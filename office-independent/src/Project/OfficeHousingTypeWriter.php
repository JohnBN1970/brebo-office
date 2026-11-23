<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use DomainException;
use InvalidArgumentException;
use PDO;

/** Writes housing type truth only for an already linked project building. */
final class OfficeHousingTypeWriter
{
    public function __construct(private readonly PDO $db) {}

    public function assign(int $projectId, int $buildingId, string $code, string $name, int $dwellingCount): void
    {
        $code = trim($code);
        $name = trim($name);
        if ($projectId < 1 || $buildingId < 1 || $dwellingCount < 1 || $dwellingCount > 100000
            || $code === '' || $name === '' || mb_strlen($code) > 100 || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Controleer woningtype, code en woningaantal.');
        }

        $this->db->beginTransaction();
        try {
            $linked = $this->db->prepare(
                'SELECT 1 FROM office_project_buildings WHERE project_id = ? AND building_id = ? LIMIT 1'
            );
            $linked->execute([$projectId, $buildingId]);
            if ($linked->fetchColumn() === false) {
                throw new DomainException('Gebouw is niet gekoppeld aan dit project.');
            }
            $existing = $this->db->prepare(
                'SELECT id, name FROM office_housing_types WHERE building_id = ? AND code = ? FOR UPDATE'
            );
            $existing->execute([$buildingId, $code]);
            $type = $existing->fetch(PDO::FETCH_ASSOC);
            if ($type === false) {
                $insert = $this->db->prepare(
                    'INSERT INTO office_housing_types (building_id, code, name) VALUES (?, ?, ?)'
                );
                $insert->execute([$buildingId, $code, $name]);
                $housingTypeId = (int) $this->db->lastInsertId();
            } else {
                if ((string) $type['name'] !== $name) {
                    throw new DomainException('Deze woningtypecode bestaat al met een andere naam.');
                }
                $housingTypeId = (int) $type['id'];
            }
            $assign = $this->db->prepare(
                'INSERT INTO office_project_housing_types (project_id, building_id, housing_type_id, dwelling_count)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE dwelling_count = VALUES(dwelling_count)'
            );
            $assign->execute([$projectId, $buildingId, $housingTypeId, $dwellingCount]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
