<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteWeekDateResolver.php';

use Brebo\Office\Inzet\OnSiteWeekDateResolver;

$resolver = new OnSiteWeekDateResolver();
if ($resolver->weekNumber('wk35') !== 35 || $resolver->weekNumber('week 02') !== 2
  || $resolver->weekNumber('Blad6') !== null) throw new RuntimeException('Week labels incorrectly recognized.');
if ($resolver->date(2026,1,'monday') !== '2025-12-29'
  || $resolver->date(2026,35,'friday') !== '2026-08-28') {
  throw new RuntimeException('ISO year boundary or weekday mapping incorrect.');
}
try {
  $resolver->date(2026,53,'monday');
  throw new RuntimeException('Nonexistent week accepted.');
} catch (InvalidArgumentException $expected) {}
$result = $resolver->attachDates([
  ['sheet'=>'wk35','cell'=>'D2','weekday'=>'monday','minutes'=>480],
  ['sheet'=>'Blad6','cell'=>'C3','weekday'=>'monday','minutes'=>480],
],2026);
if (count($result['rows']) !== 1 || count($result['issues']) !== 1
  || $result['rows'][0]['date'] !== '2026-08-24') {
  throw new RuntimeException('Unresolved sheets must be reviewed.');
}
echo "OnSite week date resolver OK\n";
