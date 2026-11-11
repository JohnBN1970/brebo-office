<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Workflow/OfficeWorkflowBlueprintValidator.php';
require_once __DIR__ . '/../src/Workflow/OfficeWorkflowBlueprintCompiler.php';
use Brebo\Office\Workflow\OfficeWorkflowBlueprintCompiler as C;
$blueprint = [
 'schema_version'=>1,'key'=>'brebo.commercial',
 'nodes'=>[['id'=>'crm'],['id'=>'accepted'],['id'=>'project']],
 'edges'=>[
  ['id'=>'register_acceptance','from'=>'crm','to'=>'accepted',
   'condition'=>'acceptance.verified','on_failure'=>'block','actions'=>[]],
  ['id'=>'start_project','from'=>'accepted','to'=>'project',
   'condition'=>'acceptance.verified','on_failure'=>'retry',
   'actions'=>['project.create','notification.create']],
 ],
];
$compiled=C::compile($blueprint);
$step=C::transition($compiled,'accepted','start_project');
if ($step['to']!=='project' || $step['actions']!==['project.create','notification.create']) {
 throw new RuntimeException('Compiled transition mismatch');
}
try { C::transition($compiled,'crm','start_project'); throw new RuntimeException('Invalid transition allowed'); }
catch (DomainException $expected) {}
echo "Workflow compiler contract OK\n";
