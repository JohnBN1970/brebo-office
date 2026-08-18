<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

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

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

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

    $storage = $this->entityTypeManager->getStorage('user');
    $uids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uid', 0, '>')
      ->sort('uid', 'ASC')
      ->execute();

    $users = $storage->loadMultiple($uids);
    $candidates = [];
    foreach ($users as $user) {
      if ((int) $user->id() === $requesterUid) {
        continue;
      }
      if (!$user->hasPermission('approve brebo finance') || !$user->hasPermission($gatePermission)) {
        continue;
      }
      if ($levelPermission !== NULL && !$user->hasPermission($levelPermission)) {
        continue;
      }
      $candidates[] = [
        'uid' => (int) $user->id(),
        'display_name' => (string) $user->getDisplayName(),
        'mail' => (string) $user->getEmail(),
        'roles' => array_values($user->getRoles(TRUE)),
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
  public function canAct(AccountInterface $account, string $gate, string $level): array {
    $gatePermission = self::GATE_PERMISSIONS[$gate] ?? NULL;
    $levelPermission = self::LEVEL_PERMISSIONS[$level] ?? NULL;
    $authorized = $gatePermission !== NULL
      && $account->hasPermission('approve brebo finance')
      && $account->hasPermission($gatePermission)
      && ($levelPermission === NULL || $account->hasPermission($levelPermission));

    return [
      'authorized' => $authorized,
      'gate' => $gate,
      'level' => $level,
      'uid' => (int) $account->id(),
      'required_gate_permission' => $gatePermission,
      'required_level_permission' => $levelPermission,
    ];
  }

}
