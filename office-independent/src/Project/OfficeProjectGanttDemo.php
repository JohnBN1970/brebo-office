<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

/** Deterministic, read-only demonstration; never persists or mixes with live projects. */
final class OfficeProjectGanttDemo {
  public static function projects(): array {
    $monday = (new \DateTimeImmutable('monday this week'))->setTime(0,0);
    $date = static fn(int $offset): string => $monday->modify(($offset >= 0 ? '+' : '').$offset.' days')->format('Y-m-d');
    return [
      ['id'=>900001,'code'=>'DEMO-001','title'=>'Demo · 63 appartementen Den Haag','status'=>'kritiek','building_count'=>1,'planned_start'=>$date(-14),'planned_end'=>$date(42),'critical_note'=>'Kozijnlevering blokkeert montage; leverdatum bevestigen'],
      ['id'=>900002,'code'=>'DEMO-002','title'=>'Demo · Gevelrenovatie Amsterdam','status'=>'op schema','building_count'=>2,'planned_start'=>$date(-7),'planned_end'=>$date(49),'critical_note'=>''],
      ['id'=>900003,'code'=>'DEMO-003','title'=>'Demo · Voegwerk Amsterdam','status'=>'in wacht','building_count'=>1,'planned_start'=>$date(7),'planned_end'=>$date(35),'critical_note'=>'Start wacht op steigerinspectie'],
      ['id'=>900004,'code'=>'DEMO-004','title'=>'Demo · Oplevering','status'=>'gereed','building_count'=>1,'planned_start'=>$date(-28),'planned_end'=>$date(0),'critical_note'=>''],
    ];
  }
}
