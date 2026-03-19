<?php

namespace App\Enums;

enum ApiKeyTier: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
            self::Enterprise => 'Enterprise',
        };
    }
}
