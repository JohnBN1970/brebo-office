<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;

require_once __DIR__ . '/OfficeBuildingColumnResolver.php';

/**
 * Read-only building lookup for Office-test canonical intake.
 * Exact normalized address/postcode/city only. Ambiguity is never auto-linked.
 * Never inserts buildings or changes an existing request.
 */
final class OfficeIntakeBuildingResolver
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array{status:string,building_id:?string,candidates:int}
     */
    public function resolve(string $address, string $postalCode, string $city): array
    {
        $address = self::normalize($address);
        $postalCode = self::postalCode($postalCode);
        $city = self::normalize($city);
        if ($address === '' || $postalCode === '' || $city === '') {
            return ['status' => 'required', 'building_id' => null, 'candidates' => 0];
        }

        // The native Office schema uses street_address; legacy uses address_line.
        // Only a whitelisted column identifier can enter this SQL statement.
        $addressColumn = OfficeBuildingColumnResolver::columns($this->db)['address'];
        $query = $this->db->prepare(
            "SELECT id, {$addressColumn} AS address_line, postal_code, city FROM office_buildings "
            . "WHERE UPPER(REPLACE(postal_code, ' ', '')) = ? "
            . "ORDER BY id LIMIT 101"
        );
        $query->execute([$postalCode]);
        $buildings = $query->fetchAll(PDO::FETCH_ASSOC);
        // A capped result set cannot establish uniqueness. Never auto-link.
        if (count($buildings) > 100) {
            return ['status' => 'ambiguous', 'building_id' => null, 'candidates' => count($buildings)];
        }
        $matches = [];
        foreach ($buildings as $building) {
            if (self::normalize((string) $building['address_line']) === $address
                && self::normalize((string) $building['city']) === $city) {
                $matches[] = (string) $building['id'];
            }
        }
        if (count($matches) === 1) {
            return ['status' => 'linked', 'building_id' => $matches[0], 'candidates' => 1];
        }
        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'building_id' => null, 'candidates' => count($matches)];
        }
        return ['status' => 'required', 'building_id' => null, 'candidates' => 0];
    }

    private static function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        return preg_replace('/\\s+/u', ' ', $value) ?? '';
    }

    private static function postalCode(string $value): string
    {
        return strtoupper(preg_replace('/\\s+/u', '', trim($value)) ?? '');
    }
}
