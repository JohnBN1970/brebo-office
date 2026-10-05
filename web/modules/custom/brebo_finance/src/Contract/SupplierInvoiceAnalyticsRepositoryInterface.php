<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Read boundary for supplier-invoice analytics owned by Finance. */
interface SupplierInvoiceAnalyticsRepositoryInterface {

  /**
   * @return array<int,array{
   *   supplier_name:string,invoice_count:int,project_count:int,turnover:float,
   *   matched_count:int,exception_count:int
   * }>
   */
  public function supplierAggregates(): array;

  /**
   * @return array<int,array{
   *   supplier_name:string,invoice_count:int,affected_projects:int,invoice_amount:float
   * }>
   */
  public function exceptionPatterns(int $minimumProjects = 2): array;

}
