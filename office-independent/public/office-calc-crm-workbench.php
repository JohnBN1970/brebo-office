<?php
declare(strict_types=1);
// Independent CRM-first Calc workbench. Office-test only, not Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$deny=static function(int $status,string $reason='UNKNOWN'): never {
    if (!preg_match('/^[A-Z][A-Z0-9_]{1,30}$/D',$reason)) $reason='UNKNOWN';
    header('X-Brebo-Calc-Diagnostic: '.$reason);
    http_response_code($status);
    exit('Calculatie niet beschikbaar (code '.$reason.').');
};
$collaborationEnabled=getenv('OFFICE_CALC_DRAFT_COLLABORATION_ENABLED')==='enabled'&&getenv('OFFICE_CALC_DRAFT_COLLABORATION_SCHEMA_VERIFIED')==='enabled';
$workbenchUrl=$collaborationEnabled?'/office-test/calculatie/samenwerken':'/office-test/calculatie';
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
    || getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'
    || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
    || (!$collaborationEnabled&&(getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')!=='enabled'||getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED')!=='enabled'))) $deny(404);
$conflict='';$actionError='';$materialSource=null;$catalog=null;$materialRows=[];$materialQuery='';$materialWarning='';
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) $deny(405);
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!is_string($host)
    ||!hash_equals($allowed,$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') $deny(403);
if ($method==='POST') {
    $expectedOrigin='https://'.$host;
    $origin=$_SERVER['HTTP_ORIGIN']??null;
    $referer=$_SERVER['HTTP_REFERER']??null;
    $fetchSite=$_SERVER['HTTP_SEC_FETCH_SITE']??null;
    if (is_string($origin) && $origin!=='' && $origin!=='null') {
        if (!hash_equals($expectedOrigin,$origin)) $deny(403,'ORIGIN_MISMATCH');
    } elseif (is_string($fetchSite) && in_array($fetchSite,['cross-site','same-site'],true)) {
        $deny(403,'ORIGIN_CROSS_SITE');
    } elseif ($fetchSite==='same-origin'
        || (is_string($referer) && str_starts_with($referer,$expectedOrigin.'/'))) {
        // An explicit same-origin browser signal.
    } elseif (!is_string($_POST['csrf']??null)) {
        // Privacy browsers can omit all origin signals; CSRF still verified below.
        $deny(403,'ORIGIN_NO_CSRF');
    }
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>4096) $deny(413,'BODY_SIZE');
}
$draft=filter_var(($method==='POST'?($_POST['draft_id']??null):($_GET['draft_id']??null)),FILTER_VALIDATE_INT);
$showList=$collaborationEnabled&&$method==='GET'&&!array_key_exists('draft_id',$_GET);
if (!$showList&&(!is_int($draft)||$draft<1)) $deny(400);
try {
    require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteCrmOperatorContext.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteOfficeCrmSessionRepository.php';
    require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftLineRepository.php';
    $session=$_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')]??null;
    if (!is_string($session)){
        if($collaborationEnabled&&$method==='GET'){header('X-Brebo-Calc-Diagnostic: SESSION_COOKIE');header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302);exit;}
        $deny(403,'SESSION_COOKIE');
    }
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db))->findActive($session,time());
    if($operator===null&&$collaborationEnabled&&$method==='GET'){header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302);exit;}
    if ($operator===null||!$operator->canEditCrm||!ctype_digit($operator->operatorId)) $deny(403,'SESSION_RIGHTS');
    $actor=(int)$operator->operatorId;
    if($showList){
        $q=$db->prepare("SELECT d.id,d.opportunity_id,d.created_at,a.username FROM office_calc_crm_drafts d JOIN office_crm_opportunities o ON o.id=d.opportunity_id JOIN office_operator_accounts a ON a.id=d.created_by WHERE d.project_id IS NULL AND d.crm_revision=o.revision AND o.status='open' AND o.stage IN ('prospect','opportunity','proposal','negotiation') AND (d.created_by=? OR EXISTS(SELECT 1 FROM office_calc_crm_draft_collaborators c WHERE c.draft_id=d.id AND c.operator_id=?)) ORDER BY d.id DESC LIMIT 200");$q->execute([$actor,$actor]);$drafts=$q->fetchAll(PDO::FETCH_ASSOC);
        $escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        header('Content-Type: text/html; charset=utf-8');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
        echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gezamenlijke calculaties · BREBO Office</title><style>body{font:15px system-ui;margin:32px;background:#f3f6fa;color:#19324a}main{max-width:1100px;margin:auto;background:white;padding:24px;border-radius:10px}table{width:100%;border-collapse:collapse}td,th{padding:14px;text-align:left;border-bottom:1px solid #dce3ea}a{color:#165b8d}.calc-inline-material-picker{background:#fff;border:1px solid #cbd4df;border-radius:4px;margin:7px 0;padding:8px 10px}.calc-inline-material-picker>summary{cursor:pointer;color:#205a98;font-weight:700}.calc-inline-material-picker>.panel{margin:9px 0 0}.calc-inline-material-picker form{background:#f4f6f9}
.calc-topbar{position:relative;top:auto}.calc-inline-material-picker{margin-bottom:7px}.calc-data-secondary{margin-top:7px}.calc-data-secondary>summary{cursor:pointer;padding:9px 12px;background:#f4f7fb;border:1px solid #cbd4df;font-weight:700}.calc-data-secondary>.panel{margin:0;border-top:0}.table-scroll th:nth-child(2),.table-scroll td:nth-child(2){position:sticky;left:30px;z-index:2;background:#fff;box-shadow:2px 0 3px #142a4314}.table-scroll th:first-child,.table-scroll td:first-child{position:sticky;left:0;z-index:3;background:#edf1f7;min-width:30px}.table-scroll thead th:nth-child(-n+2){z-index:5}.table-scroll .calc-inline-row td:nth-child(2){background:#edf5ff}.table-scroll{scrollbar-gutter:stable}
.calc-material-row>td{padding:4px 7px;background:#edf5ff!important}.calc-material-row .calc-inline-material-picker{margin:0}.calc-material-row .panel{margin:6px 0}.calc-material-row table{width:100%}.calc-material-row table th,.calc-material-row table td{position:static!important;box-shadow:none!important}.calc-material-row form{white-space:normal}.calc-material-row summary{white-space:normal}
</style></head><body><main><h1>Gezamenlijke calculaties</h1><p>Je eigen concepten en calculaties waarvoor je expliciet bewerkrecht hebt gekregen.</p><p><a href="/office-test/crm/verkoopkansen">Verkoopkansen</a> · <a href="/office-test/materialen">Materialenprijslijst</a></p><table><thead><tr><th>Calculatie</th><th>Verkoopkans</th><th>Maker</th></tr></thead><tbody>';
        foreach($drafts as$item)echo '<tr><td><a href="'.$workbenchUrl.'?draft_id='.(int)$item['id'].'">Calculatie #'.(int)$item['id'].'</a></td><td>#'.(int)$item['opportunity_id'].'</td><td>'.$escape($item['username']).'</td></tr>';
        if($drafts===[])echo '<tr><td colspan="3">Geen toegankelijke conceptcalculaties. Een calculatie start vanuit de verkoopkans in CRM.</td></tr>';
        echo '</tbody></table></main></body></html>';exit;
    }
    // Temporary least-privilege policy: only the draft creator may edit.
    $rights=static function(PDO $db,int $actor,int $opportunity):bool {
        $q=$db->prepare('SELECT 1 FROM office_calc_crm_drafts WHERE opportunity_id=? AND created_by=?');
        $q->execute([$opportunity,$actor]);
        return $q->fetchColumn()!==false;
    };
    $collaboration=null;
    if($collaborationEnabled){
        require_once dirname(__DIR__).'/src/Calculation/OfficeCalcDraftCollaboration.php';
        $collaboration=new \Brebo\Office\Calculation\OfficeCalcDraftCollaboration($db);
        $rights=static function(PDO $connection,int $user,int $opportunity)use($draft,$collaboration):bool{
            $q=$connection->prepare('SELECT 1 FROM office_calc_crm_drafts WHERE id=? AND opportunity_id=?');$q->execute([$draft,$opportunity]);
            return $q->fetchColumn()!==false&&$collaboration->canEdit($user,$draft);
        };
    }
    if($collaborationEnabled&&getenv('OFFICE_CALC_DRAFT_MATERIALS_ENABLED')==='enabled'){
        foreach(['MaterialCatalogConnection','MaterialCatalogReadModel','OfficeMaterialAccess']as$class)require_once dirname(__DIR__).'/src/Materials/'.$class.'.php';
        require_once dirname(__DIR__).'/src/Calculation/OfficeCalcDraftMaterialSource.php';
        $materialAccess=new \Brebo\Office\Materials\OfficeMaterialAccess($db);
        if($materialAccess->allows($operator->operatorId,'read')){
            try{
                $catalog=new \Brebo\Office\Materials\MaterialCatalogReadModel(\Brebo\Office\Materials\MaterialCatalogConnection::fromEnvironment(),getenv('OFFICE_MATERIALS_CANONICAL_TABLE_PREFIX')?:'');
                $materialSource=new \Brebo\Office\Calculation\OfficeCalcDraftMaterialSource($catalog,$materialAccess);
            }catch(Throwable){$materialWarning='De prijslijst is tijdelijk niet beschikbaar. Probeer de materiaalkeuze later opnieuw.';}
        }
    }
    $laborRates=[];
    $laborRateCatalog=null;
    if(getenv('OFFICE_CALC_LABOR_RATES_SCHEMA_VERIFIED')==='enabled'){
        require_once dirname(__DIR__).'/src/Calculation/OfficeCalcLaborRateCatalog.php';
        $laborRateCatalog=new \Brebo\Office\Calculation\OfficeCalcLaborRateCatalog($db);
        $laborRates=$laborRateCatalog->all();
    }
    $repo=new \Brebo\Office\Calculation\OfficeCalcCrmDraftLineRepository($db);
    $csrf=$_COOKIE['office_project_csrf']??null;
    if (!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)) {
        if ($method==='POST') $deny(403,'CSRF_COOKIE');
        $csrf=bin2hex(random_bytes(32));
        if (!setcookie('office_project_csrf',$csrf,['path'=>'/office-test','secure'=>true,'httponly'=>true,'samesite'=>'Strict'])) $deny(503);
    }
    if ($method==='POST') {
        $submitted=$_POST['csrf']??null;
        if (!is_string($submitted)||!hash_equals($csrf,$submitted)) $deny(403,'CSRF_MISMATCH');
        $action=(string)($_POST['action']??'save_line');
    if($method==='POST'&&$action==='save_labor_rate'){
        if($laborRateCatalog===null)$deny(503,'LABOR_RATE_CATALOG_DISABLED');
        if($collaboration===null||!$collaboration->owns($actor,$draft))$deny(403,'LABOR_RATE_ADMIN_REQUIRED');
        $laborRateCatalog->save((string)($_POST['rate_code']??''),(string)($_POST['rate_description']??''),str_replace(',','.',(string)($_POST['rate_hourly']??'')),isset($_POST['rate_active']));
        header('Location: '.$workbenchUrl.'?draft_id='.$draft,true,303);exit;
    }
        if($action==='add_material'){
            if($materialWarning!=='')$deny(503,'PRICE_SOURCE_UNAVAILABLE');
            if($collaboration===null||$materialSource===null)throw new DomainException('Geen materiaalrecht.');
            $articleId=filter_var($_POST['supplier_article_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$priceId=filter_var($_POST['price_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $materialQuantity=str_replace(',','.',trim((string)($_POST['material_quantity']??'1')));
            if(!is_int($articleId)||!is_int($priceId)||!preg_match('/^[0-9]{1,6}(?:\\.[0-9]{1,4})?$/D',$materialQuantity)||bccomp($materialQuantity,'0',4)<=0)$deny(400,'INVALID_MATERIAL');
            $source=$materialSource->choose($operator->operatorId,$articleId,$priceId,(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d'));
            $repo->save($draft,$actor,bin2hex(random_bytes(12)),'material',$source['description'],1,0,null,$rights,(string)($_POST['expected_revision']??''),'Materiaal uit prijslijst: '.$source['source_reference'],$materialQuantity,$source['unit_price'],$source['unit'],$source);
            header('Location: '.$workbenchUrl.'?draft_id='.$draft,true,303);exit;
        }
        if($collaboration!==null&&in_array($action,['grant','remove','checkpoint'],true)){
            $expected=(string)($_POST['expected_revision']??'');
            if($action==='checkpoint')$collaboration->checkpoint($draft,$actor,(string)($_POST['reason']??''),$expected,$rights);
            else{
                $target=filter_var($_POST['operator_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                if(!is_int($target))throw new DomainException('Kies een geldige Office-gebruiker.');
                $collaboration->grant($draft,$actor,$target,$action==='remove',$expected,$rights);
            }
            header('Location: '.$workbenchUrl.'?draft_id='.$draft,true,303);exit;
        }
        if($action!=='save_line')throw new DomainException('Onbekende calculatieactie.');
        $lineId=(string)($_POST['line_id']??'');
        if ($lineId==='') $lineId=bin2hex(random_bytes(12));
        $exactQuantity=null;$exactUnitCost=null;$unit=null;
        if($collaborationEnabled){
            $exactQuantity=str_replace(',','.',trim((string)($_POST['quantity']??'')));
            if(!preg_match('/^[0-9]{1,6}(?:\\.[0-9]{1,4})?$/D',$exactQuantity)||bccomp($exactQuantity,'0',4)<=0)$deny(400,'INVALID_QUANTITY_OR_COST');
            $quantity=1;$unit=trim((string)($_POST['unit']??''));
        }else $quantity=filter_var($_POST['quantity']??null,FILTER_VALIDATE_INT);
        // Legacy callers use cents; reviewed native inputs preserve four decimal places.
        $price=$_POST['unit_price_eur']??null;
        if (!is_string($price)) $deny(400,'INVALID_PRICE');
        $price=trim(str_replace(',', '.', $price));
        if (!preg_match($collaborationEnabled?'/^[0-9]{1,8}(?:\\.[0-9]{1,4})?$/D':'/^(?:0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/D',$price)) $deny(400,'INVALID_PRICE');
        [$whole,$fraction]=array_pad(explode('.',$price,2),2,'');
        $cost=((int)$whole)*100+(int)substr(str_pad($fraction,2,'0'),0,2);
        if($collaborationEnabled)$exactUnitCost=$price;
        if (!is_int($quantity)||$quantity<1) $deny(400,'INVALID_QUANTITY_OR_COST');
        // Optional adjustments are calculated only on new lines. Source quantities in Gebouwen are never written.
        $adjustmentReason='';$adjustmentMetadata=null;
        if($collaborationEnabled&&getenv('OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED')!=='enabled'&&(
            trim((string)($_POST['material_loss_percent']??''))!==''||
            trim((string)($_POST['extra_labor_hours']??''))!==''))$deny(400,'ADJUSTMENT_SCHEMA_UNVERIFIED');
        if($collaborationEnabled){
            $loss=trim(str_replace(',','.',(string)($_POST['material_loss_percent']??'')));
            $extra=trim(str_replace(',','.',(string)($_POST['extra_labor_hours']??'')));
            if($loss===''&&$extra===''&&trim((string)($_POST['line_id']??''))!==''&&getenv('OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED')==='enabled'){
                $adjustmentMetadata=['net_quantity'=>$exactQuantity,'material_loss_percent'=>null,'extra_labor_hours'=>null];
                $adjustmentReason='Nettohoeveelheid opnieuw vastgesteld zonder correctie';
            }
            if($loss!==''||$extra!==''){
                require_once dirname(__DIR__).'/src/Calculation/OfficeCalcQuantityAdjustments.php';
                if($loss!==''&&$extra!=='')$deny(400,'ADJUSTMENT_CONFLICT');
                if($loss!==''){
                    if((string)($_POST['cost_type']??'')!=='material')$deny(400,'ADJUSTMENT_TYPE');
                    if(!preg_match('/^[0-9]{1,3}(?:\\.[0-9]{1,2})?$/D',$loss)||bccomp($loss,'100',2)>0)$deny(400,'INVALID_MATERIAL_LOSS');
                    $calc=\Brebo\Office\Calculation\OfficeCalcQuantityAdjustments::material($exactQuantity,$loss,$price);
                    $adjustmentReason='Netto '.$calc['net_quantity'].' + '.$loss.'% materiaalverlies = '.$calc['gross_quantity'].' '.$unit;
                    $exactQuantity=$calc['gross_quantity'];
                    $adjustmentMetadata=['net_quantity'=>$calc['net_quantity'],'material_loss_percent'=>$loss,'extra_labor_hours'=>null];
                }else{
                    if((string)($_POST['cost_type']??'')!=='labor')$deny(400,'ADJUSTMENT_TYPE');
                    if($unit!=='uur'||!preg_match('/^[0-9]{1,6}(?:\\.[0-9]{1,4})?$/D',$extra))$deny(400,'INVALID_EXTRA_HOURS');
                    $calc=\Brebo\Office\Calculation\OfficeCalcQuantityAdjustments::labor($exactQuantity,$extra,$price);
                    $adjustmentReason='Basis '.$calc['base_hours'].' + '.$extra.' extra uren = '.$calc['total_hours'].' uur';
                    $exactQuantity=$calc['total_hours'];
                    $adjustmentMetadata=['net_quantity'=>$calc['base_hours'],'material_loss_percent'=>null,'extra_labor_hours'=>$extra];
                }
            }
        }
        if($laborRateCatalog!==null&&trim((string)($_POST['labor_rate_code']??''))!==''){
            $selectedRate=$laborRateCatalog->resolve((string)$_POST['labor_rate_code']);
            $_POST['labor_hourly_rate']=(string)$selectedRate['hourly_rate'];
        }
        if(trim((string)($_POST['labor_norm']??''))!==''||trim((string)($_POST['labor_hours']??''))!==''){
            if(getenv('OFFICE_CALC_LABOR_SCHEMA_VERIFIED')!=='enabled'||(string)($_POST['cost_type']??'')!=='labor'||trim((string)($_POST['labor_hourly_rate']??''))==='')$deny(400,'LABOR_INPUT_INCOMPLETE');
        }
        $labor=null;
        if(getenv('OFFICE_CALC_LABOR_SCHEMA_VERIFIED')==='enabled'&&(string)($_POST['cost_type']??'')==='labor'&&trim((string)($_POST['labor_hourly_rate']??''))!==''){
            if(!$collaborationEnabled||$adjustmentMetadata!==null)$deny(400,'LABOR_NOT_AVAILABLE');
            require_once dirname(__DIR__).'/src/Calculation/OfficeCalcLaborNorm.php';
            $driver=(string)($_POST['labor_driver']??'norm');
            $input=str_replace(',','.',trim((string)($_POST[$driver==='hours'?'labor_hours':'labor_norm']??'')));
            $rate=str_replace(',','.',trim((string)($_POST['labor_hourly_rate']??'')));
            $labor=\Brebo\Office\Calculation\OfficeCalcLaborNorm::calculate($exactQuantity,$driver,$input,$rate);
            $labor['input']=$input;
            $labor['rate_code']=$selectedRate['code']??'';
            $exactUnitCost=$labor['labor_unit_cost'];
            $cost=(int)bcdiv(bcmul($exactUnitCost,'100',4),'1',0);
            $adjustmentReason='Arbeid '.$labor['hours'].' uur, norm '.$labor['norm'].', tarief '.$labor['hourly_rate'].' (leidend '.$driver.')';
        }
        if(getenv('OFFICE_CALC_LABOR_SCHEMA_VERIFIED')==='enabled'&&$collaborationEnabled&&$labor===null){
            foreach($repo->list($draft,$actor,$rights) as $existingLabor){
                if((string)$existingLabor['line_id']===$lineId&&($existingLabor['labor_driver']??null)!==null)
                    throw new DomainException('Arbeidsregel vereist norm/uren en tarief; bestaande waarden worden niet gewist.');
            }
        }
        $repo->save($draft,$actor,$lineId,(string)($_POST['cost_type']??''),(string)($_POST['description']??''),
            $quantity,$cost,null,$rights,$collaborationEnabled?(string)($_POST['expected_revision']??''):null,(string)($_POST['reason']??'Calculatieregel gewijzigd').($adjustmentReason!==''?' | '.$adjustmentReason:''),$exactQuantity,$exactUnitCost,$unit,null,$adjustmentMetadata,$labor);
        header('Location: '.$workbenchUrl.'?draft_id='.$draft,true,303);exit;
    }
    $lines=$repo->list($draft,$actor,$rights);
} catch (\Brebo\Office\Calculation\OfficeCalcDraftConflict $error) {http_response_code(409);$conflict=$error->getMessage();}
catch (DomainException) { $deny(403,'DRAFT_ACCESS'); }
catch (PDOException) { $deny(503,'DATABASE'); }
catch (Throwable) { $deny(503,'RUNTIME'); }
$expectedRevision='';$history=[];$members=[];$eligible=[];$auditDetail=null;$canManageCollaborators=false;
if($collaboration!==null){
    try{
        $workspace=$collaboration->read($draft,$actor,$rights,$repo);$lines=$workspace['lines'];$expectedRevision=$workspace['token'];
        $history=$collaboration->history($draft,$actor,$rights);$members=$collaboration->members($draft);$canManageCollaborators=$collaboration->owns($actor,$draft);
        if($canManageCollaborators)$eligible=$db->query('SELECT id,username FROM office_operator_accounts WHERE active=1 AND can_edit_crm=1 ORDER BY username,id LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        if(isset($_GET['change_id'])){
            $changeId=filter_var($_GET['change_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!is_int($changeId))$deny(400,'INVALID_CHANGE');
            $auditDetail=$collaboration->detail($draft,$actor,$changeId,$rights);if($auditDetail===null)$deny(404,'CHANGE_NOT_FOUND');
        }
    }catch(DomainException){$deny(403,'DRAFT_ACCESS');}catch(Throwable){$deny(503,'HISTORY_UNAVAILABLE');}
}
if($materialSource!==null){
    $query=$_GET['material_q']??$_POST['material_q']??'';$materialQuery=is_string($query)?mb_substr(trim($query),0,120):'';
    try{if($materialQuery!=='')$materialRows=$catalog->search($materialQuery,null,(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d'),0,null,true);}catch(Throwable){$materialWarning='De prijslijst is tijdelijk niet beschikbaar. Probeer de materiaalkeuze later opnieuw.';}
    $materialRows=array_slice($materialRows,0,50);
}
$editId=filter_input(INPUT_GET,'edit_line_id',FILTER_UNSAFE_RAW);
$edit=null;
if (is_string($editId) && preg_match('/^[a-zA-Z0-9_-]{1,60}$/D',$editId)) {
    foreach ($lines as $existingLine) {
        if (hash_equals((string)$existingLine['line_id'],$editId)) {
            $edit=$existingLine;
            break;
        }
    }
}
if($conflict!==''&&($_POST['action']??'save_line')==='save_line'){
    $edit=['line_id'=>$lineId??(string)($_POST['line_id']??''),'description'=>(string)($_POST['description']??''),'cost_type'=>(string)($_POST['cost_type']??'material'),'quantity'=>$quantity??1,'unit_cost_cents'=>$cost??0,'quantity_decimal'=>$exactQuantity??'1','unit_cost_decimal'=>(string)($_POST['unit_price_eur']??'0'),'unit'=>(string)($_POST['unit']??'')];
}
$h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$typeLabels=['labor'=>'Arbeid','material'=>'Materiaal','equipment'=>'Materieel','subcontract'=>'Onderaanneming','other'=>'Overig'];
$categoryTotals=array_fill_keys(array_keys($typeLabels),$collaborationEnabled?'0':0);
$lineAmount=static function(array $line)use($collaborationEnabled):string|int{
    if(!$collaborationEnabled)return (int)$line['quantity']*(int)$line['unit_cost_cents'];
    return bcmul((string)$line['quantity_decimal'],(string)$line['unit_cost_decimal'],8);
};
foreach($lines as $line){$kind=(string)$line['cost_type'];if(isset($categoryTotals[$kind]))$categoryTotals[$kind]=$collaborationEnabled?bcadd((string)$categoryTotals[$kind],(string)$lineAmount($line),8):$categoryTotals[$kind]+$lineAmount($line);}
$canonicalTotal=$collaborationEnabled?(string)($workspace['direct_cost']??'0'):(string)(array_sum($categoryTotals)/100);
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Calculatieconcept #'.$draft.' | BREBO Office</title><style>
:root{color-scheme:light}*{box-sizing:border-box}body{font:13px system-ui,sans-serif;background:#edf1f7;color:#24374a;margin:0}
main{display:block;width:100%;max-width:none;margin:0;padding:8px;min-width:0}.office-shell>main{width:100%!important;max-width:none!important;margin:0!important;padding:12px!important}.workspace,.gridlayout,.panel{min-width:0}a{color:#205a98}header,.panel{background:#fff;border:1px solid #cbd4df;border-radius:3px;padding:10px;margin-bottom:7px}
header{display:flex;justify-content:space-between;gap:12px;align-items:center}header small{color:#53677f;letter-spacing:.03em}
h1{font-size:18px;margin:3px 0}h2{font-size:14px;margin:0 0 8px}.muted{color:#62748b;margin:3px 0}
.metrics{display:flex;flex-wrap:wrap;gap:12px;margin:5px 0 8px;padding:5px 10px;background:#f8fafc;border:1px solid #d8e0ea}.metric{display:flex;align-items:center;gap:8px}
.metric span{color:#64758b}.metric strong{font-size:14px}
.gridlayout{display:block}.line-editor{display:none;margin-top:8px}.line-editor:target,.line-editor.is-editing,.line-editor.has-conflict{display:block}.grid-toolbar{padding:7px 9px;background:#f3f6fa;border:1px solid #d6dfea;border-bottom:0}.guided-questions{display:flex;flex-wrap:wrap;gap:8px}.guided-choice{padding:9px 12px;border:1px solid #bdcbe0;border-radius:4px;text-decoration:none;color:#244c89;background:#f5f8fd}.grid-toolbar a{display:inline-block;background:#304a95;color:#fff;text-decoration:none;padding:7px 11px;border-radius:3px;font-weight:700}
table{width:100%;border-collapse:collapse;white-space:nowrap}td,th{padding:5px 7px;border:1px solid #e1e5ec;text-align:left}th{font-size:11px;color:#334b66;background:#e8eef6;position:sticky;top:0;z-index:1}
tbody tr:nth-child(even){background:#f5f9fc}tbody tr:hover{background:#d6f1ec}td:first-child{font-variant-numeric:tabular-nums;color:#62748b;background:#f0f3f8}
td.numeric,th.numeric{text-align:right;font-variant-numeric:tabular-nums}.table-scroll{overflow:auto;max-height:calc(100vh - 195px);min-height:120px}.calc-inline-input{box-sizing:border-box;width:100%;min-width:65px;padding:5px;border:1px solid #b8c7d8;border-radius:2px;background:#fff}.calc-inline-description{min-width:190px}.calc-inline-row td{background:#edf5ff!important}.calc-inline-row button{white-space:nowrap}.calc-inline-row .calc-inline-factor{min-width:85px}.amount{font-size:15px;font-weight:700;text-align:right;margin:8px}
form{background:#f4f6f9;padding:10px;border-radius:3px}.calc-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-bottom:10px}
.calc-fields label{display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:12px}.calc-fields input,.calc-fields select{width:100%;font:inherit;padding:7px;border:1px solid #b6c3d4;border-radius:3px;background:#fff}
button{background:#304a95;border:0;border-radius:3px;color:#fff;padding:8px 11px;font:inherit;font-weight:700;cursor:pointer}.empty{padding:20px;text-align:center;color:#718198}
dl{display:grid;grid-template-columns:1fr auto;gap:8px;margin:0}dd{margin:0;font-variant-numeric:tabular-nums;font-weight:650}
.workbench{display:grid;grid-template-columns:225px minmax(0,1fr);gap:8px}.element-tree{background:#fff;border:1px solid #cbd4df;padding:8px;min-height:500px;max-height:calc(100vh - 115px);overflow:auto}.element-tree h2{border-bottom:1px solid #d8e0ea;padding-bottom:7px}.element-tree ul{list-style:none;margin:0;padding:0}.element-tree li{padding:5px 3px;border-bottom:1px solid #edf0f4;font-size:12px}.element-tree li:before{content:"▸ ";color:#8c9aad}.sheet-label{background:#d8e5f2;border:1px solid #bfcfe1;padding:6px 9px;font-weight:700}.sheet-tabs{display:flex;gap:6px;padding:8px;background:#f4f6fa;border:1px solid #cbd4df}.sheet-tabs button{background:white;color:#244c89;border:1px solid #cbd4df}.sheet-tabs button[aria-selected="true"]{background:#304a95;color:white}.calc-tab-panel[hidden],.element-tree[hidden]{display:none!important}.calc-view-controls{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.element-tree.calc-unpinned{position:static}.element-tree.calc-pinned{position:sticky;top:8px;align-self:start}
@media(max-width:1050px){.workbench{grid-template-columns:165px minmax(0,1fr)}.gridlayout{grid-template-columns:minmax(0,1fr)}}
@media(max-width:680px){.workbench{display:block}.element-tree{min-height:0;max-height:180px;margin-bottom:8px}header{display:block}.calc-fields{grid-template-columns:1fr}.table-scroll{max-height:65vh}}
.calc-topbar{position:sticky;top:0;z-index:30;display:flex;justify-content:space-between;align-items:center;gap:12px;background:#fff;border:1px solid #cbd4df;padding:8px 12px;margin-bottom:7px;box-shadow:0 2px 7px #142a4314}.calc-topbar a{font-weight:650;text-decoration:none}.calc-topbar-right{display:flex;align-items:center;gap:8px}.calc-account-menu{position:relative}.calc-account-menu summary{cursor:pointer;list-style:none;border:1px solid #cbd4df;border-radius:4px;padding:7px 10px;font-weight:650}.calc-account-menu summary::-webkit-details-marker{display:none}.calc-account-menu[open] .calc-account-dropdown{position:absolute;right:0;top:calc(100% + 5px);z-index:40;min-width:210px;background:#fff;border:1px solid #cbd4df;box-shadow:0 6px 16px #142a4326;padding:8px;display:grid;gap:6px}.calc-account-dropdown a,.calc-account-dropdown span{display:block;padding:8px}.calc-account-dropdown .unavailable{color:#718198}.calc-account-dropdown form{margin:0;padding:0;background:transparent}.calc-account-dropdown button{width:100%;text-align:left}.calc-topbar-settings{border:1px solid #cbd4df;border-radius:4px;padding:7px 10px;color:#718198;cursor:default}
</style></head><body><main><nav class="calc-topbar" aria-label="Werkbalk calculatie"><a href="/office-test/">← Terug naar hoofdmenu</a><div class="calc-topbar-right"><details class="calc-account-menu"><summary aria-label="Profiel en account">👤 Profiel ▾</summary><div class="calc-account-dropdown"><span class="unavailable" aria-disabled="true">Mijn profiel · binnenkort</span><a href="/office-test/inloggen">Inloggen</a><form method="post" action="/office-test/uitloggen"><button type="submit">Uitloggen</button></form></div></details><span class="calc-topbar-settings" title="Instellingen worden later ingericht" aria-disabled="true">⚙ Instellingen</span></div></nav><header><div><small>BREBO OFFICE / CRM / CALCULATIE</small><h1>Calculatieconcept #'.$draft.'</h1><p class="muted">Concept · Zonder project · Bedragen exclusief btw</p></div></header>';
echo '<div class="calc-view-controls"><button type="button" id="calc-toggle-elements" aria-expanded="true" aria-controls="calc-elements">Elementen inklappen</button> <button type="button" id="calc-pin-elements" aria-pressed="true">📌 Vastgezet</button> <button type="button" id="calc-popout-elements">Elementen in nieuw venster</button></div><div class="workbench" id="calc-workbench"><aside id="calc-elements" class="element-tree" aria-label="Elementenboom"><h2>Elementen · Bouwkosten</h2><label for="calc-element-search">Zoek element</label><input id="calc-element-search" type="search" placeholder="Code of omschrijving" autocomplete="off" aria-controls="calc-element-list"><p id="calc-element-search-status" role="status" aria-live="polite"></p><ul id="calc-element-list">';
foreach(['00 Bouwdeel algemeen','10 Sloopwerkzaamheden','16 Funderingen','21 Buitenwanden','22 Binnenwanden','23 Vloeren','24 Trappen en hellingen','27 Daken','31 Buitenwandopeningen','32 Binnenwandopeningen','33 Vloeropeningen','34 Balustrades en leuningen','37 Dakopeningen','41 Buitenwandafwerkingen','42 Binnenwandafwerkingen','43 Vloerafwerkingen','45 Plafondafwerkingen','47 Dakafwerkingen','50 Energieopwekking','51 Warmteopwekking','52 Afvoeren','53 Water','54 Gassen','56 Warmtedistributie','57 Luchtbehandeling','61 Elektrotechnische voorzieningen','62 Krachtstroom','63 Verlichting','65 Beveiliging','73 Vaste keukenvoorzieningen','74 Vaste sanitaire voorzieningen','90 Terrein','98 Stelposten'] as $element){echo '<li>'.$h($element).'</li>';}
echo '</ul><p class="muted">Classificatie als referentie; koppeling aan calculatieparagrafen volgt.</p></aside><div class="workspace"><div class="metrics">';
echo '<div class="metric"><span>Directe kosten</span><strong>€ '.number_format((float)$canonicalTotal,2,',','.').'</strong></div>';
echo '<div class="metric"><span>Regels</span><strong>'.count($lines).'</strong></div>';
echo '<div class="metric"><span>Status</span><strong>Concept</strong></div>';
echo '</div><nav class="sheet-tabs" role="tablist" aria-label="Calculatiebladen"><button type="button" role="tab" id="tab-data" data-calc-tab="data" aria-controls="panel-data" aria-selected="true">Data</button><button type="button" role="tab" id="tab-hours" data-calc-tab="hours" aria-controls="panel-hours" aria-selected="false">Uren</button><button type="button" role="tab" id="tab-tail" data-calc-tab="tail" aria-controls="panel-tail" aria-selected="false">Staart</button></nav><div id="panel-hours" class="calc-tab-panel" role="tabpanel" aria-labelledby="tab-hours" hidden>'; 
if($laborRateCatalog!==null){
    echo '<section id="calc-hours"><h3>Uurtarieven op code</h3><p>Gebruik een tariefcode bij arbeidsregels. Een wijziging in de prijslijst wijzigt bestaande calculatieregels niet.</p><table><thead><tr><th>Code</th><th>Functie</th><th>Uurtarief</th><th>Status</th></tr></thead><tbody>';
    foreach($laborRates as $tariff)echo '<tr><td>'.$h((string)$tariff['code']).'</td><td>'.$h((string)$tariff['description']).'</td><td>€ '.$h((string)$tariff['hourly_rate']).'</td><td>'.((int)$tariff['active']===1?'Actief':'Inactief').'</td></tr>';
    echo '</tbody></table>';
    if($collaboration!==null&&$collaboration->owns($actor,$draft)){
        echo '<form method="post" action="'.$workbenchUrl.'"><input type="hidden" name="csrf" value="'.$h($csrf).'"><input type="hidden" name="draft_id" value="'.$draft.'"><input type="hidden" name="action" value="save_labor_rate">';
        echo '<label>Code <input name="rate_code" maxlength="16" required placeholder="GZ, A, B"></label><label>Functie <input name="rate_description" maxlength="160" required placeholder="Glaszetter"></label><label>€/uur <input type="number" name="rate_hourly" min="0" step="0.0001" required></label><label>Actief <input type="checkbox" name="rate_active" checked></label><button type="submit">Tarief opslaan</button></form>';
    }
    echo '</section>';
}

echo '</div><div id="panel-data" class="calc-tab-panel" role="tabpanel" aria-labelledby="tab-data"><div id="calc-data" class="sheet-label">Data · Calculatieregels</div>';
echo '<div class="gridlayout"><section class="panel"><div class="grid-toolbar"><strong>Nieuwe regel direct onderaan de tabel invullen</strong></div><div class="table-scroll"><table><thead><tr><th>Nr.</th><th>Omschrijving</th><th>Type</th><th class="numeric">Hoeveelheid</th><th>Eenheid</th><th class="numeric">Verliesfactor %</th><th class="numeric">Norm</th><th class="numeric">Uren</th><th title="Discipline">Disc.</th><th class="numeric">Tarief</th><th class="numeric">Loon</th><th class="numeric">Materiaal</th><th class="numeric">Materieel</th><th class="numeric">Overig</th><th class="numeric">Ond.aann.</th><th class="numeric">Kosten/eh</th><th class="numeric">Subtotaal</th><th>Prijsbron</th><th>Actie</th></tr></thead><tbody>';

$total=0;
$rowNumber=0;
foreach($lines as $line) {
    ++$rowNumber;
    $subtotal=$lineAmount($line);if(!$collaborationEnabled)$total+=$subtotal;
    $sourceLabel=($line['price_source_type']??'manual')==='article'?($line['source_supplier'].' · '.$line['source_reference']):'Handmatig';
    $unitCost=number_format($collaborationEnabled?(float)$line['unit_cost_decimal']:(int)$line['unit_cost_cents']/100,$collaborationEnabled?4:2,',','.');
    $rowTotal=number_format($collaborationEnabled?(float)$subtotal:$subtotal/100,$collaborationEnabled?4:2,',','.');
    $costCells=['labor','material','equipment','other','subcontract'];
    $editing=$edit!==null&&(string)$edit['line_id']===(string)$line['line_id'];
    if($editing){
        $formId='calc-inline-edit';
        $net=(string)($line['net_quantity_decimal']??$line['quantity_decimal']??$line['quantity']);
        $lossValue=(string)($line['material_loss_percent']??'');
        $extraValue=(string)($line['extra_labor_hours']??'');
        echo '<tr class="calc-inline-row"><td>'.$rowNumber.'</td>';
        echo '<td><input form="'.$formId.'" class="calc-inline-input calc-inline-description" name="description" maxlength="500" value="'.$h((string)$line['description']).'" required></td>';
        echo '<td><select form="'.$formId.'" class="calc-inline-input" name="cost_type">';
        foreach($typeLabels as $kind=>$label)echo '<option value="'.$h($kind).'"'.((string)$line['cost_type']===$kind?' selected':'').'>'.$h($label).'</option>';
        echo '</select></td>';
        echo '<td><input form="'.$formId.'" class="calc-inline-input" type="number" name="quantity" min="0.0001" step="0.0001" value="'.$h($net).'" required></td>';
        echo '<td><input form="'.$formId.'" class="calc-inline-input" name="unit" maxlength="32" value="'.$h((string)($line['unit']??'st')).'" required></td>';
        echo '<td><input form="'.$formId.'" class="calc-inline-input calc-inline-factor" type="number" name="material_loss_percent" min="0" max="100" step="0.01" value="'.$h($lossValue).'" placeholder="0"></td>';
        if(getenv('OFFICE_CALC_LABOR_SCHEMA_VERIFIED')==='enabled'&&(string)$line['cost_type']==='labor'){
            echo '<td><input form="'.$formId.'" name="labor_norm" type="number" min="0" step="0.0001" value="'.$h((string)($line['labor_norm']??'')).'" placeholder="Norm"></td>';
            echo '<td><input form="'.$formId.'" name="labor_hours" type="number" min="0" step="0.0001" value="'.$h((string)($line['labor_hours']??'')).'" placeholder="Uren"></td>';
            if($laborRateCatalog!==null){$rateOptions='<option value="">Handmatig tarief</option>';
            foreach($laborRates as $rate)if((int)$rate['active']===1)$rateOptions.='<option value="'.$h((string)$rate['code']).'" data-hourly-rate="'.$h((string)$rate['hourly_rate']).'"'.((string)($line['labor_rate_code']??'')===(string)$rate['code']?' selected':'').'>'.$h((string)$rate['code'].' · '.$rate['description']).'</option>';
            echo '<td><select form="'.$formId.'" name="labor_rate_code" class="calc-labor-discipline">'.$rateOptions.'</select></td><td><input form="'.$formId.'" name="labor_hourly_rate" type="number" min="0" step="0.0001" value="'.$h((string)($line['labor_hourly_rate']??'')).'" placeholder="€/uur"></td>';}else echo '<td>—</td><td><input form="'.$formId.'" name="labor_hourly_rate" type="number" min="0" step="0.0001" value="'.$h((string)($line['labor_hourly_rate']??'')).'" placeholder="€/uur"></td>';
        }else echo '<td>—</td><td>—</td><td>—</td><td>—</td>';
        foreach($costCells as $kind)echo '<td class="numeric">'.((string)$line['cost_type']===$kind?'€ '.$unitCost:'—').'</td>';
        echo '<td><input form="'.$formId.'" class="calc-inline-input" name="unit_price_eur" inputmode="decimal" value="'.$h((string)($line['unit_cost_decimal']??str_replace(',','.',$unitCost))).'" required></td>';
        echo '<td class="numeric">€ '.$rowTotal.'</td><td>'.$h($sourceLabel).'</td>';
        echo '<td><button form="'.$formId.'" type="submit">Opslaan</button> <a href="'.$workbenchUrl.'?draft_id='.$draft.'">Annuleren</a></td></tr>';
    }else{
        echo '<tr><td>'.$rowNumber.'</td><td>'.$h((string)$line['description']).'</td><td>'.$h($typeLabels[(string)$line['cost_type']]??(string)$line['cost_type']).'</td><td class="numeric">'.$h((string)($line['quantity_decimal']??$line['quantity'])).'</td><td>'.$h((string)($line['unit']??'')).'</td><td class="numeric">'.$h((string)($line['material_loss_percent']??'—')).'</td><td class="numeric">'.$h((string)($line['labor_norm']??'—')).'</td><td class="numeric">'.$h((string)($line['labor_hours']??'—')).'</td><td>'.$h((string)($line['labor_rate_code']??'—')).'</td><td class="numeric">'.$h((string)($line['labor_hourly_rate']??'—')).'</td>';
        foreach($costCells as $kind)echo '<td class="numeric">'.((string)$line['cost_type']===$kind?'€ '.$unitCost:'—').'</td>';
        echo '<td class="numeric">€ '.$unitCost.'</td><td class="numeric">€ '.$rowTotal.'</td><td>'.$h($sourceLabel).'</td><td><a href="'.$workbenchUrl.'?draft_id='.$draft.'&amp;edit_line_id='.rawurlencode((string)$line['line_id']).'">Wijzigen</a></td></tr>';
    }
}
$guidedOptions=['crane'=>['Kraan nodig?','Kraanhuur','equipment','dag'],'scaffold'=>['Steiger nodig?','Steigerhuur','equipment','week'],'toilet'=>['Bouwtoilet nodig?','Bouwtoilet','other','week'],'labor'=>['Extra arbeidsuren nodig?','Extra montageuren','labor','uur'],'transport'=>['Extra transport nodig?','Extra transportkosten','other','rit'],'waste'=>['Materiaalverlies voorzien?','Extra materiaal wegens snijverlies','material','m2'],'protection'=>['Bescherming werkgebied nodig?','Beschermingsmateriaal werkgebied','material','m2']];
$guidedKey=is_string($_GET['guided']??null)?(string)$_GET['guided']:'';
$guidedPreset=$edit===null?($guidedOptions[$guidedKey]??null):null;
$editIdValue=$edit===null?'':$h((string)$edit['line_id']);
$editDescription=$edit===null?($guidedPreset===null?'':$h($guidedPreset[1])):$h((string)$edit['description']);
$editQuantity=$edit===null?'':$h((string)($edit['net_quantity_decimal']??$edit['quantity_decimal']??$edit['quantity']));
$editPrice=$edit===null?'':($collaborationEnabled?$h(str_replace('.',',',(string)$edit['unit_cost_decimal'])):number_format((int)$edit['unit_cost_cents']/100,2,',',''));
$editUnit=$edit===null?($guidedPreset===null?'st':$h($guidedPreset[3])):$h((string)(($edit['unit']??'')?:'st'));
$selectedCost=$edit===null?($guidedPreset===null?'labor':$guidedPreset[2]):(string)$edit['cost_type'];
if($lines===[]) echo '<tr><td colspan="19" class="empty">Nog geen calculatieregels. Vul hieronder de eerste regel in.</td></tr>';
if($edit===null){
    $inlineForm='calc-inline-new';
    echo '<tr class="calc-inline-row"><td>+</td><td><input form="'.$inlineForm.'" class="calc-inline-input calc-inline-description" name="description" maxlength="500" placeholder="Omschrijving" required></td>';
    echo '<td><select form="'.$inlineForm.'" class="calc-inline-input" name="cost_type" id="calc-inline-type"><option value="labor">Arbeid</option><option value="material">Materiaal</option><option value="equipment">Materieel</option><option value="subcontract">Onderaanneming</option><option value="other">Overig</option></select></td>';
    echo '<td><input form="'.$inlineForm.'" class="calc-inline-input" type="number" name="quantity" min="'.($collaborationEnabled?'0.0001':'1').'" step="'.($collaborationEnabled?'0.0001':'1').'" value="1" required></td>';
    echo '<td><input form="'.$inlineForm.'" class="calc-inline-input" name="unit" maxlength="32" value="st" required></td>';
    echo '<td><input form="'.$inlineForm.'" class="calc-inline-input calc-inline-factor" type="number" name="material_loss_percent" min="0" max="100" step="0.01" placeholder="0" title="Alleen voor materiaal" '.(!($collaborationEnabled&&getenv('OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED')==='enabled')?'disabled':'').'></td>';
    if(getenv('OFFICE_CALC_LABOR_SCHEMA_VERIFIED')==='enabled'){
        echo '<td><input form="'.$inlineForm.'" name="labor_norm" type="number" min="0" step="0.0001" placeholder="Norm"></td><td><input form="'.$inlineForm.'" name="labor_hours" type="number" min="0" step="0.0001" placeholder="Uren"></td>';
        if($laborRateCatalog!==null){
            $rateOptions='<option value="">Handmatig tarief</option>';
            foreach($laborRates as $rate)if((int)$rate['active']===1)$rateOptions.='<option value="'.$h((string)$rate['code']).'">'.$h((string)$rate['code'].' · '.$rate['description'].' (€ '.$rate['hourly_rate'].'/uur)').'</option>';
            echo '<td><select form="'.$inlineForm.'" name="labor_rate_code" class="calc-labor-discipline">'.$rateOptions.'</select></td><td><input form="'.$inlineForm.'" name="labor_hourly_rate" type="number" min="0" step="0.0001" placeholder="€/uur"></td>';
        }else echo '<td>—</td><td><input form="'.$inlineForm.'" name="labor_hourly_rate" type="number" min="0" step="0.0001" placeholder="€/uur"></td>';
    }else echo '<td>—</td><td>—</td><td>—</td><td>—</td>';
    echo '<td colspan="5"><input form="'.$inlineForm.'" class="calc-inline-input" name="unit_price_eur" inputmode="decimal" pattern="[0-9]+([,.][0-9]{1,'.($collaborationEnabled?'4':'2').'})?" placeholder="Kostprijs per eenheid (€)" required></td><td>—</td><td>—</td><td>Handmatig</td><td><button form="'.$inlineForm.'" type="submit">Opslaan</button></td></tr>';
}
if($materialSource!==null){
 echo '<tr class="calc-material-row"><td colspan="19"><details class="calc-inline-material-picker"'.($materialQuery!==''?' open':'').'><summary>+ Materiaal zoeken en als calculatieregel opnemen</summary>';
 require __DIR__.'/office-calc-crm-material-panel.php';
 echo '</details></td></tr>';
}
echo '</tbody></table></div><p class="amount">Totaal: € '.number_format($collaborationEnabled?(float)$canonicalTotal:$total/100,2,',','.').'</p></section>';
if($edit!==null){
 echo '<form id="calc-inline-edit" method="post" action="'.$workbenchUrl.'"><input type="hidden" name="draft_id" value="'.$draft.'"><input type="hidden" name="csrf" value="'.$h($csrf).'"><input type="hidden" name="line_id" value="'.$h((string)$edit['line_id']).'">';
 if($collaborationEnabled)echo '<input type="hidden" name="expected_revision" value="'.$h($expectedRevision).'"><input type="hidden" name="reason" value="Calculatieregel direct in tabel gewijzigd">';
 echo '<input type="hidden" name="extra_labor_hours" value="'.$h((string)($edit['extra_labor_hours']??'')).'">';
 if(getenv('OFFICE_CALC_LABOR_SCHEMA_VERIFIED')==='enabled')echo '<input type="hidden" name="labor_driver" value="'.$h((string)($edit['labor_driver']??'norm')).'">';
 echo '</form>';
}
if($edit===null){
 echo '<form id="calc-inline-new" method="post" action="'.$workbenchUrl.'"><input type="hidden" name="draft_id" value="'.$draft.'"><input type="hidden" name="csrf" value="'.$h($csrf).'">';
 if($collaborationEnabled)echo '<input type="hidden" name="expected_revision" value="'.$h($expectedRevision).'"><input type="hidden" name="reason" value="Nieuwe calculatieregel ingevoerd">';
 echo '<input type="hidden" name="line_id" value=""><input type="hidden" name="extra_labor_hours" value="">';
 if(getenv('OFFICE_CALC_LABOR_SCHEMA_VERIFIED')==='enabled')echo '<input type="hidden" name="labor_driver" value="norm">';
 echo '</form>';
}
if($guidedPreset!==null||$conflict!==''){echo '<section id="calc-line-editor" class="panel line-editor'.($edit!==null||$guidedPreset!==null?' is-editing':'').($conflict!==''?' has-conflict':'').'"><h2>'.($edit===null?'Nieuwe calculatieregel':'Calculatieregel wijzigen').'</h2>';
if($edit!==null&&(($edit['material_loss_percent']??null)!==null||($edit['extra_labor_hours']??null)!==null)){
    echo '<p role="alert">Deze regel heeft een opgeslagen calculatiecorrectie. Nettohoeveelheid: '.$h((string)$edit['net_quantity_decimal']).'; materiaalverlies: '.$h((string)($edit['material_loss_percent']??'-')).'%; extra arbeidsuren: '.$h((string)($edit['extra_labor_hours']??'-')).'. Controleer de nettohoeveelheid en correctie voordat je opslaat.</p>';
}
if($conflict!=='')echo '<p role="alert" style="padding:16px;background:#fff0bf">'.$h($conflict).'</p>';
echo '<form method="post" action="'.$workbenchUrl.'">';
echo '<input type="hidden" name="draft_id" value="'.$draft.'">';
echo '<input type="hidden" name="csrf" value="'.$h($csrf).'">';
if($collaborationEnabled)echo '<input type="hidden" name="expected_revision" value="'.$h($expectedRevision).'"><label>Wijzigingsreden <input name="reason" maxlength="1000" required value="'.($conflict!==''?$h((string)($_POST['reason']??'Calculatieregel gewijzigd')):'Calculatieregel gewijzigd').'"></label>';
echo '<input type="hidden" name="line_id" value="'.$editIdValue.'">';
echo '<div class="calc-fields">';
echo '<label>Omschrijving <input name="description" maxlength="500" value="'.$editDescription.'" required></label>';
echo '<label>Type <select name="cost_type">';
foreach(['labor'=>'Arbeid','material'=>'Materiaal','equipment'=>'Materieel','subcontract'=>'Onderaanneming','other'=>'Overig'] as $key=>$label) {
    echo '<option value="'.$key.'"'.($selectedCost===$key?' selected':'').'>'.$label.'</option>';
}
echo '</select></label>';
echo '<label>Aantal <input type="number" min="'.($collaborationEnabled?'0.0001':'1').'" step="'.($collaborationEnabled?'0.0001':'1').'" name="quantity" value="'.$editQuantity.'" required></label>';
if($collaborationEnabled)echo '<label>Eenheid <input name="unit" maxlength="32" value="'.$editUnit.'" required></label>';
echo '<label>Prijs per eenheid (€) <input type="text" inputmode="decimal" pattern="[0-9]+([,.][0-9]{1,'.($collaborationEnabled?'4':'2').'})?" name="unit_price_eur" value="'.$editPrice.'" placeholder="45,00" required></label>';
if($collaborationEnabled&&getenv('OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED')==='enabled')echo '<fieldset><legend>Optionele calculatiecorrectie</legend><p class="muted">Materiaalverlies alleen bij type Materiaal; extra uren alleen bij type Arbeid. De ingevoerde hoeveelheid is de netto-/basishoeveelheid. Correcties worden bij opslaan doorgerekend en in de revisiereden vastgelegd.</p><label>Materiaalverlies (%) <input type="text" inputmode="decimal" name="material_loss_percent" value="'.$h((string)($edit['material_loss_percent']??'')).'" placeholder="bijv. 8"></label> <label>Extra arbeidsuren <input type="text" inputmode="decimal" name="extra_labor_hours" value="'.$h((string)($edit['extra_labor_hours']??'')).'" placeholder="bijv. 12"></label></fieldset>';
echo '</div><button type="submit">'.($edit===null?'Regel toevoegen':'Wijzigingen opslaan').'</button>';
echo ' <a href="'.$workbenchUrl.'?draft_id='.$draft.'">Sluiten</a>';
echo '</form></section>';}
echo '</div><details class="calc-data-secondary"><summary>Kostenverdeling · details tonen</summary><section class="panel"><h2>Kostenverdeling</h2><dl>';
foreach($typeLabels as $key=>$label) echo '<dt>'.$h($label).'</dt><dd>€ '.number_format($collaborationEnabled?(float)$categoryTotals[$key]:$categoryTotals[$key]/100,2,',','.').'</dd>';
echo '</dl><p class="muted">Opslagen, verkoopprijs, hoofdstukken en versiebeheer volgen in de volgende fase. De huidige bedragen zijn directe kosten.</p></section></details>';
echo '<details class="calc-data-secondary"><summary>Vraaggestuurd calculeren · voorstellen tonen</summary><section class="panel"><h2>Vraaggestuurd calculeren</h2><p class="muted">Kies een voorziening. Controleer de hoeveelheid en prijs voordat je de voorgestelde regel opslaat.</p><div class="guided-questions">';
foreach($guidedOptions as $key=>$option)echo '<a class="guided-choice" href="'.$workbenchUrl.'?draft_id='.$draft.'&amp;guided='.rawurlencode($key).'#calc-line-editor">'.$h($option[0]).' · + Voorstel</a>';
echo '</div><p class="muted">Geen automatische boeking. Bij materiaalverlies wordt alleen een aanvullende materiaalpost voorgesteld; een procentuele berekening op de nettohoeveelheid uit Gebouwen is nog niet gekoppeld. Gebouwhoeveelheden en tarieven zijn nog niet gekoppeld aan deze vragen.</p></section></details>';
if($materialWarning!=='')echo '<section class="panel"><p role="status">'.$h($materialWarning).'</p></section>';
echo '<details class="calc-support-panel"><summary>Revisies, samenwerking en wijzigingen</summary>';
require __DIR__.'/office-calc-crm-collaboration-panels.php';
echo '</details>';
echo '</div><div id="panel-tail" class="calc-tab-panel" role="tabpanel" aria-labelledby="tab-tail" hidden><section id="calc-tail" class="panel"><h2>Staart · kostenoverzicht</h2><p>Directe kostprijs: <strong>€ '.number_format((float)$canonicalTotal,2,',','.').'</strong></p><p class="muted">Staartopslagen en MAMO-verdeling worden hier gekoppeld. Er worden nog geen opslagen berekend.</p></section></div></div></div><script>
(function(){
  "use strict";
  const wb=document.getElementById("calc-workbench"),panel=document.getElementById("calc-elements");
  const toggle=document.getElementById("calc-toggle-elements"),pin=document.getElementById("calc-pin-elements"),popout=document.getElementById("calc-popout-elements");
  const tabs=Array.from(document.querySelectorAll("[data-calc-tab]"));
  const selectTab=name=>{for(const tab of tabs){const active=tab.dataset.calcTab===name;tab.setAttribute("aria-selected",String(active));const target=document.getElementById("panel-"+tab.dataset.calcTab);if(target)target.hidden=!active;}try{sessionStorage.setItem("brebo_calc_active_tab",name);}catch(e){}};
  tabs.forEach(tab=>tab.addEventListener("click",()=>selectTab(tab.dataset.calcTab)));
  let initial="data";try{initial=sessionStorage.getItem("brebo_calc_active_tab")||"data";}catch(e){}selectTab(tabs.some(t=>t.dataset.calcTab===initial)?initial:"data");
  let pinned=localStorage.getItem("brebo_calc_elements_pinned")!=="false";
  let visible=localStorage.getItem("brebo_calc_elements_visible")!=="false";
  const render=()=>{if(!wb||!panel)return;panel.hidden=!visible;panel.classList.toggle("calc-pinned",pinned);panel.classList.toggle("calc-unpinned",!pinned);wb.style.gridTemplateColumns=visible?"225px minmax(0,1fr)":"minmax(0,1fr)";toggle.textContent=visible?"Elementen inklappen":"Elementen tonen";toggle.setAttribute("aria-expanded",String(visible));pin.textContent=pinned?"📌 Vastgezet":"📍 Niet vastgezet";pin.setAttribute("aria-pressed",String(pinned));};
  toggle?.addEventListener("click",()=>{visible=!visible;localStorage.setItem("brebo_calc_elements_visible",String(visible));render();});
  pin?.addEventListener("click",()=>{pinned=!pinned;localStorage.setItem("brebo_calc_elements_pinned",String(pinned));render();});
  popout?.addEventListener("click",()=>{const w=window.open("","calc-elements","width=460,height=760");if(!w){alert("Sta pop-ups toe om de elementenlijst los te openen.");return;}const title=w.document.createElement("h2");title.textContent="Elementen · Bouwkosten";const list=panel.querySelector("ul");w.document.title="BREBO · Elementen";w.document.body.append(title,w.document.importNode(list,true));});
  render();
  // Prevent adjustment fields from a different cost type being submitted.
  for(const formId of ["calc-inline-new","calc-inline-edit"]){
    const form=document.getElementById(formId);
    if(!form)continue;
    const field=name=>document.querySelector(`[form="${formId}"][name="${name}"]`)||form.querySelector(`[name="${name}"]`);
    const type=field("cost_type"),loss=field("material_loss_percent"),extra=field("extra_labor_hours");
    if(!type)continue;
    const update=()=>{
      if(loss){
        const allowed=type.value==="material";
        if(!allowed)loss.value="";
        loss.disabled=!allowed;
      }
      if(extra){
        const allowed=type.value==="labor";
        if(!allowed)extra.value="";
        extra.disabled=!allowed;
      }
    };
    type.addEventListener("change",update);
    form.addEventListener("submit",update);
    update();
  }
  // Last edited labor cell determines the calculation; no separate selector.
  for(const formId of ["calc-inline-new","calc-inline-edit"]){
    const form=document.getElementById(formId);
    if(!form)continue;
    const field=name=>document.querySelector(`[form="${formId}"][name="${name}"]`)||form.querySelector(`[name="${name}"]`);
    const discipline=field("labor_rate_code"),hourly=field("labor_hourly_rate");
    if(discipline&&hourly){
      discipline.addEventListener("change",()=>{
        const option=discipline.options[discipline.selectedIndex];
        const rate=option?option.getAttribute("data-hourly-rate"):null;
        if(rate!==null)hourly.value=rate;
      });
    }
    const norm=field("labor_norm"),hours=field("labor_hours"),qty=field("quantity"),driver=field("labor_driver");
    if(!norm||!hours||!qty||!driver)continue;
    const format=n=>Number.isFinite(n)?String(Math.round((n+Number.EPSILON)*10000)/10000):"";
    const read=el=>Number(String(el.value).replace(",","."));
    const sync=()=>{
      const quantity=read(qty);
      if(!(quantity>0))return;
      if(driver.value==="hours"){
        if(hours.value.trim()!=="")norm.value=format(read(hours)/quantity);
      }else if(norm.value.trim()!==""){
        hours.value=format(read(norm)*quantity);
      }
    };
    norm.addEventListener("input",()=>{driver.value="norm";sync();});
    hours.addEventListener("input",()=>{driver.value="hours";sync();});
    qty.addEventListener("input",sync);
    form.addEventListener("submit",sync);
  }
  const search=document.getElementById("calc-element-search");
  const list=document.getElementById("calc-element-list");
  const status=document.getElementById("calc-element-search-status");
  if(!search||!list||!status)return;
  const items=Array.from(list.querySelectorAll("li"));
  const normalize=value=>value.toLocaleLowerCase("nl").normalize("NFD").replace(/[\\u0300-\\u036f]/g,"").trim();
  const filter=()=>{
    const query=normalize(search.value);
    let visible=0;
    for(const item of items){
      const match=normalize(item.textContent||"").includes(query);
      item.hidden=!match;
      if(match)visible++;
    }
    status.textContent=query?(visible+" van "+items.length+" elementen gevonden"):"";
  };
  search.addEventListener("input",filter);
  search.addEventListener("keydown",event=>{if(event.key==="Escape"){search.value="";filter();}});
})();
</script></main></body></html>';
