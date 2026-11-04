<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use InvalidArgumentException;
final class OfficeCommercialLifecycle {
 public static function mayCreateProject(string $type,string $status):bool {
  return $type==='execution_contract' && $status==='accepted';
 }
 public static function measurementContext(string $phase,bool $accepted):string {
  if($phase==='initial') return 'crm_opportunity';
  if($phase==='final' && $accepted) return 'execution_project';
  throw new InvalidArgumentException('Measurement not permitted');
 }
 public static function inspectionContext(string $status):string {
  if($status!=='accepted') throw new InvalidArgumentException('Inspection commission not accepted');
  return 'inspection_commission';
 }
}
