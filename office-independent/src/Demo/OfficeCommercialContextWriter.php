<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use InvalidArgumentException;
use PDO;

/** Explicit CRM context write service. Caller supplies a verified operator permission. */
final class OfficeCommercialContextWriter {
    public function __construct(private PDO $db) {}

    public function save(bool $authorized, string $sourceType, string $sourceId, string $position, array $categories, ?string $scope, ?int $profileId): void {
        if (!$authorized) throw new InvalidArgumentException('Commercial context write denied');
        if (!in_array($sourceType, ['opportunity', 'direct_order', 'followup_order'], true) || $sourceId === '') {
            throw new InvalidArgumentException('Invalid CRM source');
        }
        if (!in_array($position, OfficeCommercialCalcHandoff::POSITIONS, true)) {
            throw new InvalidArgumentException('Invalid commercial position');
        }
        if (!$categories || count($categories) !== count(array_unique($categories))) {
            throw new InvalidArgumentException('Invalid work categories');
        }
        foreach ($categories as $category) {
            if (!is_string($category) || !in_array($category, OfficeCommercialCalcHandoff::ACTIVITIES, true)) {
                throw new InvalidArgumentException('Invalid work category');
            }
        }
        if ($profileId !== null && $profileId < 1) throw new InvalidArgumentException('Invalid profile ID');
        $statement = $this->db->prepare('INSERT INTO office_crm_commercial_context
            (source_type, source_id, position_code, work_categories_json, work_scope, profile_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE position_code = VALUES(position_code),
            work_categories_json = VALUES(work_categories_json), work_scope = VALUES(work_scope),
            profile_id = VALUES(profile_id)');
        $statement->execute([$sourceType, $sourceId, $position, json_encode(array_values($categories), JSON_THROW_ON_ERROR), $scope, $profileId]);
    }
}
