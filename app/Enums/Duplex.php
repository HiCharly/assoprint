<?php

namespace App\Enums;

enum Duplex: string
{
    case None = 'none';
    case LongEdge = 'long-edge';
    case ShortEdge = 'short-edge';

    /**
     * The value of the CUPS `sides` option matching this mode.
     */
    public function cupsSides(): string
    {
        return match ($this) {
            self::None => 'one-sided',
            self::LongEdge => 'two-sided-long-edge',
            self::ShortEdge => 'two-sided-short-edge',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'Recto simple',
            self::LongEdge => 'Recto verso (bord long)',
            self::ShortEdge => 'Recto verso (bord court)',
        };
    }
}
