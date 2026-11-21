<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Approval/OfficeApprovalPolicy.php';
require_once dirname(__DIR__) . '/src/Approval/OfficeBlueprintApprovalModeRepository.php';
use Brebo\Office\Approval\OfficeApprovalPolicy;
use Brebo\Office\Approval\OfficeBlueprintApprovalModeRepository;
function check(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
function rejects(callable $action,string $message):void {
    try {$action();}catch(DomainException $e){return;}
    throw new RuntimeException($message);
}
check(OfficeApprovalPolicy::resolve('project_start',null)==='four_eyes','default must be four eyes');
check(OfficeApprovalPolicy::resolve('project_start','single')==='single','single requires explicit value');
rejects(fn()=>OfficeApprovalPolicy::resolve('project_start','invalid'),'invalid mode accepted');
rejects(fn()=>OfficeApprovalPolicy::assertReviewers('four_eyes','7','7'),'same reviewer accepted');
OfficeApprovalPolicy::assertReviewers('four_eyes','7','8');
OfficeApprovalPolicy::assertReviewers('single','7',null);
// Permission checks must run before any SQL. An inert PDO is sufficient.
$db=new PDO('sqlite::memory:');
$repo=new OfficeBlueprintApprovalModeRepository($db);
rejects(fn()=>$repo->set('project_start','startdossier_release','single',7,false,true),'production mutation allowed');
rejects(fn()=>$repo->set('project_start','startdossier_release','single',7,true,false),'non-admin mutation allowed');
rejects(fn()=>$repo->set('project_start','startdossier_release','single',0,true,true),'anonymous mutation allowed');
rejects(fn()=>$repo->set('project_start','startdossier_release','invalid',7,true,true),'invalid mode accepted');
rejects(fn()=>$repo->set('project_start','bad step','single',7,true,true),'invalid step accepted');
echo "Office blueprint approval policy: OK\n";
