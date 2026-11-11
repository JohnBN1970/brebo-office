<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmGrantValidationService.php';
use Brebo\Office\Calculation\OfficeCalcCrmGrantValidationService;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('SQLite required');
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach ([
    'CREATE TABLE office_calc_signed_request_ids(request_id TEXT PRIMARY KEY,expires_at TEXT)',
    'CREATE TABLE office_operator_sessions(token_hash TEXT PRIMARY KEY,operator_id TEXT,expires_at TEXT,revoked_at TEXT)',
    'CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER,can_edit_crm INTEGER)',
    'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)',
    'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,created_by INTEGER,project_id INTEGER)',
    'CREATE TABLE office_calc_crm_launch_grants(reference TEXT PRIMARY KEY,session_hash TEXT,actor_id INTEGER,draft_id INTEGER,expires_at TEXT,consumed_at TEXT,revoked_at TEXT)'
] as $sql) $db->exec($sql);
$now=time();
$at=gmdate('Y-m-d H:i:s',$now+3600);
$ref=str_repeat('b',64);
$secret=str_repeat('s',40);
$sessionHash=str_repeat('a',64);
$db->exec('INSERT INTO office_operator_accounts VALUES(7,1,1)');
$db->prepare('INSERT INTO office_operator_sessions VALUES(?,?,?,NULL)')->execute([$sessionHash,'7',$at]);
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open')");
$db->exec('INSERT INTO office_calc_crm_drafts VALUES(9,11,4,7,NULL)');
$db->prepare('INSERT INTO office_calc_crm_launch_grants VALUES(?,?,?,?,?,NULL,NULL)')->execute([$ref,$sessionHash,7,9,$at]);

$validate=new OfficeCalcCrmGrantValidationService($db);
$body=json_encode(['reference'=>$ref,'actor_id'=>'7','project_id'=>null,'calculation_id'=>9],JSON_THROW_ON_ERROR);
$counter=0;
$headers=function(string $raw) use ($secret,$now,&$counter): array {
    ++$counter;
    $id=sprintf('00000000-0000-4000-8000-%012x',$counter);
    $timestamp=(string)$now;
    $canonical=implode("\n",['POST','/api/workbench/v1/calculation-grants/validate',hash('sha256',$raw),$timestamp,$id]);
    return [
        'x-brebo-timestamp'=>$timestamp,
        'x-brebo-request-id'=>$id,
        'x-brebo-signature'=>'v1='.hash_hmac('sha256',$canonical,$secret),
    ];
};
$first=$headers($body);
if (!$validate->validate($body,$first,$secret,$now)) throw new RuntimeException('Valid CRM grant rejected');
if ($validate->validate($body,$first,$secret,$now)) throw new RuntimeException('Signed request replay accepted');

$db->exec("UPDATE office_operator_sessions SET revoked_at='2024-01-01 00:00:00'");
if ($validate->validate($body,$headers($body),$secret,$now)) throw new RuntimeException('Revoked Office session accepted');
$db->exec('UPDATE office_operator_sessions SET revoked_at=NULL');
$db->exec("UPDATE office_operator_accounts SET can_edit_crm=0");
if ($validate->validate($body,$headers($body),$secret,$now)) throw new RuntimeException('Removed CRM permission accepted');
$db->exec('UPDATE office_operator_accounts SET can_edit_crm=1');
$db->exec("UPDATE office_crm_opportunities SET revision=5");
if ($validate->validate($body,$headers($body),$secret,$now)) throw new RuntimeException('Stale CRM revision accepted');
$db->exec("UPDATE office_crm_opportunities SET revision=4");
$db->exec('UPDATE office_calc_crm_drafts SET project_id=12');
if ($validate->validate($body,$headers($body),$secret,$now)) throw new RuntimeException('Transferred draft accepted');
$db->exec('UPDATE office_calc_crm_drafts SET project_id=NULL');
$db->exec("UPDATE office_calc_crm_launch_grants SET expires_at='2024-01-01 00:00:00'");
if ($validate->validate($body,$headers($body),$secret,$now)) throw new RuntimeException('Expired CRM grant accepted');
$db->prepare('UPDATE office_calc_crm_launch_grants SET expires_at=?')->execute([$at]);
$bad=json_encode(['reference'=>$ref,'actor_id'=>'7','project_id'=>1,'calculation_id'=>9],JSON_THROW_ON_ERROR);
if ($validate->validate($bad,$headers($bad),$secret,$now)) throw new RuntimeException('Project-bound identity accepted as CRM draft');
$bad=json_encode(['reference'=>$ref,'actor_id'=>'8','project_id'=>null,'calculation_id'=>9],JSON_THROW_ON_ERROR);
if ($validate->validate($bad,$headers($bad),$secret,$now)) throw new RuntimeException('Foreign Office operator accepted');
echo "OFFICE_CALC_CRM_CONTINUOUS_GRANT=PASS\n";
