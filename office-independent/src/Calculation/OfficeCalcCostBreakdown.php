<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Pure calculation-grid cost allocation. All prices exclude VAT and are
 * decimal strings; no float conversions and no database side effects.
 */
final class OfficeCalcCostBreakdown
{
    public const TYPES = ['labor','material','equipment','subcontract','other'];

    public static function calculate(string $quantity, array $unitCosts, string $vatPercent = '21'): array
    {
        self::decimal($quantity, 8, 4);
        if (bccomp($quantity, '0', 4) <= 0) {
            throw new DomainException('Hoeveelheid moet groter zijn dan nul.');
        }
        self::decimal($vatPercent, 3, 2);
        if (bccomp($vatPercent, '100', 2) > 0) {
            throw new DomainException('Btw-percentage buiten bereik.');
        }
        if (array_diff(array_keys($unitCosts), self::TYPES) !== []) {
            throw new DomainException('Onbekende kostencategorie.');
        }
        $columns = [];
        $unitTotal = '0.0000';
        $netTotal = '0.0000';
        foreach (self::TYPES as $type) {
            $value = $unitCosts[$type] ?? '0';
            if (!is_string($value)) {
                throw new DomainException('Kosten moeten exacte decimale tekst zijn.');
            }
            self::decimal($value, 10, 4);
            $unitTotal = bcadd($unitTotal, $value, 4);
            $lineTotal = bcmul($quantity, $value, 4);
            $netTotal = bcadd($netTotal, $lineTotal, 4);
            $columns[$type] = ['unit_cost' => bcadd($value, '0', 4), 'total' => $lineTotal];
        }
        $vat = bcdiv(bcmul($netTotal, $vatPercent, 6), '100', 4);
        return [
            'quantity' => bcadd($quantity, '0', 4),
            'columns' => $columns,
            'unit_cost' => $unitTotal,
            'net_total' => $netTotal,
            'vat_percent' => bcadd($vatPercent, '0', 2),
            'vat_total' => $vat,
            'gross_total' => bcadd($netTotal, $vat, 4),
        ];
    }

    private static function decimal(string $value, int $wholeDigits, int $fractionDigits): void
    {
        if (!preg_match('/^(?:0|[1-9][0-9]{0,'.($wholeDigits - 1).'})(?:\.[0-9]{1,'.$fractionDigits.'})?$/D', $value)) {
            throw new DomainException('Ongeldige decimale calculatiewaarde.');
        }
    }
}
