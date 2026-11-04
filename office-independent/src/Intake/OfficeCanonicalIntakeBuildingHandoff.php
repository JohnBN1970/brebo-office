<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

use Brebo\Office\Buildings\OfficeIntakeBuildingResolver;

/**
 * Trusted internal orchestration of exact building lookup and canonical intake.
 * No public endpoint, no inferred building creation and no CRM mutations.
 */
final class OfficeCanonicalIntakeBuildingHandoff
{
    public function __construct(
        private readonly OfficeIntakeBuildingResolver $buildings,
        private readonly OfficeCanonicalIntakeRepository $requests
    ) {
    }

    /**
     * @return array{request_id:int,building_resolution:string,building_id:?string,candidates:int}
     */
    public function register(array $envelope): array
    {
        $mapped = OfficeCanonicalIntakeAdapter::map($envelope);
        $buildingId = $mapped['request']['building_id'];
        $resolution = $buildingId === null ? 'required' : 'linked';
        $candidates = $buildingId === null ? 0 : 1;
        if ($buildingId === null) {
            $address = $envelope['address'] ?? null;
            $postalCode = $envelope['postal_code'] ?? null;
            $city = $envelope['city'] ?? null;
            if (is_string($address) && is_string($postalCode) && is_string($city)) {
                $match = $this->buildings->resolve($address, $postalCode, $city);
                $resolution = $match['status'];
                $candidates = $match['candidates'];
                $buildingId = $match['building_id'];
                $mapped['request']['building_id'] = $buildingId;
            }
        }
        $id = $this->requests->register(
            $mapped['source'],
            $mapped['request'],
            $mapped['relation_id']
        );
        return [
            'request_id' => $id,
            'building_resolution' => $resolution,
            'building_id' => $buildingId,
            'candidates' => $candidates,
        ];
    }
}
