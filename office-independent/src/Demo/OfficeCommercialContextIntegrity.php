<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use DomainException;
use InvalidArgumentException;
use PDO;

/** Checks CRM source and profile before the caller may write commercial context. */
final class OfficeCommercialContextIntegrity {
    public function __construct(private PDO $db) {}

    public function assertProfile(?int $profileId, string $position): void {
        if ($profileId === null) return;
        if ($profileId < 1 || !in_array($position, OfficeCommercialCalcHandoff::POSITIONS, true)) {
            throw new InvalidArgumentException('Invalid commercial profile');
        }
        $query = $this->db->prepare('SELECT position_code, status FROM office_commercial_profiles WHERE id = ?');
        $query->execute([$profileId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['position_code'] !== $position || $row['status'] !== 'active') {
            throw new DomainException('Commercial profile is missing, inactive or belongs to another position');
        }
    }

    /** A source lookup is injected to avoid assuming CRM source tables or tenant rules. */
    public static function assertSource(string $sourceType, string $sourceId, callable $authorizedSourceLookup): void {
        if (!in_array($sourceType, ['opportunity', 'direct_order', 'followup_order'], true)
            || $sourceId === '' || strlen($sourceId) > 128) {
            throw new InvalidArgumentException('Invalid CRM source');
        }
        if ($authorizedSourceLookup($sourceType, $sourceId) !== true) {
            throw new DomainException('CRM source is unavailable or not authorized');
        }
    }
}
