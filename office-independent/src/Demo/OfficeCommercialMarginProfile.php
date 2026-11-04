<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use InvalidArgumentException;

/** Pure commercial profile resolution; never changes an existing Calc or offer. */
final class OfficeCommercialMarginProfile {
    public static function resolve(array $snapshot, array $profiles, ?array $customerOverride = null): array {
        $position = $snapshot['commercial_position'] ?? null;
        $categories = $snapshot['work_categories'] ?? null;
        if (!is_string($position) || !in_array($position, OfficeCommercialCalcHandoff::POSITIONS, true)
            || !is_array($categories) || $categories === []) {
            throw new InvalidArgumentException('Invalid commercial snapshot');
        }
        $profile = $profiles[$position] ?? null;
        if (!is_array($profile) || !isset($profile['version']) || !is_string($profile['version']) || $profile['version'] === '') {
            throw new InvalidArgumentException('Commercial profile missing');
        }
        if (($snapshot['profile_version'] ?? null) !== $profile['version']) {
            throw new InvalidArgumentException('Profile version mismatch; explicit reprice required');
        }
        $result = [];
        foreach ($categories as $category) {
            if (!is_string($category) || !in_array($category, OfficeCommercialCalcHandoff::ACTIVITIES, true)) {
                throw new InvalidArgumentException('Invalid work category');
            }
            $rules = $profile['categories'][$category] ?? $profile['default'] ?? null;
            if (!is_array($rules)) throw new InvalidArgumentException('No pricing rules for work category');
            if (isset($customerOverride[$category])) {
                if (!is_array($customerOverride[$category])) throw new InvalidArgumentException('Invalid customer override');
                $rules = array_replace($rules, $customerOverride[$category]);
            }
            foreach (['material_markup_pct', 'labor_markup_pct'] as $field) {
                if (!isset($rules[$field]) || !is_numeric($rules[$field]) || (float)$rules[$field] < 0) {
                    throw new InvalidArgumentException('Missing or invalid markup rule');
                }
            }
            $result[$category] = [
                'material_markup_pct' => (float)$rules['material_markup_pct'],
                'labor_markup_pct' => (float)$rules['labor_markup_pct'],
            ];
        }
        return ['commercial_position' => $position, 'profile_version' => $profile['version'], 'categories' => $result,
            'source_id' => $snapshot['source_id'] ?? null, 'requires_explicit_reprice' => true];
    }
}
