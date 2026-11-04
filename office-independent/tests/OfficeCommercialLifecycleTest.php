<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Demo/OfficeCommercialLifecycle.php';
use Brebo\Office\Demo\OfficeCommercialLifecycle as L;
assert(L::mayCreateProject('inspection_commission','accepted')===false);
assert(L::mayCreateProject('execution_contract','draft')===false);
if(L::mayCreateProject('inspection_commission','accepted')) throw new RuntimeException('Inspection created execution project');
if(L::mayCreateProject('execution_contract','draft')) throw new RuntimeException('Draft created project');
if(!L::mayCreateProject('execution_contract','accepted')) throw new RuntimeException('Accepted contract rejected');
if(L::measurementContext('initial',false)!=='crm_opportunity') throw new RuntimeException('Initial measurement wrong');
if(L::measurementContext('final',true)!=='execution_project') throw new RuntimeException('Final measurement wrong');
if(L::inspectionContext('accepted')!=='inspection_commission') throw new RuntimeException('Inspection wrong');
try {L::measurementContext('final',false);throw new RuntimeException('Unaccepted final measurement');} catch(InvalidArgumentException $e) {}
try {L::inspectionContext('draft');throw new RuntimeException('Unaccepted inspection');} catch(InvalidArgumentException $e) {}
echo "COMMERCIAL_LIFECYCLE_OK\n";
