<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;

/**
 * Declarative product contract for node/edge workflow definitions.
 * This validator NEVER evaluates executable code or performs actions.
 */
final class OfficeWorkflowBlueprintValidator
{
    private const ACTIONS = [
        'crm.update_status', 'crm.create_task', 'quotation.create_draft',
        'project.create', 'budget.transfer_accepted_calculation',
        'notification.create', 'document.create_draft',
    ];
    private const CONDITIONS = [
        'always', 'acceptance.verified', 'calculation.exists',
        'quotation.accepted', 'document.verified',
    ];
    private const FAILURES = ['block', 'retry', 'assign_task'];

    public static function validate(array $blueprint): void
    {
        if (($blueprint['schema_version'] ?? null) !== 1
            || !is_string($blueprint['key'] ?? null)
            || preg_match('/^[a-z][a-z0-9_.-]{2,63}$/D', $blueprint['key']) !== 1
            || !is_array($blueprint['nodes'] ?? null)
            || !is_array($blueprint['edges'] ?? null)
            || count($blueprint['nodes']) < 1 || count($blueprint['nodes']) > 100
            || count($blueprint['edges']) > 300) {
            throw new \DomainException('Invalid blueprint header or size.');
        }
        $nodes = [];
        foreach ($blueprint['nodes'] as $node) {
            if (!is_array($node) || !is_string($node['id'] ?? null)
                || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $node['id']) !== 1
                || isset($nodes[$node['id']])) {
                throw new \DomainException('Invalid or duplicate node.');
            }
            $nodes[$node['id']] = true;
        }
        $edges = [];
        foreach ($blueprint['edges'] as $edge) {
            if (!is_array($edge) || !is_string($edge['id'] ?? null)
                || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $edge['id']) !== 1
                || isset($edges[$edge['id']])
                || !isset($nodes[$edge['from'] ?? ''], $nodes[$edge['to'] ?? ''])
                || !in_array($edge['condition'] ?? null, self::CONDITIONS, true)
                || !in_array($edge['on_failure'] ?? null, self::FAILURES, true)
                || !is_array($edge['actions'] ?? null)
                || count($edge['actions']) > 20) {
                throw new \DomainException('Invalid edge.');
            }
            $edges[$edge['id']] = true;
            foreach ($edge['actions'] as $action) {
                if (!is_string($action) || !in_array($action, self::ACTIONS, true)) {
                    throw new \DomainException('Unknown or unsafe action.');
                }
            }
            if (in_array('project.create', $edge['actions'], true)
                && $edge['condition'] !== 'acceptance.verified') {
                throw new \DomainException('Project creation requires verified acceptance.');
            }
            if (in_array('budget.transfer_accepted_calculation', $edge['actions'], true)
                && $edge['condition'] !== 'acceptance.verified') {
                throw new \DomainException('Budget transfer requires verified acceptance.');
            }
        }
    }
}
