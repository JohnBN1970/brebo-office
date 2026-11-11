<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Workflow/OfficeWorkflowBlueprintValidator.php';
use Brebo\Office\Workflow\OfficeWorkflowBlueprintValidator as V;
$valid = [
    'schema_version'=>1,'key'=>'brebo.commercial',
    'nodes'=>[['id'=>'crm'],['id'=>'accepted'],['id'=>'project']],
    'edges'=>[
        ['id'=>'accept','from'=>'crm','to'=>'accepted','condition'=>'acceptance.verified',
         'on_failure'=>'block','actions'=>[]],
        ['id'=>'start','from'=>'accepted','to'=>'project','condition'=>'acceptance.verified',
         'on_failure'=>'retry','actions'=>['project.create','notification.create']],
    ],
];
V::validate($valid);
function invalid(array $value): void {
    try { V::validate($value); } catch (DomainException $expected) { return; }
    throw new RuntimeException('Unsafe blueprint accepted');
}
$bad=$valid; $bad['edges'][1]['condition']='always'; invalid($bad);
$bad=$valid; $bad['edges'][1]['actions']=['php.eval']; invalid($bad);
$bad=$valid; $bad['edges'][1]['to']='missing'; invalid($bad);
$bad=$valid; $bad['nodes'][]=['id'=>'crm']; invalid($bad);
echo "Workflow blueprint contract OK\n";
