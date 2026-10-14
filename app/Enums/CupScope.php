<?php

namespace App\Enums;

/** El ámbito de una copa: la de casa o una de las que existen en el mundo real. */
enum CupScope: string
{
    case National = 'national';
    case International = 'international';

    public function label(): string
    {
        return match ($this) {
            self::National => 'Nacional',
            self::International => 'Internacional',
        };
    }
}
