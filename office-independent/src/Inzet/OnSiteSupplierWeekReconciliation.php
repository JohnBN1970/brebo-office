<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Review-first reconciliation. Unresolved supplier rows block a clean status,
 * even when mapped daily totals match OnSite.
 */
final class OnSiteSupplierWeekReconciliation {
  /**
   * @param list<array<string,mixed>> $importedRows
   * @param list<array{person_id:int,project_id:int,date:string,minutes:int}> $approvedOnSite
   * @param array<string,int> $confirmedPeople
   * @param array<string,int> $confirmedProjects
   * @return array<string,mixed>
   */
  public function reconcile(
    array $importedRows, int $supplierId, array $confirmedPeople,
    array $confirmedProjects, array $approvedOnSite
  ): array {
    $resolved = (new OnSiteSupplierIdentityResolver())->resolve(
      $importedRows, $supplierId, $confirmedPeople, $confirmedProjects
    );
    $supplier = array_map(static fn(array $row): array => [
      'person_id'=>$row['person_id'],'project_id'=>$row['project_id'],
      'date'=>$row['date'],'minutes'=>$row['minutes']
    ], $resolved['rows']);
    $compared = (new OnSiteWeeklyTimesheetControl())->compare($approvedOnSite, $supplier);
    $blocked = $resolved['issues'] !== [];
    return [
      'supplier_id'=>$supplierId,
      'status'=>$blocked ? 'requires_review' : $compared['status'],
      'matched_status'=>$compared['status'],
      'approved_minutes'=>$compared['approved_minutes'],
      'supplier_minutes'=>$compared['supplier_minutes'],
      'differences'=>$compared['rows'],
      'import_issues'=>$resolved['issues'],
      'review_required'=>$blocked || $compared['status'] !== 'hours_match',
      'invoice_eligible'=>false,
    ];
  }
}
