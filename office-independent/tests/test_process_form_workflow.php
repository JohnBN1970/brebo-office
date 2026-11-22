<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormWorkflow.php';
use Brebo\Office\Process\OfficeProcessFormWorkflow as W;
$cases=[
 ['concept','submit','ingediend'],
 ['ingediend','start_review','controle'],
 ['controle','approve','akkoord'],
 ['controle','reject','afgekeurd'],
 ['controle','return','concept'],
 ['afgekeurd','reopen','concept'],
 ['akkoord','reopen','concept'],
];
foreach ($cases as [$from,$action,$to]) {
    if (W::target($from,$action,'reviewer','submitter',true,true,'getoetst')!==$to) throw new RuntimeException('Bad transition');
}
$blocked=[
 fn()=>W::target('concept','approve','reviewer','submitter',true,true,'reason'),
 fn()=>W::target('controle','approve','same','same',true,true,'reason'),
 fn()=>W::target('controle','approve','reviewer','',true,true,'reason'),
 fn()=>W::target('controle','approve','reviewer','submitter',true,false,'reason'),
 fn()=>W::target('controle','approve','reviewer','submitter',true,true,''),
];
foreach ($blocked as $index=>$case) {
    try {$case();throw new RuntimeException('Expected rejection '.$index);}
    catch (InvalidArgumentException|RuntimeException $e) {
        if (str_starts_with($e->getMessage(),'Expected rejection')) throw $e;
    }
}
if (W::target('controle','approve','same','same',false,true,'reason')!=='akkoord') throw new RuntimeException('Optional four-eyes not configurable');
echo "Process form workflow policy OK\n";
