<?php

namespace App\Enums;

enum PrintJobStatus: string
{
    case Pending = 'pending';
    case Printing = 'printing';
    case Printed = 'printed';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Printing => 'Impression en cours',
            self::Printed => 'Imprimé',
            self::Error => 'Erreur',
        };
    }

    /**
     * Whether the job is still expected to change state on its own.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::Pending, self::Printing], true);
    }
}
