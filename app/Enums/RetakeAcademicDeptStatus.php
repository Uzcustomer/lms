<?php

namespace App\Enums;

enum RetakeAcademicDeptStatus: string
{
    case NOT_STARTED = 'not_started';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Boshlanmagan',
            self::PENDING => "O'quv bo'limida kutilmoqda",
            self::APPROVED => 'Tasdiqlangan',
            self::REJECTED => 'Rad etilgan',
        };
    }
}
