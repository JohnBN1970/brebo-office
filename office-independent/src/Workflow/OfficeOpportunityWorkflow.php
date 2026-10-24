<?php
declare(strict_types=1);

namespace Brebo\Office\Workflow;

/**
 * Read-only opportunity workflow definition shared by list, Kanban and future views.
 * This policy does not persist transitions or perform side effects.
 */
final class OfficeOpportunityWorkflow {
  private const STAGES = [
    'nieuw' => ['label' => 'Nieuw', 'next' => ['in_behandeling'], 'checks' => ['source_recorded'], 'actions' => ['assign_owner', 'suggest_building']],
    'in_behandeling' => ['label' => 'In behandeling', 'next' => ['calculatie', 'offerte'], 'checks' => ['owner_assigned', 'scope_reviewed'], 'actions' => ['review_documents', 'link_building_if_known']],
    'calculatie' => ['label' => 'Calculatie', 'next' => ['offerte', 'in_behandeling'], 'checks' => ['calculation_approved'], 'actions' => ['prepare_calculation']],
    'offerte' => ['label' => 'Offerte', 'next' => ['opvolging', 'opdracht', 'in_behandeling'], 'checks' => ['offer_recorded'], 'actions' => ['schedule_follow_up']],
    'opvolging' => ['label' => 'Opvolging', 'next' => ['opdracht', 'offerte'], 'checks' => ['follow_up_recorded'], 'actions' => ['notify_owner']],
    'opdracht' => ['label' => 'Opdracht', 'next' => [], 'checks' => ['acceptance_confirmed'], 'actions' => ['propose_project_link']],
  ];

  /** @return array<string,array{label:string,next:list<string>,checks:list<string>,actions:list<string>}> */
  public static function stages(): array {
    return self::STAGES;
  }

  /** @return list<string> */
  public static function nextStages(string $current): array {
    return self::STAGES[$current]['next'] ?? [];
  }

  /** @param list<string> $completedChecks */
  public static function canTransition(string $from, string $to, array $completedChecks): bool {
    if (!in_array($to, self::nextStages($from), true)) {
      return false;
    }
    foreach (self::STAGES[$to]['checks'] as $required) {
      if (!in_array($required, $completedChecks, true)) {
        return false;
      }
    }
    return true;
  }
}
