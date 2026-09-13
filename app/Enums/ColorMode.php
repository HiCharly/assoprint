<?php

namespace App\Enums;

enum ColorMode: string
{
    case Color = 'color';
    case BlackAndWhite = 'bw';

    /**
     * The value of the CUPS `print-color-mode` option matching this mode.
     */
    public function cupsPrintColorMode(): string
    {
        return match ($this) {
            self::Color => 'color',
            self::BlackAndWhite => 'monochrome',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Color => 'Couleur',
            self::BlackAndWhite => 'Noir et blanc',
        };
    }
}
