<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use PDO;

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

        $query = $this->db->prepare(
            'SELECT id, address_line, postal_code, city FROM office_buildings '
            . 'WHERE UPPER(REPLACE(postal_code, \' \', \'\')) = ? '
            . 'ORDER BY id LIMIT 100'
        );
        $query->execute([$postalCode]);
        $matches = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $building) {
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
