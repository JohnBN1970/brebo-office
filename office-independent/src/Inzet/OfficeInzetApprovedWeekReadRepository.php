<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Read-only approved OnSite minutes; project access is checked again in SQL. */
final class OfficeInzetApprovedWeekReadRepository {
  public function __construct(private PDO $db) {}

  /** @param list<int> $authorizedProjectIds
   * @return array<int,array<string,int>> person ID => local date => approved minutes
   */
  public function listWeek(string $operatorId, array $authorizedProjectIds, int $year, int $week): array {
    if ($operatorId === '' || strlen($operatorId) > 128) throw new \InvalidArgumentException('Invalid operator');
    $projects = array_values(array_unique(array_filter($authorizedProjectIds, static fn($id) => is_int($id) && $id > 0)));
    if ($projects === []) return [];
    $local = new DateTimeZone('Europe/Brussels');
    $start = (new DateTimeImmutable('now', $local))->setISODate($year, $week, 1)->setTime(0, 0);
    if ($year < 2020 || $year > 2100 || (int)$start->format('o') !== $year || (int)$start->format('W') !== $week) {
      throw new \InvalidArgumentException('Invalid ISO week');
    }
    $end = $start->modify('+7 days')->format('Y-m-d');
    $params = [':operator'=>$operatorId, ':start'=>$start->format('Y-m-d'), ':end'=>$end];
    $holders = [];
    foreach ($projects as $i=>$id) { $key=':project'.$i; $holders[]=$key; $params[$key]=$id; }
    $sql = "SELECT d.person_id,d.work_date,SUM(d.reviewed_seconds) AS approved_seconds
      FROM office_onsite_hours_decisions d
      WHERE d.status='approved' AND d.work_date>=:start AND d.work_date<:end
        AND d.project_id IN (".implode(',', $holders).")
        AND EXISTS (
          SELECT 1 FROM office_project_memberships m
          JOIN office_operator_accounts a ON a.id=m.operator_id
          WHERE m.operator_id=:operator AND m.project_id=d.project_id
            AND m.permission IN ('read','manage') AND a.active=1
        )
      GROUP BY d.person_id,d.work_date";
    $stmt=$this->db->prepare($sql);
    $stmt->execute($params);
    $result=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $personId=(int)$row['person_id'];
      if ($personId < 1) continue;
      $result[$personId][(string)$row['work_date']]=(int)round(((int)$row['approved_seconds'])/60);
    }
    return $result;
  }
}
