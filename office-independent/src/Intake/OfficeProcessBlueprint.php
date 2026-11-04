<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Immutable blueprint contract. The current steps are hardcoded; a future
 * graphical editor may publish versioned definitions with this same shape.
 * This class does not execute steps or grant permissions.
 */
final class OfficeProcessBlueprint
{
    public static function forDiscipline(string $discipline): array
    {
        $discipline = OfficeDisciplineCatalog::validate([$discipline])[0];
        $shared = ['aanvraag', 'gebouw_koppelen', 'offerte', 'opdracht'];
        $routes = [
            OfficeDisciplineCatalog::DELIVERY => ['specificatie', 'inkoop', 'levering', 'controle'],
            OfficeDisciplineCatalog::INSTALLATION => ['opname', 'werkvoorbereiding', 'planning', 'montage', 'oplevering'],
            OfficeDisciplineCatalog::DELIVERY_INSTALLATION => ['opname', 'inkoop', 'werkvoorbereiding', 'planning', 'montage', 'oplevering'],
            OfficeDisciplineCatalog::CONSTRUCTION => ['werkbegroting', 'werkvoorbereiding', 'planning', 'uitvoering', 'oplevering'],
            OfficeDisciplineCatalog::ADVICE => ['vraagstelling', 'onderzoek', 'analyse', 'adviesrapport', 'overdracht'],
            OfficeDisciplineCatalog::EXPERTISE => ['vraagstelling', 'inspectie', 'bevindingen', 'expertiserapport', 'overdracht'],
        ];
        $steps = array_merge($shared, $routes[$discipline], ['evaluatie']);
        return [
            'key' => 'brebo.' . $discipline,
            'version' => 1,
            'discipline' => $discipline,
            'steps' => array_map(
                static fn (string $step): array => ['id' => $step, 'type' => 'task'],
                $steps
            ),
        ];
    }

    public static function validateDefinition(array $definition): void
    {
        if (!isset($definition['key'], $definition['version'], $definition['discipline'], $definition['steps'])
            || !is_string($definition['key']) || $definition['key'] === ''
            || !is_int($definition['version']) || $definition['version'] < 1
            || !is_string($definition['discipline']) || !is_array($definition['steps'])
            || $definition['steps'] === []) {
            throw new \InvalidArgumentException('invalid_process_blueprint');
        }
        OfficeDisciplineCatalog::validate([$definition['discipline']]);
        $seen = [];
        foreach ($definition['steps'] as $step) {
            if (!is_array($step) || !isset($step['id'], $step['type'])
                || !is_string($step['id']) || !preg_match('/^[a-z][a-z0-9_]*$/D', $step['id'])
                || !is_string($step['type']) || !in_array($step['type'], ['task', 'decision', 'approval', 'notification'], true)
                || isset($seen[$step['id']])) {
                throw new \InvalidArgumentException('invalid_process_step');
            }
            $seen[$step['id']] = true;
        }
    }
}
