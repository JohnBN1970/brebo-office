<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;

/** Pure preflight checks; database publishing requires separate transactional code. */
final class OfficeWorkflowPublicationPolicy
{
    public static function prepare(array $blueprint, string $status, bool $authorized, int $tenantId): array
    {
        if (!$authorized || $tenantId < 1 || $status !== 'draft') {
            throw new \DomainException('Workflow publication not authorized or draft.');
        }
        OfficeWorkflowBlueprintValidator::validate($blueprint);
        $compiled = OfficeWorkflowBlueprintCompiler::compile($blueprint);
        $json = json_encode($blueprint, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return [
            'tenant_id' => $tenantId,
            'definition_json' => $json,
            'definition_sha256' => hash('sha256', $json),
            'compiled' => $compiled,
        ];
    }
}
