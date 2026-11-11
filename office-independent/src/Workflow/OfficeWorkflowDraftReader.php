<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;
use PDO;

/** Tenant-scoped, read-only workflow draft loader. */
final class OfficeWorkflowDraftReader
{
    public function __construct(private PDO $db) {}

    public function read(int $tenantId, int $blueprintId, int $versionId, bool $authorized): array
    {
        if (!$authorized || min($tenantId, $blueprintId, $versionId) < 1) {
            throw new \DomainException('Workflow access denied.');
        }
        $query = $this->db->prepare(
            'SELECT v.id, v.version_number, v.status, v.definition_json, v.definition_sha256
             FROM office_workflow_blueprint_versions v
             INNER JOIN office_workflow_blueprints b ON b.id = v.blueprint_id
             WHERE b.tenant_id = ? AND b.id = ? AND v.id = ? LIMIT 1'
        );
        $query->execute([$tenantId, $blueprintId, $versionId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status'] !== 'draft') {
            throw new \DomainException('Draft not found.');
        }
        $json = (string)$row['definition_json'];
        if (!hash_equals((string)$row['definition_sha256'], hash('sha256', $json))) {
            throw new \DomainException('Draft integrity mismatch.');
        }
        $definition = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($definition)) {
            throw new \DomainException('Draft definition invalid.');
        }
        OfficeWorkflowBlueprintValidator::validate($definition);
        return [
            'version_id' => (int)$row['id'],
            'version_number' => (int)$row['version_number'],
            'definition_sha256' => (string)$row['definition_sha256'],
            'definition' => $definition,
        ];
    }
}
