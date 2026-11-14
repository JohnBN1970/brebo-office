<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;
use InvalidArgumentException;

/**
 * Read-only building duplicate candidate lookup.
 * Caller MUST supply a building-level authorization callback before results leave
 * the service. Matching addresses are hints; distinct projects remain allowed.
 */
final class OfficeBuildingDuplicateCandidates
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param callable(string):bool $canViewBuilding
     * @return list<array{id:string,building_name:string,street_address:string,postal_code:string,city:string,reason:string}>
     */
    public function find(string $postcode, string $streetAddress, callable $canViewBuilding, int $limit = 10): array
    {
        $postal = strtoupper(preg_replace('/\s+/u', '', trim($postcode)) ?? '');
        $address = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $streetAddress) ?? ''), 'UTF-8');
        if ($postal === '' || strlen($postal) > 32 || $address === '' || mb_strlen($address, 'UTF-8') > 255
            || $limit < 1 || $limit > 25) {
            throw new InvalidArgumentException('invalid_building_candidate_query');
        }
        // Use the existing schema only: no assumed BAG identifier or project ACL.
        $stmt = $this->db->prepare("SELECT id,building_name,street_address,postal_code,city
            FROM office_buildings WHERE active=1
            AND UPPER(REPLACE(postal_code,' ',''))=:postal
            ORDER BY id DESC LIMIT 250");
        $stmt->execute([':postal'=>$postal]);
        $result=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $candidateAddress = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$row['street_address']) ?? ''), 'UTF-8');
            if ($candidateAddress !== $address || !$canViewBuilding((string)$row['id'])) continue;
            $result[]=[
                'id'=>(string)$row['id'],
                'building_name'=>(string)$row['building_name'],
                'street_address'=>(string)$row['street_address'],
                'postal_code'=>(string)$row['postal_code'],
                'city'=>(string)$row['city'],
                'reason'=>'Zelfde postcode en straatadres; controleer huisnummer en toevoeging'
            ];
            if (count($result) >= $limit) break;
        }
        return $result;
    }
}
