<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Pure invoice number formatting. Allocation MUST occur under a database
 * transaction with a unique constraint; this class never allocates numbers.
 */
final class OfficeSalesInvoiceNumber
{
    public static function format(int $year, int $sequence): string
    {
        if ($year < 2000 || $year > 9999 || $sequence < 1 || $sequence > 999999) {
            throw new \InvalidArgumentException('Ongeldig factuurnummerbereik');
        }

        return sprintf('VF-%04d-%06d', $year, $sequence);
    }
}
