<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Side-effect-free intake plan. No database writes or project creation.
 * A caller must resolve the building identity and authorize any writes.
 */
final class OfficeIntakeProcessPlan
{
    public static function prepare(array $request): array
    {
        $requestId = $request['request_id'] ?? null;
        if (!is_string($requestId) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,79}$/D', $requestId)) {
            throw new \InvalidArgumentException('invalid_request_id');
        }
        $disciplines = OfficeDisciplineCatalog::validate($request['disciplines'] ?? []);
        $buildingId = $request['building_id'] ?? null;
        if ($buildingId !== null && (!is_string($buildingId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $buildingId))) {
            throw new \InvalidArgumentException('invalid_building_id');
        }
        $plans = [];
        foreach ($disciplines as $discipline) {
            $definition = OfficeProcessBlueprint::forDiscipline($discipline);
            OfficeProcessBlueprint::validateDefinition($definition);
            $plans[] = [
                'blueprint_key' => $definition['key'],
                'blueprint_version' => $definition['version'],
                'discipline' => $discipline,
            ];
        }
        return [
            'request_id' => $requestId,
            'building_id' => $buildingId,
            'building_resolution' => $buildingId === null ? 'required' : 'linked',
            'processes' => $plans,
        ];
    }
}
