<?php

namespace App\Enums;

enum RetakeGroupStatus: string
{
    case FORMING = 'forming';
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::FORMING => 'Shakllantirilmoqda',
            self::SCHEDULED => 'Rejalashtirilgan',
            self::IN_PROGRESS => 'Davom etmoqda',
            self::COMPLETED => 'Tugagan',
        };
    }
}
