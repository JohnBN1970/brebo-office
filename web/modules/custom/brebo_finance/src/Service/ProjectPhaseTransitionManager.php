<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\ProjectLifecycleGatewayInterface;
use Drupal\brebo_office_core\Project\ProjectLifecycle;
use Drupal\brebo_finance\Contract\ProjectTransitionFinanceRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Central authority for financially protected BREBO project phase transitions.
 */
final class ProjectPhaseTransitionManager {

  private const TRANSITIONS = [
    'start_execution' => [
      'gate' => 'execution_start',
      'target' => ProjectLifecycle::EXECUTION,
    ],
    'close_project' => [
      'gate' => 'project_closeout',
      'target' => ProjectLifecycle::CLOSED,
    ],
  ];

  public function __construct(
    private readonly ProjectTransitionFinanceRepositoryInterface $finance,
    private readonly ProjectLifecycleGatewayInterface $projects,
    private readonly FinancialPhaseGateManager $phaseGateManager,
  ) {}

  /**
   * Performs a protected project transition after deterministic gate checks.
   */
  public function transition(int $projectNid, string $transition, string $reason, int $actorUid): void {
    if (!isset(self::TRANSITIONS[$transition])) {
      throw new InvalidArgumentException('Unsupported BREBO project phase transition.');
    }
    if ($actorUid <= 0 || trim($reason) === '') {
      throw new InvalidArgumentException('A human actor and transition reason are required.');
    }

    $definition = self::TRANSITIONS[$transition];
    $target = $definition['target'];
    if ($this->projects->status($projectNid) === $target) {
      return;
    }

    $this->phaseGateManager->requireRelease($projectNid, $definition['gate']);

    if ($transition === 'close_project') {
      $this->assertCloseoutReady($projectNid);
    }

    $result = $this->projects->transition($projectNid, $target);
    if (!$result['changed']) {
      return;
    }
    $before = $result['before'];

    $this->finance->appendAudit([
      'project_nid' => $projectNid,
      'entity_type' => 'project_phase',
      'entity_id' => $projectNid,
      'action' => $transition,
      'before_hash' => hash('sha256', $before),
      'after_hash' => hash('sha256', $target),
      'payload' => json_encode([
        'from' => $before,
        'to' => $target,
        'gate' => $definition['gate'],
        'ai_override_allowed' => FALSE,
      ], JSON_THROW_ON_ERROR),
      'reason' => trim($reason),
      'created' => time(),
      'created_by' => $actorUid,
    ]);
  }

  /**
   * Prevents closeout while material financial administration remains open.
   */
  private function assertCloseoutReady(int $projectNid): void {
    $open = $this->finance->openAdministration($projectNid);
    if ($open['commitments'] > 0) {
      throw new RuntimeException('Project closeout is blocked while purchase commitments remain open.');
    }
    if ($open['purchase_invoices'] > 0) {
      throw new RuntimeException('Project closeout is blocked while purchase invoices remain unpaid or unresolved.');
    }
    if ($open['billing_instalments'] > 0) {
      throw new RuntimeException('Project closeout is blocked while billing instalments remain open.');
    }
  }

}
