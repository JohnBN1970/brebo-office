<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

final class OfficeProjectContractValue
{
    private static function cents(string $amount): int
    {
        if (!preg_match(
            '/^(0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D',
            $amount
        )) {
            throw new \InvalidArgumentException('Invalid monetary amount');
        }

        [$euros, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        return ((int) $euros * 100)
            + (int) str_pad($fraction, 2, '0');
    }

    private static function money(int $cents): string
    {
        return sprintf(
            '%s%d.%02d',
            $cents < 0 ? '-' : '',
            intdiv(abs($cents), 100),
            abs($cents) % 100
        );
    }

    public static function calculate(
        string $contractAmount,
        array $approvedChanges
    ): array {
        $original = self::cents($contractAmount);
        $more = 0;
        $less = 0;

        foreach ($approvedChanges as $change) {
            $type = $change['change_type'] ?? null;
            $amount = self::cents((string) ($change['amount'] ?? ''));

            if ($type === 'more') {
                $more += $amount;
            } elseif ($type === 'less') {
                $less += $amount;
            } else {
                throw new \InvalidArgumentException('Invalid change type');
            }
        }

        $current = $original + $more - $less;

        if ($current < 0) {
            throw new \DomainException(
                'Adjusted contract value cannot be negative'
            );
        }

        return [
            'original' => self::money($original),
            'approved_more' => self::money($more),
            'approved_less' => self::money($less),
            'current' => self::money($current),
        ];
    }
}
