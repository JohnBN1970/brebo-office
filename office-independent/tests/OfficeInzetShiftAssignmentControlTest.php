<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftAssignmentControl.php';

use Brebo\Office\Inzet\OfficeInzetShiftAssignmentControl as Control;

$base = ['person_id'=>8,'starts_at'=>'2026-10-19 08:00:00','ends_at'=>'2026-10-19 16:00:00'];
$existing = [['id'=>10,'person_id'=>8,'starts_at'=>'2026-10-19 15:00:00','ends_at'=>'2026-10-19 17:00:00','status'=>'confirmed']];
$failed = false;
try { Control::assertNoOverlap($base,$existing); } catch (InvalidArgumentException $e) { $failed = true; }
if (!$failed) throw new RuntimeException('Overlapping shift accepted');
Control::assertNoOverlap($base,$existing,10);
Control::assertNoOverlap($base,[['id'=>11,'person_id'=>8,'starts_at'=>'2026-10-19 16:00:00','ends_at'=>'2026-10-19 18:00:00','status'=>'confirmed']]);
Control::assertNoOverlap($base,[['id'=>12,'person_id'=>8,'starts_at'=>'2026-10-19 15:00:00','ends_at'=>'2026-10-19 17:00:00','status'=>'cancelled']]);
Control::assertNoOverlap($base,[['id'=>13,'person_id'=>9,'starts_at'=>'2026-10-19 15:00:00','ends_at'=>'2026-10-19 17:00:00','status'=>'confirmed']]);
echo "Office shift overlap validation OK\n";
