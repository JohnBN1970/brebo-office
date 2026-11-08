<?php
declare(strict_types=1);
/** Read-only functional acceptance: real active Office-test PDO, project ACL, service and Finance. */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_GOVERNANCE_FUNCTIONAL_AUDIT') !== 'read-only') {
    fwrite(STDERR, "GOVERNANCE_FUNCTIONAL_SCOPE_DENIED\n"); exit(2);
}
$base='/home/u213420663/domains/brebobv.nl/office_calc_test';
$c=require $base.'/config/config.php';
if (!is_array($c) || ($c['OFFICE_BASE_PATH']??null)!=='/office-test'
    || ($c['OFFICE_TEST_GOVERNANCE_ENABLED']??null)!=='enabled'
    || ($c['OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED']??null)!=='enabled'
    || ($c['OFFICE_CRM_SESSION_MODE']??null)!=='enabled'
    || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',(string)($c['OFFICE_INTAKE_PDO_DSN']??''))
    || ($c['OFFICE_INTAKE_DB_USER']??null)!=='u213420663_office_test') {
    fwrite(STDERR,"GOVERNANCE_FUNCTIONAL_CONFIG_DENIED\n");exit(2);
}
$release=realpath((string)($c['OFFICE_PRIVATE_ROOT']??''));
if (!is_string($release) || !str_starts_with($release,$base.'/release-')
    || !str_ends_with($release,'/private')) {
    fwrite(STDERR,"GOVERNANCE_FUNCTIONAL_RELEASE_DENIED\n");exit(2);
}
foreach (['src/Governance/bootstrap.php','src/Governance/OfficeProcurementOrderSource.php'] as $f) {
    if (!is_file($release.'/'.$f)) {fwrite(STDERR,"GOVERNANCE_FUNCTIONAL_DEPENDENCY_MISSING\n");exit(2);}
}
require_once $release.'/src/Governance/bootstrap.php';
require_once $release.'/src/Governance/OfficeProcurementOrderSource.php';
$db=new PDO($c['OFFICE_INTAKE_PDO_DSN'],$c['OFFICE_INTAKE_DB_USER'],$c['OFFICE_INTAKE_DB_PASSWORD'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if ($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test') exit(2);
$db->exec('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $q=$db->query("SELECT DISTINCT a.id FROM office_operator_accounts a
      JOIN office_project_memberships m ON m.operator_id=a.id AND m.permission='manage'
      JOIN office_module_permissions g ON g.operator_id=a.id AND g.module='settings' AND g.permission='manage'
      WHERE a.active=1");
    $actors=$q->fetchAll(PDO::FETCH_COLUMN);
    if (count($actors)!==1) throw new RuntimeException('unique_actor');
    $actor=(string)$actors[0];
    $store=new \Brebo\Office\Governance\Infrastructure\PdoOfficeGovernanceStore($db);
    $service=new \Brebo\Office\Governance\OfficeGovernanceService($store);
    $source=new \Brebo\Office\Governance\OfficeProcurementOrderSource($service);
    $contextTotals=[];
    foreach (['quality','risk','procurement'] as $module) {
        $contexts=$store->contexts($actor,$module);
        $contextTotals[$module]=count($contexts);
        if ($contexts===[]) throw new RuntimeException('no_context_'.$module);
        foreach ($contexts as $ctx) {
            $p=(int)$ctx['project_id'];$b=(int)$ctx['building_id'];
            if (!$store->allowed($actor,$module,'read',$p,$b)
                || !$store->allowed($actor,$module,'write',$p,$b)
                || $store->allowed($actor,$module,'approve',$p,$b)
                || $store->allowed($actor,$module,'award',$p,$b)
                || $store->allowed($actor,$module,'receive',$p,$b)) {
                throw new RuntimeException('acl_'.$module);
            }
            $rows=$service->read($actor,$module,$p,$b);
            $choices=$store->choices($actor,$module,$p,$b);
            if (!isset($choices['owner']) || !is_array($choices['owner']) || $choices['owner']===[]) {
                throw new RuntimeException('owner_choices_'.$module);
            }
            if (!is_array($rows)) throw new RuntimeException('list_'.$module);
        }
        echo 'GOVERNANCE_FUNCTIONAL_'.strtoupper($module).'_CONTEXTS='.count($contexts)."\n";
    }
    $projects=[];
    foreach ($store->contexts($actor,'procurement') as $ctx) {
        $projects[(int)$ctx['project_id']][(int)$ctx['building_id']]=true;
    }
    foreach ($projects as $projectId=>$buildingIds) {
        $result=$source->awardedProjectCommitment($actor,$projectId,array_keys($buildingIds));
        if (($result['project_id']??null)!==$projectId
            || ($result['currency']??null)!=='EUR'
            || !is_int($result['commitment_cents'])
            || $result['commitment_cents']<0
            || !is_int($result['awarded_order_count'])) {
            throw new RuntimeException('finance_aggregation');
        }
    }
    echo 'GOVERNANCE_FINANCE_PROJECTS_CHECKED='.count($projects)."\n";
    echo "GOVERNANCE_FUNCTIONAL_READONLY_PASS\n";
} catch (Throwable $e) {
    fwrite(STDERR,'GOVERNANCE_FUNCTIONAL_FAIL='.preg_replace('/[^a-zA-Z0-9_]/','_', $e instanceof RuntimeException?$e->getMessage():get_class($e))."\n");
    exit(1);
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
