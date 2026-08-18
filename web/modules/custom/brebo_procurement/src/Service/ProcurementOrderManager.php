<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;

/** Converts selected offers to orders and records controlled receipts. */
final class ProcurementOrderManager {
  public function __construct(
    private readonly Connection $database,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  public function createFromSelectedOffer(int $requestId, AccountInterface $account): int {
    $request = $this->database->select('brebo_procurement_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc();
    if (!$request || $request['status'] !== 'offer_selected') throw new \RuntimeException('Selecteer eerst een technisch akkoord bevonden leveranciersofferte.');
    $offer = $this->database->select('brebo_procurement_offer', 'o')->fields('o')->condition('request_id', $requestId)->condition('status', 'selected')->execute()->fetchAssoc();
    if (!$offer) throw new \RuntimeException('Geselecteerde offerte ontbreekt.');
    $existing = $this->database->select('brebo_procurement_order', 'o')->fields('o', ['id'])->condition('request_id', $requestId)->execute()->fetchField();
    if ($existing) return (int) $existing;
    $now = time();
    $orderId = (int) $this->database->insert('brebo_procurement_order')->fields([
      'order_number' => sprintf('PO-%s-%05d', date('Y'), $requestId), 'request_id' => $requestId,
      'supplier_name' => $offer['supplier_name'], 'supplier_ref' => $offer['supplier_ref'], 'status' => 'ordered',
      'ordered_at' => $now, 'expected_delivery_date' => $offer['delivery_date'] ?: $request['requested_delivery_date'],
      'created' => $now, 'created_by' => (int) $account->id(),
    ])->execute();
    $this->database->update('brebo_procurement_request')->fields(['status'=>'ordered','changed'=>$now])->condition('id',$requestId)->execute();
    $this->notifySources($requestId, 'ordered', ['order_id'=>$orderId,'expected_delivery_date'=>$offer['delivery_date'] ?: $request['requested_delivery_date']]);
    return $orderId;
  }

  /** @param array<string,mixed> $inspection */
  public function receive(int $orderId, array $inspection, AccountInterface $account): void {
    $order = $this->database->select('brebo_procurement_order','o')->fields('o')->condition('id',$orderId)->execute()->fetchAssoc();
    if (!$order || $order['status'] !== 'ordered') throw new \RuntimeException('Alleen openstaande bestellingen kunnen worden ontvangen.');
    $checks = ['quantity_ok','dimensions_ok','specification_ok','damage_free','checksum_ok'];
    $accepted = TRUE;
    foreach ($checks as $check) $accepted = $accepted && !empty($inspection[$check]);
    $now = time();
    $this->database->insert('brebo_procurement_receipt')->fields([
      'order_id'=>$orderId,'received_at'=>$now,'received_by'=>(int)$account->id(),
      'quantity_ok'=>!empty($inspection['quantity_ok'])?1:0,'dimensions_ok'=>!empty($inspection['dimensions_ok'])?1:0,
      'specification_ok'=>!empty($inspection['specification_ok'])?1:0,'damage_free'=>!empty($inspection['damage_free'])?1:0,
      'checksum_ok'=>!empty($inspection['checksum_ok'])?1:0,'accepted'=>$accepted?1:0,
      'note'=>trim((string)($inspection['note']??''))?:NULL,
    ])->execute();
    $status = $accepted ? 'received' : 'receipt_exception';
    $this->database->update('brebo_procurement_order')->fields(['status'=>$status,'received_at'=>$now])->condition('id',$orderId)->execute();
    $this->notifySources((int)$order['request_id'], $status, ['order_id'=>$orderId,'inspection'=>$inspection]);
  }

  /** @param array<string,mixed> $context */
  private function notifySources(int $requestId, string $status, array $context = []): void {
    $lines = $this->database->select('brebo_procurement_request_line','l')->fields('l',['source_domain','source_reference'])->condition('request_id',$requestId)->execute();
    foreach ($lines as $line) {
      $this->moduleHandler->invokeAll('brebo_procurement_source_status_changed', [[
        'source_domain'=>(string)$line->source_domain,
        'source_reference'=>(string)$line->source_reference,
        'request_id'=>$requestId,
        'status'=>$status,
        'context'=>$context,
      ]]);
    }
  }
}
