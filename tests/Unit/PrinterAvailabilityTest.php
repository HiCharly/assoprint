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
    public function test_it_names_the_cause_from_an_ipp_keyword()
    {
        $availability = PrinterAvailability::unavailable(
            'printer-state (enum) = 5'."\n".'printer-state-reasons (keyword) = media-empty-error'
        );

        $this->assertTrue($availability->blocksPrinting());
        $this->assertStringContainsString('plus de papier', $availability->memberMessage());
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
