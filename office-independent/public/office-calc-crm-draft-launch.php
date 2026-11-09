<?php
declare(strict_types=1);

// Independent Office CRM-first Calc entrypoint. No Drupal bootstrap and no
// project_id. This is a draft launch boundary, NOT the Calc line editor.
// Deployment must mount this endpoint explicitly and install reviewed schema.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Content-Type: application/json; charset=utf-8');
$deny=static function(int $status): never {
    http_response_code($status);
    echo '{"ok":false}';
    exit;
};
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
    || getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'
    || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
    || getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')!=='enabled') $deny(404);
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') {
    header('Allow: POST');$deny(405);
}
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!is_string($host)
    ||!hash_equals($allowed,$host)
    ||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') $deny(403);
$origin=$_SERVER['HTTP_ORIGIN']??null;
if (!is_string($origin)||!hash_equals('https://'.$host,$origin)) $deny(403);
$type=$_SERVER['CONTENT_TYPE']??'';
$length=$_SERVER['CONTENT_LENGTH']??'';
if (!is_string($type)||!preg_match('/^application\/x-www-form-urlencoded(?:\s*;\s*charset=UTF-8)?$/iD',$type)
    ||!is_string($length)||!ctype_digit($length)
    ||(int)$length<1||(int)$length>2048) $deny(415);
$draft=filter_var($_POST['draft_id']??null,FILTER_VALIDATE_INT);
$csrf=$_POST['csrf']??null;
$expected=$_COOKIE['office_project_csrf']??null;
if (!is_int($draft)||$draft<1||!is_string($csrf)||!is_string($expected)) $deny(403);
try {
    require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
    require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmLaunchIssuer.php';
    $token=$_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')]??null;
    if (!is_string($token)) $deny(403);
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',
        getenv('OFFICE_INTAKE_DB_USER')?:'',
        getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    // Until the CRM authorization service is wired, limit to the creator
    // and require current active CRM edit rights in the issuer.
    $owner=static function(PDO $db,int $actor,int $opportunity): bool {
        $q=$db->prepare('SELECT 1 FROM office_calc_crm_drafts WHERE opportunity_id=? AND created_by=? LIMIT 1');
        $q->execute([$opportunity,$actor]);
        return $q->fetchColumn()!==false;
    };
    $reference=(new \Brebo\Office\Calculation\OfficeCalcCrmLaunchIssuer($db))
        ->issue($token,$csrf,$expected,$draft,time(),$owner);
    if ($reference===null) $deny(403);
    echo json_encode(['ok'=>true,'reference'=>$reference,'draft_id'=>$draft],JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $deny(503);
}
