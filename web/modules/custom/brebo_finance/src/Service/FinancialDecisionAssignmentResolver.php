<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\FinancialActorGatewayInterface;

/** Resolves which active BREBO users can decide a financial gate exception. */
final class FinancialDecisionAssignmentResolver {

  private const LEVEL_PERMISSIONS = [
    'gate_approver' => NULL,
    'finance_controller' => 'approve brebo finance elevated',
    'executive' => 'approve brebo finance executive',
    'executive_unresolved_exposure' => 'approve brebo finance executive',
  ];

  private const GATE_PERMISSIONS = [
    'procurement_release' => 'approve brebo procurement gate exception',
    'execution_start' => 'approve brebo execution gate exception',
    'billing_release' => 'approve brebo billing gate exception',
    'payment_release' => 'approve brebo payment gate exception',
    'project_closeout' => 'approve brebo closeout gate exception',
  ];

  public function __construct(private readonly FinancialActorGatewayInterface $actors) {}

  /**
   * @return array<string, mixed>
   */
  public function resolve(string $gate, string $level, int $requesterUid): array {
    $gatePermission = self::GATE_PERMISSIONS[$gate] ?? NULL;
    $levelPermission = self::LEVEL_PERMISSIONS[$level] ?? NULL;
    if ($gatePermission === NULL) {
      return [
        'assigned' => FALSE,
        'gate' => $gate,
        'level' => $level,
        'candidates' => [],
        'reason' => 'unknown_gate',
      ];
    }

    $candidates = [];
    foreach ($this->actors->activeActors() as $actor) {
      $uid = (int) $actor['uid'];
      if ($uid === $requesterUid) {
        continue;
      }
      if (!$this->actors->hasPermission($uid, 'approve brebo finance') || !$this->actors->hasPermission($uid, $gatePermission)) {
        continue;
      }
      if ($levelPermission !== NULL && !$this->actors->hasPermission($uid, $levelPermission)) {
        continue;
      }
      $candidates[] = [
        'uid' => $uid,
        'display_name' => (string) $actor['display_name'],
        'mail' => (string) $actor['mail'],
        'roles' => array_values($actor['roles']),
        'required_gate_permission' => $gatePermission,
        'required_level_permission' => $levelPermission,
      ];
    }

    return [
      'assigned' => $candidates !== [],
      'gate' => $gate,
      'level' => $level,
      'candidate_count' => count($candidates),
      'primary_candidate' => $candidates[0] ?? NULL,
      'candidates' => $candidates,
      'escalation_required' => $candidates === [],
      'reason' => $candidates === [] ? 'no_authorized_active_user' : NULL,
    ];
  }

  /**
   * Convenience wrapper for a live account decision preview.
   *
   * @return array<string, mixed>
   */
  public function canAct(int $actorUid, string $gate, string $level): array {
    $gatePermission = self::GATE_PERMISSIONS[$gate] ?? NULL;
    $levelPermission = self::LEVEL_PERMISSIONS[$level] ?? NULL;
    $authorized = $gatePermission !== NULL
      && $this->actors->hasPermission($actorUid, 'approve brebo finance')
      && $this->actors->hasPermission($actorUid, $gatePermission)
      && ($levelPermission === NULL || $this->actors->hasPermission($actorUid, $levelPermission));

    return [
      'authorized' => $authorized,
      'gate' => $gate,
      'level' => $level,
      'uid' => $actorUid,
      'required_gate_permission' => $gatePermission,
      'required_level_permission' => $levelPermission,
    ];
  }

}
