<?php
declare(strict_types=1);
use Brebo\Office\Inspection\OfficeInspectionSchema;
use Brebo\Office\Inspection\OfficeInspectionReportConfigurationSchema;
use Brebo\Office\Inspection\OfficeInspectionFindingSelectionSchema;
use Brebo\Office\Inspection\OfficeInspectionCreateAction;
use Brebo\Office\Inspection\OfficeInspectionFindingAction;
use Brebo\Office\Inspection\OfficeInspectionReportConfigurationRepository;
use Brebo\Office\Inspection\OfficeInspectionRepository;
foreach (['OfficeInspectionSchema','OfficeInspectionReportConfigurationSchema','OfficeInspectionFindingSelectionSchema','OfficeInspectionReportConfiguration','OfficeInspectionReportConfigurationRepository','OfficeInspectionRepository','OfficeInspectionCreateAction','OfficeInspectionFindingAction'] as $file) {
  require_once __DIR__.'/../src/Inspection/'.$file.'.php';
}
$dsn=getenv('OFFICE_INSPECTION_CI_DSN') ?: '';
if (!str_contains($dsn,'dbname=office_inspection_ci;')) throw new RuntimeException('isolated_ci_database_required');
$db=new PDO($dsn,getenv('OFFICE_INSPECTION_CI_USER') ?: '',getenv('OFFICE_INSPECTION_CI_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->exec('CREATE TABLE office_buildings (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, active TINYINT NOT NULL DEFAULT 1)');
$db->exec('CREATE TABLE office_project_buildings (building_id BIGINT UNSIGNED NOT NULL, project_id BIGINT UNSIGNED NOT NULL)');
$db->exec('CREATE TABLE office_operator_accounts (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, active TINYINT NOT NULL DEFAULT 1)');
$db->exec('CREATE TABLE office_project_memberships (project_id BIGINT UNSIGNED NOT NULL, operator_id BIGINT UNSIGNED NOT NULL, permission VARCHAR(20) NOT NULL)');
foreach (array_merge(OfficeInspectionSchema::statements(),OfficeInspectionReportConfigurationSchema::statements(),OfficeInspectionFindingSelectionSchema::statements()) as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_buildings VALUES (10,1)");
$db->exec("INSERT INTO office_project_buildings VALUES (10,20)");
$db->exec("INSERT INTO office_operator_accounts VALUES (30,1)");
$db->exec("INSERT INTO office_project_memberships VALUES (20,30,'manage')");
$create=new OfficeInspectionCreateAction($db);
$id=$create->create(['building_id'=>'10','project_id'=>'20','title'=>'CI bouwkundige inspectie','inspection_date'=>'2026-11-03','report_config'=>['report_type'=>'expertise','sections'=>['cover','findings']]],'30',static fn($b,$p)=>$b==='10' && $p==='20');
if (!ctype_digit($id)) throw new RuntimeException('inspection_create_failed');
$configRepo=new OfficeInspectionReportConfigurationRepository($db);
$config=$configRepo->forInspection($id);
if ($config===null || $config['sections']!==['cover','findings']) throw new RuntimeException('report_config_roundtrip_failed');
$configRepo->save($id,'30',$config);
$action=new OfficeInspectionFindingAction($db);
$fields=['code'=>'B01','observation'=>'Scheurvorming geconstateerd','assessment_framework'=>'Onderzoek vereist','technical_assessment'=>'Oorzaak nog onbekend','consequences'=>'Nader te beoordelen','remedy'=>'Nader onderzoek'];
$finding=$action->save($id,'30',$fields,true);
$fields['observation']='Scheurvorming opnieuw beoordeeld';
$action->save($id,'30',$fields,false,$finding);
$rows=(new OfficeInspectionRepository($db))->findings($id);
if (count($rows)!==1 || $rows[0]['observation']!==$fields['observation']) throw new RuntimeException('finding_roundtrip_failed');
$stmt=$db->prepare('SELECT include_in_report FROM office_inspection_report_findings WHERE inspection_id=? AND finding_id=?');
$stmt->execute([$id,$finding]);
if ((int)$stmt->fetchColumn()!==0) throw new RuntimeException('report_exclusion_failed');
$db->exec("UPDATE office_project_memberships SET permission='read' WHERE project_id=20 AND operator_id=30");
try {
  $action->save($id,'30',$fields,true,$finding);
  throw new RuntimeException('read_only_operator_was_allowed');
} catch (InvalidArgumentException $expected) {
  if ($expected->getMessage()!=='finding_access_denied') throw $expected;
}
echo "OfficeInspectionMySqlRoundTripTest OK\n";
