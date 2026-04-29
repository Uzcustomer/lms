<?php

namespace App\Enums;

enum RetakeReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Kutilmoqda',
            self::APPROVED => 'Tasdiqlangan',
            self::REJECTED => 'Rad etilgan',
        };
    }
}
