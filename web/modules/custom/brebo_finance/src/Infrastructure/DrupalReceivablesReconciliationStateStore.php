<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ReceivablesReconciliationStateStoreInterface;
use Drupal\Core\State\StateInterface;

/** Drupal State adapter for receivables reconciliation health. */
final class DrupalReceivablesReconciliationStateStore implements ReceivablesReconciliationStateStoreInterface {

  private const STATE_KEY = 'brebo_finance.receivables_reconciliation';

  public function __construct(private readonly StateInterface $state) {}

  public function get(): array {
    $value = $this->state->get(self::STATE_KEY, []);
    return is_array($value) ? $value : [];
  }

  public function set(array $value): void {
    $this->state->set(self::STATE_KEY, $value);
  }

}
