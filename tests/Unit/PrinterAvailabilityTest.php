<?php

namespace Tests\Unit;

use App\Enums\PrinterState;
use App\Services\PrinterAvailability;
use PHPUnit\Framework\TestCase;

/**
 * La lecture de la sortie d'ipptool est isolée ici parce qu'elle est la seule
 * partie du contrôle d'état qui suppose quelque chose du format : le verdict,
 * lui, vient du code de sortie. Ces cas fixent ce qu'on attend d'elle, sans
 * imprimante ni ipptool.
 */
class PrinterAvailabilityTest extends TestCase
{
    /**
     * Le rapport d'un `ipptool -t` en échec, relevé tel quel sur une file mise
     * à l'arrêt. Les cas ci-dessous partent de cette forme réelle plutôt que
     * d'une chaîne inventée, pour que le jour où ipptool changera de mise en
     * forme, ce soit un test qui le dise.
     */
    private function report(string $state, string $reasons): string
    {
        return <<<REPORT
            "/app/resources/cups/printer-ready.test":
                Imprimante prête                                            [FAIL]
                    RECEIVED: 158 bytes in response
                    status-code = successful-ok (successful-ok)
                    EXPECTED: printer-state WITH-VALUE "3,4"
                    GOT: printer-state=5
                    printer-is-accepting-jobs (boolean) = true
                    printer-state (enum) = {$state}
                    printer-state-reasons (keyword) = {$reasons}
            REPORT;
    }

    public function test_it_names_the_cause_from_a_real_ipptool_report()
    {
        $availability = PrinterAvailability::unavailable($this->report('stopped', 'media-empty-error'));

        $this->assertTrue($availability->blocksPrinting());
        $this->assertStringContainsString('plus de papier', $availability->memberMessage());
    }

    public function test_a_queue_stopped_by_hand_is_reported_as_paused()
    {
        // Ce que rend `cupsdisable` : l'administrateur a arrêté la file.
        $availability = PrinterAvailability::unavailable($this->report('stopped', 'paused'));

        $this->assertStringContainsString('mise en pause', $availability->memberMessage());
    }

    public function test_it_recognises_a_keyword_whatever_its_severity_suffix()
    {
        // CUPS suffixe ses motifs (-error, -warning, -report) : la reconnaissance
        // doit porter sur le motif lui-même.
        foreach (['media-jam', 'media-jam-error', 'media-jam-warning'] as $output) {
            $this->assertStringContainsString(
                'bourrage papier',
                PrinterAvailability::unavailable($output)->memberMessage(),
                "Motif non reconnu : {$output}",
            );
        }
    }

    public function test_an_unrecognised_output_still_yields_a_usable_message()
    {
        $availability = PrinterAvailability::unavailable('printer-state = 5, motif inconnu au bataillon');

        $this->assertNull($availability->reason);
        $this->assertStringContainsString('pas disponible', $availability->memberMessage());
        $this->assertStringContainsString('partira', $availability->memberMessage());
    }

    public function test_the_member_message_never_leaks_the_raw_output()
    {
        $raw = 'printer-state (enum) = 5, printer-state-reasons (keyword) = toner-empty-error';

        $availability = PrinterAvailability::unavailable($raw);

        $this->assertStringNotContainsString('toner-empty', $availability->memberMessage());
        $this->assertStringNotContainsString('printer-state', $availability->memberMessage());

        // Le détail brut reste disponible, mais pour l'administrateur seulement.
        $this->assertSame($raw, $availability->details);
    }

    public function test_the_deposit_notice_speaks_of_a_document_not_yet_deposited()
    {
        $notice = PrinterAvailability::unavailable('media-empty')->depositNotice();

        $this->assertStringContainsString('Vous pouvez déposer', $notice);
        $this->assertStringNotContainsString('est en attente', $notice);
    }

    public function test_a_failed_report_carrying_the_printer_attributes_blocks_printing()
    {
        // Le cas qui compte : ipptool a bien parlé à l'imprimante, et elle
        // s'est déclarée arrêtée. Sans `-t`, ce rapport se réduirait à
        // « successful-ok », la cause deviendrait indétectable et la tâche
        // partirait quand même — la fonctionnalité entière serait sans effet.
        $availability = PrinterAvailability::fromReport(
            false,
            $this->report('stopped', 'media-empty-error'),
        );

        $this->assertTrue($availability->blocksPrinting());
        $this->assertStringContainsString('plus de papier', $availability->memberMessage());
    }

    public function test_a_passing_report_lets_the_job_through()
    {
        $passed = <<<'REPORT'
            "/app/resources/cups/printer-ready.test":
                Imprimante prête                                            [PASS]
                    printer-state (enum) = idle
                    printer-state-reasons (keyword) = none
                    printer-is-accepting-jobs (boolean) = true
            REPORT;

        $this->assertFalse(PrinterAvailability::fromReport(true, $passed)->blocksPrinting());
    }

    public function test_a_misnamed_queue_is_unknown_rather_than_a_blocked_printer()
    {
        // Rapport relevé pour une file qui n'existe pas. Il fait figurer
        // « EXPECTED: printer-state » alors que rien n'a été reçu : chercher la
        // trace d'un attribut le ferait passer pour une imprimante en panne, et
        // des tâches attendraient une heure sur une erreur de configuration que
        // seule une erreur franche fera corriger.
        $notFound = <<<'REPORT'
            "/app/resources/cups/printer-ready.test":
                Imprimante prête                                            [FAIL]
                    RECEIVED: 127 bytes in response
                    status-code = client-error-not-found (The printer or class does not exist.)
                    EXPECTED: STATUS successful-ok (got client-error-not-found)
                    status-message="The printer or class does not exist."
                    EXPECTED: printer-state
                    EXPECTED: printer-is-accepting-jobs
            REPORT;

        $availability = PrinterAvailability::fromReport(false, $notFound);

        $this->assertSame(PrinterState::Unknown, $availability->state);
        $this->assertFalse($availability->blocksPrinting());
    }

    public function test_a_failure_without_any_answer_is_unknown_rather_than_blocking()
    {
        // Imprimante injoignable : ipptool échoue sans avoir rien pu lire.
        // Conclure « bloquée » retiendrait les tâches de tout le club sur une
        // panne qui n'est peut-être que celle du contrôle.
        $availability = PrinterAvailability::fromReport(
            false,
            '',
            'ipptool: Unable to connect to "127.0.0.1" on port 631 - Host is down',
        );

        $this->assertSame(PrinterState::Unknown, $availability->state);
        $this->assertFalse($availability->blocksPrinting());
        $this->assertStringContainsString('Host is down', (string) $availability->details);
    }

    public function test_an_unknown_state_does_not_block_printing()
    {
        $availability = PrinterAvailability::unknown('ipptool: command not found');

        $this->assertSame(PrinterState::Unknown, $availability->state);
        $this->assertFalse($availability->blocksPrinting());
    }

    public function test_an_available_printer_does_not_block_printing()
    {
        $this->assertFalse(PrinterAvailability::available()->blocksPrinting());
    }
}
