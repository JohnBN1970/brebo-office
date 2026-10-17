<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Reconcile normalized supplier timesheet rows against approved OnSite minutes
 * per person, project and calendar day. Importers must normalize Excel first.
 */
final class OnSiteWeeklyTimesheetControl {
  /**
   * @param list<array{person_id:int,project_id:int,date:string,minutes:int}> $approved
   * @param list<array{person_id:int,project_id:int,date:string,minutes:int}> $supplier
   * @return array{status:string,approved_minutes:int,supplier_minutes:int,rows:list<array{person_id:int,project_id:int,date:string,approved_minutes:int,supplier_minutes:int,difference_minutes:int}>}
   */
  public function compare(array $approved, array $supplier): array {
    $indexed = [];
    foreach ([['rows'=>$approved,'field'=>'approved_minutes'],['rows'=>$supplier,'field'=>'supplier_minutes']] as $source) {
      foreach ($source['rows'] as $row) {
        $person = $row['person_id'] ?? null;
        $project = $row['project_id'] ?? null;
        $date = $row['date'] ?? null;
        $minutes = $row['minutes'] ?? null;
        if (!is_int($person) || $person < 1 || !is_int($project) || $project < 1
          || !is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
          || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))
          || !is_int($minutes) || $minutes < 0 || $minutes > 1440) {
          throw new \InvalidArgumentException('Ongeldige dagregel in urenstaat.');
        }
        $key = $person . ':' . $project . ':' . $date;
        $indexed[$key] ??= ['person_id'=>$person,'project_id'=>$project,'date'=>$date,
          'approved_minutes'=>0,'supplier_minutes'=>0];
        $indexed[$key][$source['field']] += $minutes;
      }
    }
    $rows = array_values($indexed);
    usort($rows, static fn(array $a,array $b): int =>
      [$a['date'],$a['project_id'],$a['person_id']] <=> [$b['date'],$b['project_id'],$b['person_id']]);
    $approvedTotal = 0;
    $supplierTotal = 0;
    $hasDifferences = false;
    foreach ($rows as &$row) {
      $row['difference_minutes'] = $row['supplier_minutes'] - $row['approved_minutes'];
      $approvedTotal += $row['approved_minutes'];
      $supplierTotal += $row['supplier_minutes'];
      $hasDifferences = $hasDifferences || $row['difference_minutes'] !== 0;
    }
    unset($row);
    return ['status'=>$rows === [] ? 'no_evidence' : ($hasDifferences ? 'requires_review' : 'hours_match'),
      'approved_minutes'=>$approvedTotal,'supplier_minutes'=>$supplierTotal,'rows'=>$rows];
  }
}
