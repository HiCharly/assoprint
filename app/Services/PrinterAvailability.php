<?php

namespace App\Services;

use App\Enums\PrinterState;

/**
 * Ce que l'imprimante déclarait lors de la dernière interrogation.
 *
 * L'objet porte deux niveaux de lecture : une phrase française destinée au
 * membre, et la sortie brute d'ipptool réservée à l'administrateur. Les deux ne
 * se mélangent jamais — un membre n'a rien à faire d'un mot-clé IPP.
 */
final readonly class PrinterAvailability
{
    /**
     * Message par défaut, quand l'imprimante est bloquée sans dire pourquoi.
     */
    public const DEFAULT_REASON = "L'imprimante n'est pas disponible pour le moment.";

    /**
     * Mots-clés IPP reconnus, et ce qu'ils signifient pour un membre.
     *
     * L'ordre compte : c'est le premier mot-clé trouvé qui l'emporte, et une
     * imprimante en annonce souvent plusieurs à la fois (un bac vide s'accompagne
     * volontiers d'un `offline-report`). Les causes les plus concrètes — celles
     * sur lesquelles un membre peut agir — sont donc placées en tête.
     *
     * Les valeurs réelles portent un suffixe de gravité (`media-empty-error`,
     * `media-empty-warning`) : la recherche se fait par sous-chaîne, ce qui les
     * couvre toutes. La gravité n'est pas examinée, car l'état bloqué a déjà été
     * établi par ailleurs — on ne cherche ici qu'à le nommer.
     *
     * @var array<string, string>
     */
    private const REASON_MESSAGES = [
        'media-jam' => 'Il y a un bourrage papier.',
        'media-empty' => "L'imprimante n'a plus de papier.",
        'media-needed' => "L'imprimante réclame du papier.",
        'toner-empty' => "L'imprimante n'a plus de toner.",
        'marker-supply-empty' => "L'imprimante n'a plus d'encre.",
        'door-open' => "Un capot de l'imprimante est ouvert.",
        'cover-open' => "Un capot de l'imprimante est ouvert.",
        'output-area-full' => 'Le bac de sortie est plein.',
        'shutdown' => "L'imprimante est éteinte.",
        'offline-report' => "L'imprimante semble éteinte ou déconnectée.",
        'connecting-to-device' => "Le serveur n'arrive pas à joindre l'imprimante.",
        'timed-out' => "Le serveur n'arrive pas à joindre l'imprimante.",
        'paused' => "L'imprimante a été mise en pause.",
        'spool-area-full' => "La file d'impression est saturée.",
    ];

    /**
     * Ce qui, dans un rapport `ipptool -t`, prouve que l'imprimante a répondu.
     *
     * C'est le statut de la requête IPP elle-même. Chercher la trace d'un
     * attribut ne suffirait pas : le rapport rappelle aussi les attributs
     * attendus, y compris quand il n'a rien reçu du tout. Une file au nom
     * erroné répond `client-error-not-found` tout en faisant figurer
     * « EXPECTED: printer-state » — de quoi la prendre à tort pour une
     * imprimante en panne, et faire attendre une heure des tâches qu'une
     * erreur de configuration condamne.
     *
     * L'écho de l'attente s'écrit « EXPECTED: STATUS successful-ok », qui ne
     * peut pas être confondu avec la ligne cherchée ici.
     *
     * Si ipptool changeait un jour cette mise en forme, le marqueur ne serait
     * plus trouvé et l'état deviendrait inconnu : les tâches partiraient au
     * lieu d'attendre. C'est le bon sens de la dégradation.
     */
    private const EVIDENCE_OF_ANSWER = 'status-code = successful-ok';

    private function __construct(
        public PrinterState $state,
        public ?string $reason,
        public ?string $details,
    ) {}

    /**
     * Read the verdict of an `ipptool -t` run.
     *
     * Le verdict tient au seul succès du test, jamais à la lecture du rapport :
     * les conditions sont portées par le fichier de test. Le rapport ne sert
     * qu'à distinguer une imprimante qui s'est déclarée en panne d'une question
     * restée sans réponse, puis à nommer la cause.
     *
     * @param  bool  $passed  `true` si ipptool a rendu un code de sortie nul.
     * @param  string  $report  Sa sortie standard.
     * @param  string  $fallbackDetails  De quoi renseigner l'administrateur
     *                                   quand le rapport est muet.
     */
    public static function fromReport(bool $passed, string $report, string $fallbackDetails = ''): self
    {
        if ($passed) {
            return self::available();
        }

        if (! str_contains($report, self::EVIDENCE_OF_ANSWER)) {
            return self::unknown(trim($fallbackDetails) ?: null);
        }

        return self::unavailable($report);
    }

    /**
     * The printer is ready to take a job right now.
     */
    public static function available(): self
    {
        return new self(PrinterState::Available, null, null);
    }

    /**
     * The printer state could not be established at all.
     */
    public static function unknown(?string $details = null): self
    {
        return new self(PrinterState::Unknown, null, $details);
    }

    /**
     * The printer answered, and it cannot print right now.
     *
     * La raison est déduite de la sortie d'ipptool par simple recherche de
     * mots-clés : aucune structure n'est supposée, de sorte qu'un changement de
     * format d'affichage dégrade le message sans jamais le rendre faux.
     */
    public static function unavailable(string $output): self
    {
        return new self(PrinterState::Unavailable, self::reasonFrom($output), trim($output) ?: null);
    }

    /**
     * Whether a job must wait rather than be handed over to CUPS.
     */
    public function blocksPrinting(): bool
    {
        return $this->state === PrinterState::Unavailable;
    }

    /**
     * What to tell the member about a job already waiting.
     */
    public function memberMessage(): string
    {
        return $this->say('Votre document est en attente et partira dès qu’elle sera prête.');
    }

    /**
     * What to tell a member who has not deposited anything yet.
     *
     * Le formulaire de dépôt parle au futur : rien n'est encore en attente, et
     * il s'agit surtout de dire que déposer reste utile. Dissuader ici serait
     * contre-productif — le membre repartirait avec son document sous le bras.
     */
    public function depositNotice(): string
    {
        return $this->say('Vous pouvez déposer votre document : il partira automatiquement dès qu’elle sera prête.');
    }

    /**
     * Cause and consequence in one breath.
     *
     * La seconde phrase n'est pas décorative : sans elle, un membre qui voit sa
     * tâche immobile la redépose, et le club imprime deux fois.
     */
    private function say(string $consequence): string
    {
        return ($this->reason ?? self::DEFAULT_REASON).' '.$consequence;
    }

    /**
     * The first recognised IPP keyword in the output, as a French sentence.
     */
    private static function reasonFrom(string $output): ?string
    {
        foreach (self::REASON_MESSAGES as $keyword => $message) {
            if (str_contains($output, $keyword)) {
                return $message;
            }
        }

        return null;
    }
}
