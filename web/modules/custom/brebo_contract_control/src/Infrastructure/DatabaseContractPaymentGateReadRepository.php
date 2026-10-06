<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ContractPaymentGateReadRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseContractPaymentGateReadRepository implements ContractPaymentGateReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function award(int $awardId): ?array {
    $row = $this->database
      ->select('brebo_procurement_award', 'a')
      ->fields('a')
      ->condition('id', $awardId)
      ->execute()
      ->fetchAssoc();

    return $row === FALSE ? NULL : $row;
  }

  public function invoice(int $invoiceId): ?array {
    $row = $this->database
      ->select('brebo_supplier_invoice', 'i')
      ->fields('i')
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();

    return $row === FALSE ? NULL : $row;
  }

}
