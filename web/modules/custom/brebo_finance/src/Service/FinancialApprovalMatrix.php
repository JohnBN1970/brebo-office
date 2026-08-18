<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Session\AccountInterface;
use InvalidArgumentException;

/** Deterministic procuration and escalation policy for gate exceptions. */
final class FinancialApprovalMatrix {

  private const BASE_PERMISSIONS = [
    'procurement_release' => 'approve brebo procurement gate exception',
    'execution_start' => 'approve brebo execution gate exception',
    'billing_release' => 'approve brebo billing gate exception',
    'payment_release' => 'approve brebo payment gate exception',
    'project_closeout' => 'approve brebo closeout gate exception',
  ];

  /**
   * Initial policy bands in EUR. Values are intentionally centralized so the
   * procuration schedule can later move to configuration without controller changes.
   */
  private const ESCALATION_BANDS = [
    ['up_to' => '25000.00', 'permission' => NULL, 'level' => 'gate_approver'],
    ['up_to' => '100000.00', 'permission' => 'approve brebo finance elevated', 'level' => 'finance_controller'],
    ['up_to' => NULL, 'permission' => 'approve brebo finance executive', 'level' => 'executive'],
  ];

  public function authorize(AccountInterface $account, string $gate, string $exposureAmount): array {
    if (!isset(self::BASE_PERMISSIONS[$gate])) throw new InvalidArgumentException('Unknown financial phase gate.');
    if (!$this->validMoney($exposureAmount)) throw new InvalidArgumentException('Exposure amount must be a non-negative monetary amount with max two decimals.');

    $basePermission = self::BASE_PERMISSIONS[$gate];
    if (!$account->hasPermission($basePermission)) {
      return ['authorized' => FALSE, 'level' => 'gate_approver', 'required_permissions' => [$basePermission], 'exposure_amount' => $exposureAmount];
    }

    $band = $this->bandFor($exposureAmount);
    $required = [$basePermission];
    if ($band['permission'] !== NULL) $required[] = $band['permission'];
    foreach ($required as $permission) {
      if (!$account->hasPermission($permission)) return ['authorized' => FALSE, 'level' => $band['level'], 'required_permissions' => $required, 'exposure_amount' => $exposureAmount];
    }

    return ['authorized' => TRUE, 'level' => $band['level'], 'required_permissions' => $required, 'exposure_amount' => $exposureAmount];
  }

  private function bandFor(string $amount): array {
    foreach (self::ESCALATION_BANDS as $band) {
      if ($band['up_to'] === NULL || bccomp($amount, $band['up_to'], 2) <= 0) return $band;
    }
    return self::ESCALATION_BANDS[array_key_last(self::ESCALATION_BANDS)];
  }

  private function validMoney(string $amount): bool {
    return preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/', $amount) === 1;
  }
}
