<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Infrastructure;

use Drupal\brebo_project_cockpit\Contract\ProjectInvoiceRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for project invoice administration. */
final class DatabaseProjectInvoiceRepository implements ProjectInvoiceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function draftStorageAvailable(): bool {
    $schema = $this->database->schema();
    return $schema->tableExists('brebo_finance_sales_invoice_draft')
      && $schema->tableExists('brebo_finance_sales_invoice_draft_line');
  }

  public function releaseStorageAvailable(): bool {
    return $this->draftStorageAvailable() && $this->database->schema()->tableExists('brebo_finance_sales_invoice_outbox');
  }

  public function projectContract(int $projectId): array {
    return $this->one('brebo_finance_project_contract', $projectId);
  }

  public function projectOverview(int $projectId): array {
    return [
      'contract' => $this->one('brebo_finance_project_contract', $projectId),
      'instalments' => $this->many('brebo_finance_billing_instalment', $projectId, 'planned_invoice_date'),
      'changes' => $this->many('brebo_finance_change_order', $projectId, 'changed', 'DESC'),
      'provisional_sums' => $this->many('brebo_finance_provisional_sum', $projectId, 'changed', 'DESC'),
      'drafts' => $this->many('brebo_finance_sales_invoice_draft', $projectId, 'changed', 'DESC'),
      'outbox' => $this->many('brebo_finance_sales_invoice_outbox', $projectId, 'created', 'DESC'),
      'sales_invoices' => $this->many('brebo_finance_sales_invoice', $projectId, 'invoice_date', 'DESC'),
    ];
  }

  public function purchaseInvoices(int $projectId): array {
    return $this->many('brebo_finance_purchase_invoice', $projectId, 'invoice_date', 'DESC');
  }

  public function salesInvoices(int $projectId): array {
    return $this->many('brebo_finance_sales_invoice', $projectId, 'invoice_date', 'DESC');
  }

  public function billableInstalments(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_billing_instalment')) return [];
    return array_values($this->database->select('brebo_finance_billing_instalment', 'i')->fields('i')
      ->condition('project_nid', $projectId)->condition('status', 'billable')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function invoiceableChanges(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_change_order')) return [];
    return array_values($this->database->select('brebo_finance_change_order', 'c')->fields('c')
      ->condition('project_nid', $projectId)->condition('status', ['client_approved', 'executed'], 'IN')
      ->isNull('invoice_ref')->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function invoiceableProvisionalSums(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_provisional_sum')) return [];
    return array_values($this->database->select('brebo_finance_provisional_sum', 'p')->fields('p')
      ->condition('project_nid', $projectId)
      ->where('approved_settlement_ex_vat <> invoiced_settlement_ex_vat')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function instalmentEvidencePayload(int $projectId, int $instalmentId): ?string {
    if (!$this->database->schema()->tableExists('brebo_finance_billing_instalment')) return NULL;
    $value = $this->database->select('brebo_finance_billing_instalment', 'i')->fields('i', ['evidence_payload'])
      ->condition('id', $instalmentId)->condition('project_nid', $projectId)->execute()->fetchField();
    return is_string($value) ? $value : NULL;
  }

  public function sourceLines(int $projectId, string $type, int $id): array {
    if ($type === 'instalment') {
      $row = $this->database->select('brebo_finance_billing_instalment', 'i')->fields('i')
        ->condition('id', $id)->condition('project_nid', $projectId)->condition('status', 'billable')->execute()->fetchAssoc();
      if ($row === FALSE) return [];
      if ($this->database->schema()->tableExists('brebo_finance_billing_instalment_line')) {
        $stored = $this->database->select('brebo_finance_billing_instalment_line', 'l')->fields('l')
          ->condition('instalment_id', $id)->orderBy('line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC);
        if ($stored !== []) return array_map(static fn(array $line): array => ['source_type' => 'instalment', 'source_id' => $id, 'description' => $line['description'], 'amount_ex_vat' => $line['amount_ex_vat'], 'vat_code' => $line['vat_code']], $stored);
      }
      return [['source_type' => 'instalment', 'source_id' => $id, 'description' => $row['description'], 'amount_ex_vat' => $row['amount_ex_vat'], 'vat_code' => $row['vat_code']]];
    }
    if ($type === 'change') {
      $row = $this->database->select('brebo_finance_change_order', 'c')->fields('c')
        ->condition('id', $id)->condition('project_nid', $projectId)->condition('status', ['client_approved', 'executed'], 'IN')->execute()->fetchAssoc();
      if ($row === FALSE) return [];
      $amount = (float) $row['sales_amount_ex_vat'];
      if (($row['change_type'] ?? '') === 'omission') $amount *= -1;
      return [['source_type' => 'change', 'source_id' => $id, 'description' => $row['title'], 'amount_ex_vat' => number_format($amount, 4, '.', ''), 'vat_code' => $row['vat_code']]];
    }
    if ($type === 'provisional') {
      $row = $this->database->select('brebo_finance_provisional_sum', 'p')->fields('p')
        ->condition('id', $id)->condition('project_nid', $projectId)->execute()->fetchAssoc();
      if ($row === FALSE) return [];
      $amount = (float) $row['approved_settlement_ex_vat'] - (float) $row['invoiced_settlement_ex_vat'];
      if (abs($amount) < 0.0001) return [];
      return [['source_type' => 'provisional', 'source_id' => $id, 'description' => 'Stelpostverrekening ' . $row['title'], 'amount_ex_vat' => number_format($amount, 4, '.', ''), 'vat_code' => $row['vat_code']]];
    }
    return [];
  }

  public function createDraft(array $draftFields, array $lineFields): int {
    $transaction = $this->database->startTransaction();
    try {
      $draftId = (int) $this->database->insert('brebo_finance_sales_invoice_draft')->fields($draftFields)->execute();
      foreach ($lineFields as $fields) {
        $fields['draft_id'] = $draftId;
        $this->database->insert('brebo_finance_sales_invoice_draft_line')->fields($fields)->execute();
      }
      return $draftId;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  public function draftForProject(int $draftId, int $projectId, bool $draftOnly = FALSE): ?array {
    if (!$this->draftStorageAvailable()) return NULL;
    $query = $this->database->select('brebo_finance_sales_invoice_draft', 'd')->fields('d')
      ->condition('id', $draftId)->condition('project_nid', $projectId);
    if ($draftOnly) $query->condition('status', 'draft');
    $row = $query->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function draftLines(int $draftId): array {
    if (!$this->draftStorageAvailable()) return [];
    return array_values($this->database->select('brebo_finance_sales_invoice_draft_line', 'l')->fields('l')
      ->condition('draft_id', $draftId)->orderBy('line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function queueRelease(int $draftId, int $projectId, array $outboxFields, array $draftFields): int {
    $transaction = $this->database->startTransaction();
    try {
      $existing = $this->database->select('brebo_finance_sales_invoice_outbox', 'o')->fields('o', ['id'])
        ->condition('draft_id', $draftId)->execute()->fetchField();
      if ($existing !== FALSE) throw new \RuntimeException('This invoice draft already has a registration command.');
      $outboxId = (int) $this->database->insert('brebo_finance_sales_invoice_outbox')->fields($outboxFields)->execute();
      $this->database->update('brebo_finance_sales_invoice_draft')->fields($draftFields)
        ->condition('id', $draftId)->condition('project_nid', $projectId)->condition('status', 'draft')->execute();
      return $outboxId;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  public function instalmentVatRates(int $instalmentId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_billing_instalment_line')) return [];
    $query = $this->database->select('brebo_finance_billing_instalment_line', 'l');
    $query->addField('l', 'vat_rate');
    $query->condition('instalment_id', $instalmentId);
    $query->distinct();
    $rates = array_map('floatval', $query->execute()->fetchCol());
    sort($rates, SORT_NUMERIC);
    return $rates;
  }

  private function one(string $table, int $projectId): array {
    if (!$this->database->schema()->tableExists($table)) return [];
    $row = $this->database->select($table, 't')->fields('t')->condition('project_nid', $projectId)->execute()->fetchAssoc();
    return is_array($row) ? $row : [];
  }

  private function many(string $table, int $projectId, string $orderField, string $direction = 'ASC'): array {
    if (!$this->database->schema()->tableExists($table)) return [];
    $query = $this->database->select($table, 't')->fields('t')->condition('project_nid', $projectId);
    if ($this->database->schema()->fieldExists($table, $orderField)) $query->orderBy($orderField, $direction);
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

}
