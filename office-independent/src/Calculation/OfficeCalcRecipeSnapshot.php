<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Pure, Office-native recipe expansion. No writes and no Drupal dependency.
 * Prices are snapshots from an approved source, never guessed here.
 */
final class OfficeCalcRecipeSnapshot
{
    private static function decimal(mixed $value, bool $allowZero = false): string
    {
        if (!is_string($value) || !preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\\.[0-9]{1,4})?$/D', $value)
            || (!$allowZero && bccomp($value, '0', 4) <= 0)) {
            throw new DomainException('Ongeldige recepthoeveelheid of factor.');
        }
        return bcadd($value, '0', 4);
    }

    /**
     * @param array{code:string,version:int,unit:string,lines:list<array{code:string,description:string,cost_type:string,unit:string,per_unit:string,unit_cost:?string,price_source:?array}>} $recipe
     * @return array<string,mixed>
     */
    public static function expand(array $recipe, string $buildingQuantity, string $buildingUnit, string $sourceRef): array
    {
        if (!extension_loaded('bcmath')) throw new DomainException('BCMath vereist.');
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $sourceRef)
            || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $recipe['code'] ?? '')
            || !is_int($recipe['version'] ?? null) || $recipe['version'] < 1
            || ($recipe['unit'] ?? null) !== $buildingUnit
            || !is_array($recipe['lines'] ?? null) || $recipe['lines'] === []) {
            throw new DomainException('Receptbron of eenheid ongeldig.');
        }
        $quantity = self::decimal($buildingQuantity);
        $lines = [];
        $seen = [];
        foreach ($recipe['lines'] as $line) {
            if (!is_array($line) || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $line['code'] ?? '')
                || isset($seen[$line['code']]) || trim((string)($line['description'] ?? '')) === ''
                || !in_array($line['cost_type'] ?? null, ['labor','material','equipment','subcontract','other'], true)
                || !is_string($line['unit'] ?? null) || trim($line['unit']) === '') {
                throw new DomainException('Ongeldige of dubbele receptregel.');
            }
            $seen[$line['code']] = true;
            $factor = self::decimal($line['per_unit'], true);
            $cost = $line['unit_cost'] ?? null;
            if ($cost !== null) $cost = self::decimal($cost, true);
            if ($cost === null && !empty($line['price_source'])) {
                throw new DomainException('Prijsbron zonder gecontroleerde prijs.');
            }
            $lines[] = [
                'code' => $line['code'],
                'description' => trim($line['description']),
                'cost_type' => $line['cost_type'],
                'unit' => $line['unit'],
                'quantity' => bcmul($quantity, $factor, 4),
                'unit_cost' => $cost,
                'price_source' => $line['price_source'] ?? null,
                'pricing_status' => $cost === null ? 'requires_price' : 'priced',
            ];
        }
        return [
            'recipe_code' => $recipe['code'],
            'recipe_version' => $recipe['version'],
            'building_source_ref' => $sourceRef,
            'building_quantity' => $quantity,
            'building_unit' => $buildingUnit,
            'lines' => $lines,
        ];
    }
}
