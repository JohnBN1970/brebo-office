<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use InvalidArgumentException;

/** CRM-owned commercial context for an optional Calculatie handoff. No prices are changed here. */
final class OfficeCommercialCalcHandoff {
    public const POSITIONS = ['toeleverancier', 'hoofdleverancier', 'particulier'];
    public const ACTIVITIES = ['montage', 'kozijnen', 'glas', 'inspectie', 'advies', 'onderhoud', 'inmeting'];

    public static function snapshot(array $crm, string $sourceId, string $profileVersion): array {
        $position = $crm['commercial_position'] ?? null;
        if (!is_string($position) || !in_array($position, self::POSITIONS, true)) {
            throw new InvalidArgumentException('Unknown commercial position');
        }
        $activities = $crm['work_categories'] ?? null;
        if (!is_array($activities) || !$activities || count($activities) !== count(array_unique($activities))) {
            throw new InvalidArgumentException('Work categories required and unique');
        }
        foreach ($activities as $activity) {
            if (!is_string($activity) || !in_array($activity, self::ACTIVITIES, true)) {
                throw new InvalidArgumentException('Unknown work category');
            }
        }
        if ($sourceId === '' || $profileVersion === '') {
            throw new InvalidArgumentException('CRM source and profile version required');
        }
        return [
            'source' => 'brebo_office_crm',
            'source_id' => $sourceId,
            'commercial_position' => $position,
            'work_categories' => array_values($activities),
            'work_scope' => $crm['work_scope'] ?? null,
            'profile_version' => $profileVersion,
            'pricing_mode' => 'resolve_in_calculatie',
            'snapshot_policy' => 'immutable_until_explicit_reprice',
        ];
    }

    public static function shouldPush(bool $calculationRequested): bool {
        return $calculationRequested;
    }
}
