<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En cola',
            self::Processing => 'Procesando',
            self::Completed => 'Completado',
            self::Failed => 'Falló',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
