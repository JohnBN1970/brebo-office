<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Pure selection model: a paragraph contains selectable recipe sections.
 * Alternatives are retained for comparison but excluded from baseline totals.
 */
final class OfficeCalcRecipeChoices
{
    public static function resolve(array $sections, array $selections): array
    {
        $result = []; $seen = [];
        foreach ($sections as $section) {
            $code = $section['code'] ?? null;
            if (!is_string($code) || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $code)
                || isset($seen[$code]) || !is_array($section['options'] ?? null)
                || $section['options'] === []) {
                throw new DomainException('Ongeldige receptrubriek.');
            }
            $seen[$code] = true;
            $options = [];
            foreach ($section['options'] as $option) {
                $key = $option['code'] ?? null;
                if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $key)
                    || isset($options[$key]) || !is_array($option['recipe'] ?? null)) {
                    throw new DomainException('Ongeldig receptalternatief.');
                }
                $options[$key] = $option;
            }
            $selection = $selections[$code] ?? ['enabled'=>false,'primary'=>null,'alternatives'=>[]];
            if (!is_array($selection) || !is_bool($selection['enabled'] ?? null)
                || !is_array($selection['alternatives'] ?? null)) {
                throw new DomainException('Ongeldige receptkeuze.');
            }
            $primary = $selection['primary'] ?? null;
            if ($selection['enabled'] && (!is_string($primary) || !isset($options[$primary]))) {
                throw new DomainException('Geselecteerde rubriek heeft geen geldig basisrecept.');
            }
            if (!$selection['enabled'] && $primary !== null) {
                throw new DomainException('Uitgeschakelde rubriek mag geen basisrecept hebben.');
            }
            $alternativeCodes = [];
            foreach ($selection['alternatives'] as $alternative) {
                if (!is_string($alternative) || !isset($options[$alternative])
                    || $alternative === $primary || isset($alternativeCodes[$alternative])) {
                    throw new DomainException('Ongeldige of dubbele alternatieve keuze.');
                }
                $alternativeCodes[$alternative] = true;
            }
            $result[] = [
                'code'=>$code,
                'enabled'=>$selection['enabled'],
                'baseline'=>$selection['enabled'] ? $options[$primary] : null,
                'alternatives'=>array_map(static fn($key)=>$options[$key], array_keys($alternativeCodes)),
                'available_options'=>array_keys($options),
            ];
        }
        foreach (array_keys($selections) as $key) {
            if (!isset($seen[$key])) throw new DomainException('Keuze voor onbekende rubriek.');
        }
        return $result;
    }
}
