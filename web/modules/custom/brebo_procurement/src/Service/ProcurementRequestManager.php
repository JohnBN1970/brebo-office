<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Creates traceable supplier requests from BREBO object-domain demand. */
final class ProcurementRequestManager {
  public function __construct(private readonly Connection $database) {}

  /** @param array<int,array<string,mixed>> $lines */
  public function create(array $lines, ?int $projectId, array $supplier, ?string $requestedDeliveryDate, ?string $deliveryLocation, ?string $note, AccountInterface $account): int {
    if (!$lines) throw new \InvalidArgumentException('Een leveranciersaanvraag moet minimaal één regel bevatten.');
    $now = time();
    $requestNumber = 'RFQ-' . gmdate('Ymd-His', $now) . '-' . random_int(100, 999);
    $transaction = $this->database->startTransaction();
    try {
      $requestId = (int) $this->database->insert('brebo_procurement_request')->fields([
        'request_number' => $requestNumber,
        'project_nid' => $projectId,
        'supplier_ref' => trim((string) ($supplier['ref'] ?? '')) ?: NULL,
        'supplier_name' => trim((string) ($supplier['name'] ?? '')) ?: NULL,
        'supplier_email' => trim((string) ($supplier['email'] ?? '')) ?: NULL,
        'status' => 'draft',
        'requested_delivery_date' => $requestedDeliveryDate ?: NULL,
        'delivery_location' => trim((string) $deliveryLocation) ?: NULL,
        'note' => trim((string) $note) ?: NULL,
        'created' => $now,
        'created_by' => (int) $account->id(),
        'changed' => $now,
      ])->execute();
      foreach ($lines as $line) {
        foreach (['source_domain','source_reference','description','quantity','unit'] as $required) {
          if (!isset($line[$required]) || (string) $line[$required] === '') throw new \InvalidArgumentException('Inkoopregel mist verplichte bron- of hoeveelheidgegevens.');
        }
        $this->database->insert('brebo_procurement_request_line')->fields([
          'request_id' => $requestId,
          'source_domain' => (string) $line['source_domain'],
          'source_reference' => (string) $line['source_reference'],
          'description' => (string) $line['description'],
          'quantity' => (float) $line['quantity'],
          'unit' => (string) $line['unit'],
          'specification_json' => json_encode($line['specification'] ?? [], JSON_THROW_ON_ERROR),
          'calculation_id' => !empty($line['calculation_id']) ? (int) $line['calculation_id'] : NULL,
          'calculation_version' => !empty($line['calculation_version']) ? (string) $line['calculation_version'] : NULL,
          'calculation_line_id' => !empty($line['calculation_line_id']) ? (int) $line['calculation_line_id'] : NULL,
        ])->execute();
      }
      return $requestId;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }
}
