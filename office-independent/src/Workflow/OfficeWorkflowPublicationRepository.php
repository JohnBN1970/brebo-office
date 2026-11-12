<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;

use PDO;

/**
 * Publishes one validated version under a database lock.
 * Caller is responsible for authentication, tenant authorization and CSRF.
 * Does not execute any workflow actions or migrate running cases.
 */
final class OfficeWorkflowPublicationRepository
{
    public function __construct(private PDO $db) {}

    public function publish(int $tenantId, int $blueprintId, int $versionId, int $actorId, bool $authorized, string $expectedSha256 = ''): array
    {
        if (!$authorized || min($tenantId, $blueprintId, $versionId, $actorId) < 1) {
            throw new \DomainException('Not authorized to publish workflow.');
        }
        $this->db->beginTransaction();
        try {
            $owner = $this->db->prepare(
                'SELECT id FROM office_workflow_blueprints WHERE id = ? AND tenant_id = ? FOR UPDATE'
            );
            $owner->execute([$blueprintId, $tenantId]);
            if (!$owner->fetch(PDO::FETCH_ASSOC)) {
                throw new \DomainException('Blueprint not found in tenant.');
            }
            $get = $this->db->prepare(
                'SELECT id, status, definition_json, definition_sha256
                 FROM office_workflow_blueprint_versions
                 WHERE id = ? AND blueprint_id = ? FOR UPDATE'
            );
            $get->execute([$versionId, $blueprintId]);
            $version = $get->fetch(PDO::FETCH_ASSOC);
            if (!$version || $version['status'] !== 'draft') {
                throw new \DomainException('Only draft versions can be published.');
            }
            if ($expectedSha256 !== '' && !hash_equals((string)$version['definition_sha256'], $expectedSha256)) {
                throw new \DomainException('Draft changed since last review.');
            }
            $json = (string) $version['definition_json'];
            if (!hash_equals((string) $version['definition_sha256'], hash('sha256', $json))) {
                throw new \DomainException('Workflow definition integrity mismatch.');
            }
            $definition = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($definition)) {
                throw new \DomainException('Workflow definition must be an object.');
            }
            $prepared = OfficeWorkflowPublicationPolicy::prepare($definition, 'draft', true, $tenantId);
            $retire = $this->db->prepare(
                "UPDATE office_workflow_blueprint_versions SET status = 'retired'
                 WHERE blueprint_id = ? AND status = 'published'"
            );
            $retire->execute([$blueprintId]);
            $publish = $this->db->prepare(
                "UPDATE office_workflow_blueprint_versions
                 SET status = 'published', published_by = ?, published_at = UTC_TIMESTAMP()
                 WHERE id = ? AND blueprint_id = ? AND status = 'draft'"
            );
            $publish->execute([$actorId, $versionId, $blueprintId]);
            if ($publish->rowCount() !== 1) {
                throw new \DomainException('Concurrent workflow publication detected.');
            }
            $this->db->commit();
            return ['version_id' => $versionId, 'definition_sha256' => $prepared['definition_sha256']];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
