<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use InvalidArgumentException;

/** Exact native Calc values; cents are rounded once per execution budget line. */
final class OfficeCalcAcceptedLineAmounts
{
    public static function fromLine(array $line): array
    {
        $hasQuantity = array_key_exists('quantity_decimal', $line);
        $hasPrice = array_key_exists('unit_cost_decimal', $line);
        if ($hasQuantity || $hasPrice) {
            if (!$hasQuantity || !$hasPrice || !extension_loaded('bcmath')) {
                throw new InvalidArgumentException('Exact Calc amounts require quantity, price and BCMath');
            }
            $quantity = self::decimal($line['quantity_decimal'], 4, 15);
            $price = self::decimal($line['unit_cost_decimal'], 4, 15);
            if ($quantity === null || $price === null) {
                throw new InvalidArgumentException('Invalid exact Calc quantity or unit price');
            }
            $unitCents = bcmul($price, '100', 2);
            $cents = bcadd(bcmul($quantity, $unitCents, 6), '0.5', 0);
            if (bccomp($cents, (string) PHP_INT_MAX, 0) > 0) {
                throw new InvalidArgumentException('Accepted calculation line budget overflows integer cents');
            }
            return ['quantity'=>$quantity, 'unit_cost_cents'=>$unitCents, 'budget_cents'=>(int)$cents];
        }
        $quantity = $line['quantity'] ?? null;
        $unitCents = $line['unit_cost_cents'] ?? null;
        if (!is_int($quantity) || $quantity < 0 || !is_int($unitCents) || $unitCents < 0
            || ($quantity > 0 && $unitCents > intdiv(PHP_INT_MAX, $quantity))) {
            throw new InvalidArgumentException('Invalid or overflowing accepted calculation amounts');
        }
        return ['quantity'=>$quantity, 'unit_cost_cents'=>$unitCents, 'budget_cents'=>$quantity*$unitCents];
    }

    /** No integer casts: truncation or substitution must fail verification. */
    public static function equal(mixed $stored, mixed $expected, int $scale): bool
    {
        $left = self::decimal($stored, $scale, 24);
        $right = self::decimal($expected, $scale, 24);
        return $left !== null && $right !== null && $left === $right;
    }

    private static function decimal(mixed $value, int $scale, int $digits): ?string
    {
        if (is_int($value)) $value = (string)$value;
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/D', $value, $parts)
            || strlen($parts[1]) > $digits) return null;
        $fraction = rtrim($parts[2] ?? '', '0');
        if (strlen($fraction) > $scale) return null;
        return $parts[1].($scale > 0 ? '.'.str_pad($fraction, $scale, '0') : '');
    }
}
