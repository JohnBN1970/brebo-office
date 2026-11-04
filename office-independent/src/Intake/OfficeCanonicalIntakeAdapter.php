<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Maps a trusted upstream intake envelope into the canonical Office contract.
 * Does not authenticate requests, infer buildings or create CRM/project records.
 * A caller must authorize the source and resolve identities before persistence.
 */
final class OfficeCanonicalIntakeAdapter
{
    /**
     * @return array{source:string,request:array,relation_id:?int}
     */
    public static function map(array $envelope): array
    {
        $source = $envelope['source'] ?? null;
        if (!is_string($source) || !preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $source)) {
            throw new \InvalidArgumentException('invalid_intake_source');
        }
        $requestId = $envelope['request_id'] ?? null;
        if (!is_string($requestId)) {
            throw new \InvalidArgumentException('invalid_request_id');
        }
        $disciplines = $envelope['disciplines'] ?? null;
        if (!is_array($disciplines)) {
            throw new \InvalidArgumentException('invalid_disciplines');
        }
        $buildingId = $envelope['building_id'] ?? null;
        if ($buildingId !== null && (!is_string($buildingId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $buildingId))) {
            throw new \InvalidArgumentException('invalid_building_id');
        }
        $relationId = $envelope['relation_id'] ?? null;
        if ($relationId !== null && (!is_int($relationId) || $relationId < 1)) {
            throw new \InvalidArgumentException('invalid_relation_id');
        }
        // An unverified address or inferred match is not a verified building id.
        // Identity resolution must be performed by a separate authorized service.
        $plan = OfficeIntakeProcessPlan::prepare([
            'request_id' => $requestId,
            'building_id' => $buildingId,
            'disciplines' => $disciplines,
        ]);
        return [
            'source' => $source,
            'request' => [
                'request_id' => $plan['request_id'],
                'building_id' => $plan['building_id'],
                'disciplines' => array_map(
                    static fn (array $process): string => $process['discipline'],
                    $plan['processes']
                ),
            ],
            'relation_id' => $relationId,
        ];
    }

    /**
     * Trusted internal handoff only; caller must enforce source permissions.
     * No direct public API route is registered by this class.
     */
    public static function register(
        OfficeCanonicalIntakeRepository $repository,
        array $envelope
    ): int {
        $mapped = self::map($envelope);
        return $repository->register(
            $mapped['source'],
            $mapped['request'],
            $mapped['relation_id']
        );
    }
}
