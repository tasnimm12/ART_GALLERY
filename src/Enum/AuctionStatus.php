<?php

namespace App\Enum;

enum AuctionStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case ACTIVE = 'ACTIVE';
    case ENDED = 'ENDED';
    case CANCELLED = 'CANCELLED';

    public static function values(): array
    {
        return array_map(fn(self $s) => $s->value, self::cases());
    }

    public static function choices(): array
    {
        // For forms: label => value
        return [
            'SCHEDULED' => self::SCHEDULED->value,
            'ACTIVE' => self::ACTIVE->value,
            'ENDED' => self::ENDED->value,
            'CANCELLED' => self::CANCELLED->value,
        ];
    }
}
