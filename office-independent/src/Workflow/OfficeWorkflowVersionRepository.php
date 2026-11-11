<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;
use PDO;

/**
 * Creates a new draft version from a validated definition. Published versions
 * are immutable and existing case bindings retain their original version.
 * Authentication, CSRF and tenant-level edit rights belong to the caller.
 */
final class OfficeWorkflowVersionRepository
{
    public function __construct(private PDO $db) {}

    public function createDraft(
        int $tenantId,
        int $blueprintId,
        int $actorId,
        bool $authorized,
        array $definition
    ): array {
        if (!$authorized || min($tenantId, $blueprintId, $actorId) < 1) {
            throw new \DomainException('Workflow draft creation denied.');
        }
        OfficeWorkflowBlueprintValidator::validate($definition);
        $json = json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $sha = hash('sha256', $json);
        $this->db->beginTransaction();
        try {
            $owner = $this->db->prepare(
                'SELECT id, blueprint_key FROM office_workflow_blueprints
                 WHERE id = ? AND tenant_id = ? FOR UPDATE'
            );
            $owner->execute([$blueprintId, $tenantId]);
            $blueprint = $owner->fetch(PDO::FETCH_ASSOC);
            if (!$blueprint || !hash_equals((string)$blueprint['blueprint_key'], (string)$definition['key'])) {
                throw new \DomainException('Blueprint not found or key mismatch.');
            }
            $latest = $this->db->prepare(
                'SELECT COALESCE(MAX(version_number), 0) FROM office_workflow_blueprint_versions
                 WHERE blueprint_id = ?'
            );
            $latest->execute([$blueprintId]);
            $next = (int)$latest->fetchColumn() + 1;
            $insert = $this->db->prepare(
                "INSERT INTO office_workflow_blueprint_versions
                 (blueprint_id, version_number, status, definition_json, definition_sha256, created_by)
                 VALUES (?, ?, 'draft', ?, ?, ?)"
            );
            $insert->execute([$blueprintId, $next, $json, $sha, $actorId]);
            $versionId = (int)$this->db->lastInsertId();
            $this->db->commit();
            return ['version_id'=>$versionId, 'version_number'=>$next, 'definition_sha256'=>$sha];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
