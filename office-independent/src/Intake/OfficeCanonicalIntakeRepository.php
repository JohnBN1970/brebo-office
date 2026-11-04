<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Persist a canonical request only from a trusted, authorized Office service.
 * Website or mail inputs must pass their own authentication/authorization gate.
 * A building is never guessed or created here: only a verified id is linked.
 */
final class OfficeCanonicalIntakeRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function register(
        string $source,
        array $request,
        ?int $relationId = null
    ): int {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $source)) {
            throw new \InvalidArgumentException('invalid_intake_source');
        }
        if ($relationId !== null && $relationId < 1) {
            throw new \InvalidArgumentException('invalid_relation_id');
        }

        $plan = OfficeIntakeProcessPlan::prepare($request);
        $requestId = $plan['request_id'];
        if ($plan['building_id'] !== null && filter_var($plan['building_id'], FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException('invalid_building_id');
        }
        $buildingId = $plan['building_id'] === null ? null : (int) $plan['building_id'];

        $this->db->beginTransaction();
        try {
            // Serialize retries by unique source/request identity; no duplicate event.
            $insert = $this->db->prepare(
                'INSERT INTO office_intake_requests '
                . '(source, source_request_id, relation_id, building_id, building_resolution) '
                . 'VALUES (?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $insert->execute([
                $source, $requestId, $relationId, $buildingId,
                $plan['building_resolution'],
            ]);
            $id = (int) $this->db->lastInsertId();
            if ($id < 1) {
                throw new \RuntimeException('intake_identity_unavailable');
            }

            $existing = $this->db->prepare(
                'SELECT source, source_request_id, relation_id, building_id, building_resolution '
                . 'FROM office_intake_requests WHERE id = ? FOR UPDATE'
            );
            $existing->execute([$id]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || $row['source'] !== $source || $row['source_request_id'] !== $requestId) {
                throw new \RuntimeException('intake_identity_conflict');
            }
            // Idempotent retries must not silently replace a verified building or relation.
            if (($row['building_id'] === null ? null : (int) $row['building_id']) !== $buildingId
                || ($row['relation_id'] === null ? null : (int) $row['relation_id']) !== $relationId
                || $row['building_resolution'] !== $plan['building_resolution']) {
                throw new \DomainException('intake_retry_conflict');
            }

            $count = $this->db->prepare(
                'SELECT COUNT(*) FROM office_intake_events WHERE request_id = ?'
            );
            $count->execute([$id]);
            $alreadyRecorded = (int) $count->fetchColumn() > 0;
            if (!$alreadyRecorded) {
                $discipline = $this->db->prepare(
                    'INSERT INTO office_intake_request_disciplines '
                    . '(request_id, discipline_code, blueprint_key, blueprint_version) '
                    . 'VALUES (?, ?, ?, ?)'
                );
                foreach ($plan['processes'] as $process) {
                    $discipline->execute([
                        $id,
                        $process['discipline'],
                        $process['blueprint_key'],
                        $process['blueprint_version'],
                    ]);
                }
                $event = $this->db->prepare(
                    "INSERT INTO office_intake_events (request_id, event_type) VALUES (?, 'received')"
                );
                $event->execute([$id]);
            } else {
                $disciplines = $this->db->prepare(
                    'SELECT discipline_code, blueprint_key, blueprint_version '
                    . 'FROM office_intake_request_disciplines WHERE request_id = ?'
                );
                $disciplines->execute([$id]);
                $stored = [];
                foreach ($disciplines->fetchAll(PDO::FETCH_ASSOC) as $saved) {
                    $stored[$saved['discipline_code']] = [
                        'blueprint_key' => $saved['blueprint_key'],
                        'blueprint_version' => (int) $saved['blueprint_version'],
                    ];
                }
                $submitted = [];
                foreach ($plan['processes'] as $process) {
                    $submitted[$process['discipline']] = [
                        'blueprint_key' => $process['blueprint_key'],
                        'blueprint_version' => $process['blueprint_version'],
                    ];
                }
                if ($stored !== $submitted) {
                    throw new \DomainException('intake_retry_disciplines_conflict');
                }
            }

            $this->db->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
