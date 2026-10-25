<?php
declare(strict_types=1);

namespace Brebo\Office\Workflow;

/**
 * Compatibility layer for legacy CRM opportunity stages.
 * Never silently rewrites persisted stage values.
 */
final class OfficeOpportunityStageMap {
  private const ALIASES = [
    'prospect' => 'nieuw',
    'new' => 'nieuw',
    'lead' => 'nieuw',
    'qualified' => 'in_behandeling',
    'proposal' => 'offerte',
    'won' => 'opdracht',
  ];

  public static function workflowStage(string $storedStage): ?string {
    $stage = strtolower(trim($storedStage));
    if ($stage === '') return null;
    $stages = OfficeOpportunityWorkflow::stages();
    if (isset($stages[$stage])) return $stage;
    return self::ALIASES[$stage] ?? null;
  }

  public static function label(string $storedStage): string {
    $mapped = self::workflowStage($storedStage);
    if ($mapped === null) return $storedStage;
    return OfficeOpportunityWorkflow::stages()[$mapped]['label'];
  }

  /** @return list<string> */
  public static function nextStages(string $storedStage): array {
    $mapped = self::workflowStage($storedStage);
    return $mapped === null ? [] : OfficeOpportunityWorkflow::nextStages($mapped);
  }
}
