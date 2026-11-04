<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use InvalidArgumentException;
use PDO;

/** Persistence only. Caller must enforce Office operator permissions before invoking writes. */
final class OfficeCommercialProfileRepository {
    public function __construct(private PDO $db) {}

    public function findProfile(string $position, string $version): ?array {
        if (!in_array($position, OfficeCommercialCalcHandoff::POSITIONS, true) || $version === '') {
            throw new InvalidArgumentException('Invalid commercial profile key');
        }
        $query = $this->db->prepare('SELECT id, position_code, version_code, status FROM office_commercial_profiles WHERE position_code = ? AND version_code = ?');
        $query->execute([$position, $version]);
        $profile = $query->fetch(PDO::FETCH_ASSOC);
        if (!$profile) return null;
        $rules = $this->db->prepare('SELECT category_code, material_markup_pct, labor_markup_pct FROM office_commercial_profile_rules WHERE profile_id = ?');
        $rules->execute([$profile['id']]);
        $profile['categories'] = [];
        foreach ($rules->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            $profile['categories'][$rule['category_code']] = [
                'material_markup_pct' => (float)$rule['material_markup_pct'],
                'labor_markup_pct' => (float)$rule['labor_markup_pct'],
            ];
        }
        return $profile;
    }

    public function findCrmContext(string $sourceType, string $sourceId): ?array {
        if (!in_array($sourceType, ['opportunity', 'direct_order', 'followup_order'], true) || $sourceId === '') {
            throw new InvalidArgumentException('Invalid CRM source');
        }
        $query = $this->db->prepare('SELECT source_type, source_id, position_code, work_categories_json, work_scope, profile_id FROM office_crm_commercial_context WHERE source_type = ? AND source_id = ?');
        $query->execute([$sourceType, $sourceId]);
        $context = $query->fetch(PDO::FETCH_ASSOC);
        if (!$context) return null;
        $context['work_categories'] = json_decode($context['work_categories_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($context['work_categories_json']);
        return $context;
    }
}
