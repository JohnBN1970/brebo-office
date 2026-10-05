<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SupplierInvoiceAnalyticsRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseSupplierInvoiceAnalyticsRepository implements SupplierInvoiceAnalyticsRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function supplierAggregates(): array {
    if (!$this->exists()) {
      return [];
    }

    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addField('i', 'supplier_name');
    $query->addExpression('COUNT(*)', 'invoice_count');
    $query->addExpression('COUNT(DISTINCT project_nid)', 'project_count');
    $query->addExpression('COALESCE(SUM(gross_amount), 0)', 'turnover');
    $query->addExpression("SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END)", 'matched_count');
    $query->addExpression("SUM(CASE WHEN match_status <> 'matched' THEN 1 ELSE 0 END)", 'exception_count');
    $query->groupBy('supplier_name');

    return array_map(static fn(array $row): array => [
      'supplier_name' => (string) $row['supplier_name'],
      'invoice_count' => (int) $row['invoice_count'],
      'project_count' => (int) $row['project_count'],
      'turnover' => (float) $row['turnover'],
      'matched_count' => (int) $row['matched_count'],
      'exception_count' => (int) $row['exception_count'],
    ], $query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function exceptionPatterns(int $minimumProjects = 2): array {
    if (!$this->exists()) {
      return [];
    }

    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addField('i', 'supplier_name');
    $query->addExpression('COUNT(*)', 'invoice_count');
    $query->addExpression('COUNT(DISTINCT project_nid)', 'affected_projects');
    $query->addExpression('COALESCE(SUM(gross_amount), 0)', 'invoice_amount');
    $query->condition('match_status', 'matched', '<>');
    $query->groupBy('supplier_name');
    $query->having('COUNT(DISTINCT project_nid) >= :minimum', [':minimum' => max(1, $minimumProjects)]);

    return array_map(static fn(array $row): array => [
      'supplier_name' => (string) $row['supplier_name'],
      'invoice_count' => (int) $row['invoice_count'],
      'affected_projects' => (int) $row['affected_projects'],
      'invoice_amount' => (float) $row['invoice_amount'],
    ], $query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  private function exists(): bool {
    return $this->database->schema()->tableExists('brebo_supplier_invoice');
  }

}
