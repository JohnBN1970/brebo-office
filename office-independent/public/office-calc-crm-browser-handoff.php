<?php
declare(strict_types=1);

// Office CRM-first browser handoff to an explicitly isolated Calc Node origin.
// GET does not mint any credentials. A real user click creates a short-lived
// signed grant via Office's authorized POST, then a top-level cross-origin POST.
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$deny=static function(int $status): never {
    http_response_code($status);
    exit('CRM-calculatie is nog niet beschikbaar.');
};
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
    || getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'
    || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
    || getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')!=='enabled'
    || getenv('OFFICE_CALC_CRM_V2_LAUNCH_ENABLED')!=='enabled'
    || getenv('OFFICE_CALC_CRM_V2_GRANT_VALIDATION_ENABLED')!=='enabled'
    || getenv('OFFICE_CALC_CRM_V2_GRANT_VALIDATION_VERIFIED')!=='enabled') $deny(404);
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') $deny(405);
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host)||!is_string($allowed)||$allowed===''
    || !hash_equals($allowed,$host)
    || empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') $deny(403);
$origin=getenv('OFFICE_CALC_CRM_APP_ORIGIN');
if (!is_string($origin)||!filter_var($origin,FILTER_VALIDATE_URL)) $deny(503);
$parts=parse_url($origin);
if (!is_array($parts) || ($parts['scheme']??null)!=='https'
    || !isset($parts['host'])
    || isset($parts['user']) || isset($parts['pass'])
    || isset($parts['query']) || isset($parts['fragment'])
    || isset($parts['port'])
    || !in_array($parts['path']??'', ['', '/'],true)
    || rtrim($origin,'/')!=='https://'.$parts['host']) $deny(503);
$origin=rtrim($origin,'/');
$draft=filter_var($_GET['draft_id']??null,FILTER_VALIDATE_INT);
if (!is_int($draft)||$draft<1) $deny(400);
try {
    require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteCrmOperatorContext.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteOfficeCrmSessionRepository.php';
    require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftAccess.php';
    $token=$_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')]??null;
    if (!is_string($token)) $deny(403);
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',
        getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db))
        ->findActive($token,time());
    if ($operator===null || !$operator->canEditCrm || !ctype_digit($operator->operatorId)) $deny(403);
    $actor=(int)$operator->operatorId;
    $rights=static function(PDO $db,int $actorId,int $opportunity): bool {
        $q=$db->prepare('SELECT 1 FROM office_calc_crm_drafts WHERE opportunity_id=? AND created_by=? LIMIT 1');
        $q->execute([$opportunity,$actorId]);
        return $q->fetchColumn()!==false;
    };
    if (!(new \Brebo\Office\Calculation\OfficeCalcCrmDraftAccess($db))
        ->canManage($actor,$draft,$rights)) $deny(403);
    $csrf=$_COOKIE['office_project_csrf']??null;
    if (!is_string($csrf)||preg_match('/^[a-f0-9]{64}$/D',$csrf)!==1) {
        $csrf=bin2hex(random_bytes(32));
        if (!setcookie('office_project_csrf',$csrf,[
            'expires'=>0,'path'=>'/office-test','secure'=>true,
            'httponly'=>true,'samesite'=>'Strict'
        ])) $deny(503);
    }
} catch (Throwable) {
    $deny(503);
}
$nonce=base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'unsafe-inline'; connect-src 'self'; form-action {$origin}; base-uri 'none'; frame-ancestors 'none'");
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CRM-calculatie openen – BREBO Office</title>
<style>body{font:16px system-ui,sans-serif;color:#19314d;background:#f5f7fb;max-width:660px;margin:8vh auto;padding:24px}main{background:white;border-radius:16px;padding:32px;box-shadow:0 12px 36px #1b36621c}button{background:#4849a8;color:white;border:0;border-radius:9px;padding:14px 22px;font:700 16px system-ui;cursor:pointer}button:disabled{opacity:.6}#status{margin-top:20px;color:#65758b}</style></head>
<body><main><small>BREBO OFFICE / CRM / CALCULATIE</small><h1>Conceptcalculatie openen</h1>
<p>De calculatie wordt geopend in de beveiligde Calc-werkbank. Er is nog geen project gestart.</p>
<button type="button" id="open">Open Calc-werkbank</button><p id="status" role="status" aria-live="polite"></p></main>
<script nonce="<?= htmlspecialchars($nonce,ENT_QUOTES,'UTF-8') ?>">
(() => {
    const button=document.getElementById('open');
    const status=document.getElementById('status');
    button.addEventListener('click',async () => {
        button.disabled=true;
        status.textContent='Office controleert toegang…';
        try {
            const body=new URLSearchParams({
                draft_id:<?= json_encode((string)$draft,JSON_THROW_ON_ERROR) ?>,
                csrf:<?= json_encode($csrf,JSON_THROW_ON_ERROR) ?>
            });
            const response=await fetch('/office-test/calc/crm/launch',{
                method:'POST',credentials:'same-origin',cache:'no-store',redirect:'error',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:body.toString()
            });
            if (!response.ok) throw new Error('Office heeft de toegang geweigerd.');
            const result=await response.json();
            if (result.ok!==true || typeof result.token!=='string') throw new Error('Er is geen geldig Calc-starttoken.');
            const form=document.createElement('form');
            form.method='POST';
            form.action=<?= json_encode($origin.'/api/launch/consume',JSON_THROW_ON_ERROR) ?>;
            form.enctype='application/x-www-form-urlencoded';
            const input=document.createElement('input');
            input.type='hidden';
            input.name='token';
            input.value=result.token;
            form.appendChild(input);
            document.body.appendChild(form);
            status.textContent='Calc-werkbank openen…';
            form.submit();
        } catch (error) {
            status.textContent=error instanceof Error?error.message:'Calc kon niet worden geopend.';
            button.disabled=false;
        }
    });
})();
</script></body></html>
