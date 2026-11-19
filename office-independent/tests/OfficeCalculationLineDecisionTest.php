<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalculationLineApproval.php';
use Brebo\Office\Calculation\OfficeCalculationLineApproval as Approval;
$f=str_repeat('a',64);
$line=['author_id'=>12,'status'=>'draft'];
$line=Approval::submit($line,12,$f);
if($line['status']!==Approval::SUBMITTED)throw new RuntimeException('submit failed');
$rejected=Approval::reject($line,17,$f,'Hoeveelheid controleren',12,2,3);
if($rejected['status']!==Approval::REJECTED || $rejected['rejection_note']!=='Hoeveelheid controleren')throw new RuntimeException('red decision failed');
foreach([
  static fn()=>Approval::reject($line,17,$f,'',12,2,3),
  static fn()=>Approval::reject($line,12,$f,'Reden',12,2,3),
  static fn()=>Approval::reject($line,17,$f,'Reden',12,3,3),
  static fn()=>Approval::reject($line,17,str_repeat('b',64),'Reden',12,2,3),
  static fn()=>Approval::review($line,17,$f,'',12,2,2),
] as $invalid){
  try{$invalid();throw new RuntimeException('unauthorized transition accepted');}
  catch(DomainException $expected){}
}
$approved=Approval::review($line,17,$f,'',12,2,3);
if($approved['status']!==Approval::REVIEWED)throw new RuntimeException('green decision failed');
$edited=Approval::invalidateAfterEdit($approved,str_repeat('b',64));
if($edited['status']!==Approval::DRAFT)throw new RuntimeException('revision invalidation failed');
echo "Submitted, red/green, independent hierarchy, stale revision: OK\n";
