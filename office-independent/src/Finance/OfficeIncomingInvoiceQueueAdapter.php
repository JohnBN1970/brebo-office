<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Adapter for the existing authorized payables work-queue response.
 * Only invoices present in those queues are represented. This is NOT a
 * complete purchase ledger and must never be labelled as such.
 */
final class OfficeIncomingInvoiceQueueAdapter {
  public static function fromFinanceRead(array $data): array {
    $invoices = [];
    $seen = [];
    foreach (OfficeFinanceView::QUEUES as $queue => $label) {
      foreach (($data['queues'][$queue] ?? []) as $row) {
        if (!is_array($row)) continue;
        $id = filter_var($row['invoice_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($id === false || $id === null || isset($seen[$id])) continue;
        $seen[$id] = true;
        $invoices[] = [
          'id' => $id,
          'direction' => OfficeInvoiceDirection::INCOMING,
          'number' => (string)($row['invoice_number'] ?? ''),
          'relation' => (string)($row['supplier_name'] ?? ''),
          'project_label' => (string)($row['project_label'] ?? ''),
          'status' => $label,
          'amount_inc_vat' => (string)($row['amount_inc_vat'] ?? ''),
        ];
      }
    }
    return $invoices;
  }
}
