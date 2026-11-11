<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;

use PDO;

/**
 * Draft-only storage. Caller must enforce authenticated tenant-scoped permissions and CSRF.
 * Published versions and running cases are never mutated by this class.
 */
final class OfficeWorkflowDraftRepository
{
    public function __construct(private PDO $db) {}

    public function save(
        int $tenantId,
        int $blueprintId,
        int $versionId,
        int $actorId,
        bool $authorized,
        string $expectedSha256,
        array $definition
    ): string {
        if (!$authorized || min($tenantId, $blueprintId, $versionId, $actorId) < 1
            || preg_match('/^[a-f0-9]{64}$/D', $expectedSha256) !== 1) {
            throw new \DomainException('Draft update not authorized or invalid.');
        }
        OfficeWorkflowBlueprintValidator::validate($definition);
        $json = json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $newSha = hash('sha256', $json);
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare(
                'SELECT id FROM office_workflow_blueprints WHERE id = ? AND tenant_id = ? FOR UPDATE'
            );
            $lock->execute([$blueprintId, $tenantId]);
            if (!$lock->fetch(PDO::FETCH_ASSOC)) {
                throw new \DomainException('Blueprint not found in tenant.');
            }
            $version = $this->db->prepare(
                'SELECT status, definition_sha256 FROM office_workflow_blueprint_versions
                 WHERE id = ? AND blueprint_id = ? FOR UPDATE'
            );
            $version->execute([$versionId, $blueprintId]);
            $current = $version->fetch(PDO::FETCH_ASSOC);
            if (!$current || $current['status'] !== 'draft') {
                throw new \DomainException('Only draft versions may be changed.');
            }
            if (!hash_equals((string)$current['definition_sha256'], $expectedSha256)) {
                throw new \DomainException('Draft changed elsewhere; reload before saving.');
            }
            $update = $this->db->prepare(
                "UPDATE office_workflow_blueprint_versions
                 SET definition_json = ?, definition_sha256 = ?
                 WHERE id = ? AND blueprint_id = ? AND status = 'draft' AND definition_sha256 = ?"
            );
            $update->execute([$json, $newSha, $versionId, $blueprintId, $expectedSha256]);
            // MySQL reports zero changed rows for a no-op save.
            if ($update->rowCount() !== 1 && $newSha !== $expectedSha256) {
                throw new \DomainException('Concurrent draft update detected.');
            }
            $this->db->commit();
            return $newSha;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
