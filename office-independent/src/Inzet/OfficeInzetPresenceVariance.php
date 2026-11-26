<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Read-only comparison. Caller must supply authorized projects and verified native personnel identities. */
final class OfficeInzetPresenceVariance {
  public static function compare(array $shifts, array $sessions, array $verifiedPeople, array $availableProjects, \DateTimeImmutable $monday, \DateTimeImmutable $asOf): array {
    $monday = $monday->setTime(0,0);
    $utc = new \DateTimeZone('UTC');
    $rows = [];
    $people = array_fill_keys($verifiedPeople, true);
    $projects = array_fill_keys($availableProjects, true);
    foreach ([['planned',$shifts,'starts_at','ends_at'],['actual',$sessions,'started_at','ended_at']] as [$kind,$items,$fromKey,$toKey]) {
      foreach ($items as $item) {
        $person = (int)($item['person_id'] ?? 0);
        $project = (int)($item['project_id'] ?? 0);
        if ($person <= 0 || $project <= 0 || !isset($people[$person],$projects[$project])) continue;
        if ($kind === 'planned' && !in_array($item['status'] ?? '', ['planned','confirmed'],true)) continue;
        if (empty($item[$fromKey]) || empty($item[$toKey])) continue;
        try {
          $from = (new \DateTimeImmutable($item[$fromKey],$utc))->getTimestamp();
          $to = (new \DateTimeImmutable($item[$toKey],$utc))->getTimestamp();
        } catch (\Throwable $e) { continue; }
        for ($i=0;$i<7;$i++) {
          $day = $monday->modify("+$i days");
          $start = max($from,$day->getTimestamp());
          $end = min($to,$day->modify('+1 day')->getTimestamp(),$asOf->getTimestamp());
          if ($end <= $start) continue;
          $key = $person.':'.$project.':'.$day->format('Y-m-d');
          $rows[$key] ??= ['person_id'=>$person,'project_id'=>$project,'date'=>$day->format('Y-m-d'),'planned'=>[],'actual'=>[]];
          $rows[$key][$kind][] = [$start,$end];
        }
      }
    }
    ksort($rows);
    foreach ($rows as &$row) {
      $p = self::merge($row['planned']);
      $a = self::merge($row['actual']);
      $planned = self::seconds($p);
      $actual = self::seconds($a);
      $matched = 0;
      foreach ($p as [$ps,$pe]) foreach ($a as [$as,$ae]) $matched += max(0,min($pe,$ae)-max($ps,$as));
      $row['planned_seconds']=$planned;
      $row['actual_seconds']=$actual;
      $row['variance_seconds']=$actual-$planned;
      $row['missing_seconds']=$planned-$matched;
      $row['unplanned_seconds']=$actual-$matched;
      $row['planned']=$p;
      $row['actual']=$a;
    }
    unset($row);
    return array_values($rows);
  }
  private static function merge(array $intervals): array {
    sort($intervals);
    $out=[];
    foreach ($intervals as [$start,$end]) {
      $last=count($out)-1;
      if ($last>=0 && $start <= $out[$last][1]) $out[$last][1]=max($end,$out[$last][1]);
      else $out[]=[$start,$end];
    }
    return $out;
  }
  private static function seconds(array $intervals): int {
    return array_sum(array_map(static fn(array $i): int=>$i[1]-$i[0],$intervals));
  }
}
