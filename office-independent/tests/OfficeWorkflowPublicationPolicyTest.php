<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Workflow/OfficeWorkflowBlueprintValidator.php';
require_once __DIR__ . '/../src/Workflow/OfficeWorkflowBlueprintCompiler.php';
require_once __DIR__ . '/../src/Workflow/OfficeWorkflowPublicationPolicy.php';
use Brebo\Office\Workflow\OfficeWorkflowPublicationPolicy as P;
$b=['schema_version'=>1,'key'=>'brebo.test','nodes'=>[['id'=>'intake'],['id'=>'done']],
'edges'=>[['id'=>'complete','from'=>'intake','to'=>'done','condition'=>'always',
'on_failure'=>'block','actions'=>[]]]];
$p=P::prepare($b,'draft',true,1);
if (hash('sha256',$p['definition_json'])!==$p['definition_sha256']) throw new RuntimeException('Hash mismatch');
foreach ([['published',true,1],['draft',false,1],['draft',true,0]] as [$s,$a,$t]) {
    try { P::prepare($b,$s,$a,$t); throw new RuntimeException('Unsafe publish allowed'); }
    catch (DomainException $expected) {}
}
echo "Workflow publication preflight OK\n";
