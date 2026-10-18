<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetLocalTime.php';
use Brebo\Office\Inzet\OfficeInzetLocalTime as LocalTime;
if (LocalTime::toUtc('2026-10-19T09:00') !== '2026-10-19 07:00:00') throw new RuntimeException('Summer offset incorrect');
if (LocalTime::toUtc('2026-12-19T09:00') !== '2026-12-19 08:00:00') throw new RuntimeException('Winter offset incorrect');
foreach (['2026-03-29T02:30','2026-10-25T02:30','2026-02-30T09:00'] as $invalid) {
  $rejected = false;
  try { LocalTime::toUtc($invalid); } catch (InvalidArgumentException $e) { $rejected = true; }
  if (!$rejected) throw new RuntimeException('Invalid local time accepted: '.$invalid);
}
echo "Office local shift time validation OK\n";
