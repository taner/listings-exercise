<?php

namespace App\Enums;

/**
 * How often a saved search may alert its owner. A new match is recorded the
 * moment a listing goes live; this decides when that match is sent.
 */
enum AlertFrequency: string
{
    case Instant = 'instant';
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * Value/label pairs for building a select. Keeps the option list in one
     * place rather than duplicated in the front end.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Instant => 'Instantly',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }
}
