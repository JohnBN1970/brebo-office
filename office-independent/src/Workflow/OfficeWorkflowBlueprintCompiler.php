<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;

/**
 * Read-only compilation of a validated visual blueprint.
 * The caller must separately authorize the operator, case, event and actions.
 */
final class OfficeWorkflowBlueprintCompiler
{
    public static function compile(array $blueprint): array
    {
        OfficeWorkflowBlueprintValidator::validate($blueprint);
        $transitions = [];
        foreach ($blueprint['edges'] as $edge) {
            $from = $edge['from'];
            $event = $edge['id'];
            if (isset($transitions[$from][$event])) {
                throw new \DomainException('Ambiguous workflow transition.');
            }
            $transitions[$from][$event] = [
                'to' => $edge['to'],
                'condition' => $edge['condition'],
                'actions' => $edge['actions'],
                'on_failure' => $edge['on_failure'],
            ];
        }
        return [
            'key' => $blueprint['key'],
            'schema_version' => 1,
            'transitions' => $transitions,
        ];
    }

    public static function transition(array $compiled, string $state, string $event): array
    {
        $transition = $compiled['transitions'][$state][$event] ?? null;
        if (!is_array($transition)) {
            throw new \DomainException('Transition not defined.');
        }
        return $transition;
    }
}
