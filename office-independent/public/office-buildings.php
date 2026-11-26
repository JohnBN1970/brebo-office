<?php
declare(strict_types=1);

// Read-only buildings linked to projects visible to the current Office operator.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src https://service.pdok.nl data:; base-uri 'none'; frame-ancestors 'none'");
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
  header('Allow: GET, POST');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(503);
  exit('Gebouwen zijn nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'OfficeMount.php',
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  $registerReady=getenv('OFFICE_BASE_PATH')==='/office-test' && getenv('OFFICE_BUILDING_REGISTER_GRANTS_SCHEMA_VERIFIED')==='enabled';
  $canCreate=false;
  $registerToken='';
  $registerError='';
  $newBuilding=[];
  if ($registerReady && getenv('OFFICE_BUILDING_CREATE_ENABLED')==='enabled') {
    $p=$db->prepare('SELECT can_edit_crm FROM office_operator_accounts WHERE id=? AND active=1');
    $p->execute([$operator->operatorId]);
    $secret=getenv('OFFICE_CL204_CSRF_SECRET');
    $canCreate=(int)$p->fetchColumn()===1 && is_string($secret) && strlen($secret)>=32 && strlen((string)$operator->sessionId)===64;
    if ($canCreate) $registerToken=hash_hmac('sha256','building-register-create',(string)$operator->sessionId.$secret);
  }
  if (($_SERVER['REQUEST_METHOD']??'')==='POST' && in_array(($_POST['building_action']??null),['create','search_pdok'],true)) {
    if (isset($_GET['id']) || !$canCreate || !is_string($_POST['csrf_token']??null) || !hash_equals($registerToken,$_POST['csrf_token'])) {http_response_code(403);exit;}
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateService.php';
    $newBuilding=is_array($_POST['new_building']??null)?$_POST['new_building']:[];
    unset($newBuilding['pdok_results']);
    if (($_POST['building_action']??null)==='search_pdok') {
        if (getenv('OFFICE_BUILDING_BAG_SCHEMA_VERIFIED')!=='enabled') {http_response_code(403);exit;}
        try {
            require_once dirname(__DIR__).'/src/Buildings/OfficePdokAddressLookup.php';
            require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateForm.php';
            $query=\Brebo\Office\Buildings\OfficeBuildingCreateForm::searchQuery($newBuilding);
            if (!is_string($query)) throw new DomainException('Invalid query');
            $newBuilding['pdok_results']=(new \Brebo\Office\Buildings\OfficePdokAddressLookup())->search($query);
            if ($newBuilding['pdok_results']===[]) $registerError='Geen BAG-adressen gevonden.';
        } catch (Throwable $e) {$registerError='PDOK zoeken mislukt. Controleer het adres of probeer opnieuw.';}
    } else
    try {
      (new \Brebo\Office\Buildings\OfficeBuildingCreateService($db))->createForOperator($newBuilding,(int)$operator->operatorId,static fn():bool=>$canCreate);
      header('Location: /office-test/gebouwen',true,303);exit;
    } catch (DomainException $e) {
      $registerError=$e->getMessage()==='building_address_candidate_exists'?'Dit adres bestaat mogelijk al.':'Ongeldige gebouwgegevens.';
    } catch (Throwable $e) {$registerError='Gebouw aanmaken mislukt.';}
  }
  $statement = $db->prepare(
    "SELECT b.id, b.building_name, b.street_address, b.postal_code, b.city,
            COUNT(DISTINCT pb.project_id) AS project_count,
            0 AS active_zone_count
       FROM office_buildings b
       INNER JOIN office_project_buildings pb ON pb.building_id = b.id
       INNER JOIN office_project_memberships m ON m.project_id = pb.project_id
       INNER JOIN office_operator_accounts a ON a.id = m.operator_id AND a.active = 1
      WHERE m.operator_id = ?
        AND m.permission IN ('read','manage')
      GROUP BY b.id, b.building_name, b.street_address, b.postal_code, b.city
      ORDER BY b.building_name, b.id LIMIT 200"
  );
  $statement->execute([$operator->operatorId]);
  $buildings = $statement->fetchAll(PDO::FETCH_ASSOC);
  if ($registerReady) {
    $q=$db->prepare('SELECT b.id,b.building_name,b.street_address,b.postal_code,b.city,0 AS project_count,0 AS active_zone_count FROM office_buildings b JOIN office_building_register_grants g ON g.building_id=b.id JOIN office_operator_accounts a ON a.id=g.operator_id AND a.active=1 WHERE g.operator_id=? AND b.active=1 ORDER BY b.building_name,b.id LIMIT 200');
    $q->execute([$operator->operatorId]);
    $known=array_column($buildings,'id');
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) if (!in_array($row['id'],$known,false)) $buildings[]=$row;
  }
  // Zone counts are optional: missing or unready zone schema must not hide buildings.
  if ($buildings !== []) {
    try {
      $zoneIds = array_map(static fn(array $building): int => (int)$building['id'], $buildings);
      $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
      $zoneStatement = $db->prepare(
        "SELECT building_id, COUNT(*) AS zone_count FROM office_building_zones
         WHERE active = 1 AND building_id IN ($placeholders) GROUP BY building_id"
      );
      $zoneStatement->execute($zoneIds);
      $counts = $zoneStatement->fetchAll(PDO::FETCH_KEY_PAIR);
      foreach ($buildings as &$building) {
        $building['active_zone_count'] = (int)($counts[$building['id']] ?? 0);
      }
      unset($building);
    } catch (PDOException $zoneException) {
      // The building listing remains available even before zone schema rollout.
      error_log('Office buildings: zone counts unavailable: ' . $zoneException->getCode());
    }
  }
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Gebouwenoverzicht tijdelijk niet beschikbaar.');
}
$buildingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$detail = isset($_GET['id']);
$selectedBuilding = null;
if ($detail) {
  if (!is_int($buildingId)) { http_response_code(404); exit('Gebouw niet gevonden.'); }
  foreach ($buildings as $candidate) {
    if ((int)$candidate['id'] === $buildingId) { $selectedBuilding = $candidate; break; }
  }
  if ($selectedBuilding === null && !isset($_GET['deal_id'])) { http_response_code(404); exit('Gebouw niet gevonden of niet toegankelijk.'); }
}
// Validate opname selection before writing any HTML, so invalid IDs return a real 404.
$requestedCl204OpnameId = null;
if ($detail && isset($_GET['opname_id'])) {
  $requestedCl204OpnameId = filter_input(INPUT_GET, 'opname_id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
  if (!is_int($requestedCl204OpnameId) || getenv('OFFICE_TEST_CL204_OPNAMES_READ_ENABLED') !== 'enabled') {
    http_response_code(404);
    exit('Opname niet gevonden.');
  }
  try {
    require_once dirname(__DIR__) . '/src/Process/OfficeProcessFormRepository.php';
    $requestedCl204Opname = (new \Brebo\Office\Process\OfficeProcessFormRepository($db))->loadBuildingOpname((int)$buildingId, $requestedCl204OpnameId);
    if ($requestedCl204Opname === null) { http_response_code(404); exit('Opname niet gevonden.'); }
  } catch (PDOException $e) {
    http_response_code(503);
    exit('Opnames tijdelijk niet beschikbaar.');
  }
}

if ($detail && isset($_GET['deal_id'])) {
  if(isset($_GET['opname_id'])){http_response_code(404);exit('Opname niet gevonden.');}
  // Deal-scoped access is deliberately restricted to the element register.
  if (isset($_GET['deal_id']) && getenv('OFFICE_BASE_PATH')==='/office-test' && getenv('OFFICE_BUILDING_ELEMENTS_SCHEMA_VERIFIED')==='enabled') {
    try {
    $dealId=filter_input(INPUT_GET,'deal_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(is_int($dealId)){
      require_once dirname(__DIR__).'/src/Project/OfficeBuildingElementAccess.php';
      if((new \Brebo\Office\Project\OfficeBuildingElementAccess($db))->canManageBeforeProject((int)$operator->operatorId,$buildingId,$dealId)){
        $q=$db->prepare('SELECT id,building_name FROM office_buildings WHERE id=?');
        $q->execute([$buildingId]);
        $dealBuilding=$q->fetch(PDO::FETCH_ASSOC);
        if($dealBuilding){
          require_once dirname(__DIR__).'/src/Project/OfficeBuildingElementRepository.php';
          $secret=getenv('OFFICE_CL204_CSRF_SECRET');
          $session=(string)$operator->sessionId;
          if(!is_string($secret)||strlen($secret)<32||strlen($session)!==64){http_response_code(503);exit('Beveiligde opslag niet beschikbaar.');}
          $token=hash_hmac('sha256','deal-elements:'.$dealId.':'.$buildingId,$session.$secret);
          $url='/office-test/gebouwen?id='.$buildingId.'&deal_id='.$dealId;
          $repo=new \Brebo\Office\Project\OfficeBuildingElementRepository($db);
          if($_SERVER['REQUEST_METHOD']==='POST'){
            if(($_POST['action']??'')!=='create_building_element'){http_response_code(400);exit;}
            if(!is_string($_POST['csrf_token']??null)||!hash_equals($token,$_POST['csrf_token'])){http_response_code(403);exit;}
            try{
              $housingType=null;
              if(($_POST['housing_type_id']??'')!==''){
                $housingType=filter_var($_POST['housing_type_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                if(!is_int($housingType))throw new InvalidArgumentException('Ongeldig woningtype');
              }
              $repo->create($buildingId,$housingType,(string)($_POST['classification']??''),(string)($_POST['code']??''),(string)($_POST['description']??''),(string)($_POST['unit']??''),str_replace(',','.',(string)($_POST['quantity']??'')),'manual');
              header('Location: '.$url,true,303);exit;
            }catch(InvalidArgumentException){http_response_code(422);exit('Ongeldige bouwdeelgegevens.');}
          }
          $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
          header('Content-Type: text/html; charset=utf-8');
          echo '<!doctype html><html lang="nl"><meta charset="utf-8"><title>Bouwdelen</title><main><h1>'.$esc($dealBuilding['building_name']).' — Bouwdelen</h1><p>Deal #'.$dealId.'</p><ul>';
          foreach($repo->forBuilding($buildingId) as $element)echo '<li>'.$esc($element['classification_code'].' '.$element['description'].' — '.$element['quantity'].' '.$element['unit']).'</li>';
          $housingOptions='<option value="">Gebouwtotaal</option>';
          $q=$db->prepare('SELECT id,code,name FROM office_housing_types WHERE building_id=? ORDER BY name,id');
          $q->execute([$buildingId]);
          foreach($q->fetchAll(PDO::FETCH_ASSOC) as $housing)$housingOptions.='<option value="'.(int)$housing['id'].'">'.$esc($housing['code'].' · '.$housing['name']).'</option>';
          echo '</ul><form method="post" action="'.$esc($url).'"><input type="hidden" name="csrf_token" value="'.$esc($token).'"><input type="hidden" name="action" value="create_building_element"><p><label>Groep <input name="classification" pattern="[0-9]{2}" required></label></p><p><label>Code <input name="code" required></label></p><p><label>Omschrijving <input name="description" required></label></p><p><label>Eenheid <input name="unit" required></label></p><p><label>Scope <select name="housing_type_id">'.$housingOptions.'</select></label></p><p><label>Hoeveelheid voor gekozen scope <input name="quantity" type="number" min="0" step="0.0001" required></label></p><button>Opslaan</button></form></main></html>';exit;
        }
      }
    }
    http_response_code(403);exit('Geen toegang tot bouwdelen voor deze deal.');
    } catch (Throwable $e) { http_response_code(503);exit('Bouwdelen tijdelijk niet beschikbaar.'); }
  }

  http_response_code(403);exit('Geen toegang tot bouwdelen voor deze deal.');
}
// Read access to a building does not imply permission to change its opnames.
$cl204CanManage = false;
if ($detail && is_int($buildingId)) {
  $cl204Access = $db->prepare(
    "SELECT 1 FROM office_project_buildings pb
      JOIN office_project_memberships m ON m.project_id=pb.project_id
      WHERE pb.building_id=? AND m.operator_id=? AND m.permission='manage' LIMIT 1"
  );
  $cl204Access->execute([$buildingId, $operator->operatorId]);
  $cl204CanManage = (bool)$cl204Access->fetchColumn();
}
// CL-204 writes require explicit test-only activation and a session-bound HMAC CSRF token.
$cl204WriteEnabled = getenv('OFFICE_BASE_PATH') === '/office-test'
  && getenv('OFFICE_TEST_CL204_OPNAMES_WRITE_ENABLED') === 'enabled'
  && getenv('OFFICE_TEST_CL204_OPNAMES_READ_ENABLED') === 'enabled';
$cl204Secret = getenv('OFFICE_CL204_CSRF_SECRET');
$cl204SessionHash = isset($operator) ? (string)$operator->sessionId : '';
$cl204CsrfReady = $cl204CanManage && $cl204WriteEnabled && is_string($cl204Secret) && strlen($cl204Secret) >= 32
  && strlen($cl204SessionHash) === 64;
$cl204Csrf = $cl204CsrfReady
  ? hash_hmac('sha256', 'CL-204:building:' . (int)$buildingId, $cl204SessionHash . $cl204Secret)
  : '';
$elementsEnabled=getenv('OFFICE_BASE_PATH')==='/office-test'&&getenv('OFFICE_BUILDING_ELEMENTS_SCHEMA_VERIFIED')==='enabled';
$elementCsrf=$elementsEnabled&&$cl204CanManage&&is_string($cl204Secret)&&strlen($cl204Secret)>=32&&strlen($cl204SessionHash)===64
  ?hash_hmac('sha256','building-elements:'.(int)$buildingId,$cl204SessionHash.$cl204Secret):'';
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST'&&($_POST['action']??'')==='create_building_element') {
  if(!$detail||$elementCsrf===''||!is_string($_POST['csrf_token']??null)||!hash_equals($elementCsrf,$_POST['csrf_token'])){http_response_code(403);exit('Bouwdeel opslaan niet toegestaan.');}
  try{
    require_once dirname(__DIR__).'/src/Project/OfficeBuildingElementRepository.php';
    $housingType=null;
    if(($_POST['housing_type_id']??'')!==''){
      $housingType=filter_var($_POST['housing_type_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
      if(!is_int($housingType))throw new InvalidArgumentException('Ongeldig woningtype');
    }
    (new \Brebo\Office\Project\OfficeBuildingElementRepository($db))->create((int)$buildingId,$housingType,(string)($_POST['classification']??''),(string)($_POST['code']??''),(string)($_POST['description']??''),(string)($_POST['unit']??''),str_replace(',','.',(string)($_POST['quantity']??'')),'manual');
    header('Location: /office-test/gebouwen?id='.(int)$buildingId.'#building-elements',true,303);exit;
  }catch(InvalidArgumentException $e){http_response_code(422);exit('Ongeldige bouwdeelgegevens.');}
  catch(Throwable $e){http_response_code(503);exit('Bouwdeel niet opgeslagen.');}
}
 $editToken='';
 if ($detail && $selectedBuilding!==null && $registerReady && $canCreate) {
   $q=$db->prepare('SELECT 1 FROM office_building_register_grants WHERE building_id=? AND operator_id=?');
   $q->execute([$buildingId,$operator->operatorId]);
   if ($q->fetchColumn()) $editToken=hash_hmac('sha256','building-register-update:'.$buildingId,$registerToken);
 }
 if (($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['building_action']??null)==='update') {
   if ($editToken==='' || !is_string($_POST['csrf_token']??null) || !hash_equals($editToken,$_POST['csrf_token']) || !is_array($_POST['building']??null) || !is_string($_POST['revision']??null)) {http_response_code(403);exit;}
   require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingUpdateService.php';
   try {
     (new \Brebo\Office\Buildings\OfficeBuildingUpdateService($db))->update((int)$buildingId,(int)$operator->operatorId,$_POST['building'],$_POST['revision']);
     header('Location: /office-test/gebouwen?id='.(int)$buildingId,true,303);exit;
   } catch (Throwable $e) {http_response_code(409);exit('Wijziging niet opgeslagen. Controleer het adres en ververs de pagina.');}
 }
$bagRow=null;
$bagToken='';
if ($detail && $selectedBuilding!==null && $registerReady && getenv('OFFICE_BUILDING_BAG_SCHEMA_VERIFIED')==='enabled') {
  require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingPdokLocationRepository.php';
  $q=$db->prepare('SELECT building_name,street_address,address_line,postal_code,city,latitude,longitude,bag_id,bag_verified_at FROM office_buildings WHERE id=? AND active=1');
  $q->execute([$buildingId]);$bagRow=$q->fetch(PDO::FETCH_ASSOC)?:null;
  if ($canCreate && $bagRow!==null) {
    $q=$db->prepare("SELECT 1 FROM office_buildings b WHERE b.id=? AND (EXISTS (SELECT 1 FROM office_building_register_grants g WHERE g.building_id=b.id AND g.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_buildings pb JOIN office_project_memberships m ON m.project_id=pb.project_id WHERE pb.building_id=b.id AND m.operator_id=? AND m.permission='manage'))");
    $q->execute([$buildingId,$operator->operatorId,$operator->operatorId]);
    if ($q->fetchColumn()) $bagToken=hash_hmac('sha256','building-bag:'.$buildingId,$registerToken);
  }
}
$zoneToken='';
if ($bagToken!=='' && getenv('OFFICE_BUILDING_ZONES_SCHEMA_VERIFIED')==='enabled' && getenv('OFFICE_BUILDING_ZONE_WRITE_ENABLED')==='enabled') {
  $zoneToken=hash_hmac('sha256','building-zone-create:'.$buildingId,$bagToken);
}
if (($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['building_action']??null)==='create_zone') {
  if ($zoneToken==='' || !is_string($_POST['csrf_token']??null) || !hash_equals($zoneToken,$_POST['csrf_token'])) {http_response_code(403);exit;}
  require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingZoneRepository.php';
  try {
    (new \Brebo\Office\Buildings\OfficeBuildingZoneRepository($db))->create([
      'building_id'=>(string)$buildingId,'zone_name'=>$_POST['zone_name']??null,
      'latitude'=>$_POST['latitude']??null,'longitude'=>$_POST['longitude']??null,
      'radius_metres'=>$_POST['radius_metres']??null
    ],static fn(string $id):bool=>$zoneToken!=='' && $id===(string)$buildingId);
    header('Location: /office-test/gebouwen?id='.(int)$buildingId,true,303);exit;
  } catch (Throwable $e) {http_response_code(422);exit('Klokzone niet opgeslagen. Controleer de invoer.');}
}
if (($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['building_action']??null)==='link_bag') {
  if ($bagToken==='' || !is_string($_POST['csrf_token']??null) || !hash_equals($bagToken,$_POST['csrf_token']) || !is_string($_POST['bag_id']??null) || !is_string($_POST['revision']??null)) {http_response_code(403);exit;}
  try {
    (new \Brebo\Office\Buildings\OfficeBuildingPdokLocationRepository($db))->linkVerified((int)$operator->operatorId,(int)$buildingId,$_POST['bag_id'],$_POST['revision']);
    header('Location: /office-test/gebouwen?id='.(int)$buildingId,true,303);exit;
  } catch (Throwable $e) {http_response_code(409);exit('BAG-koppeling niet opgeslagen. Controleer adres en rechten.');}
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !in_array($_POST['building_action']??null,['create','search_pdok'],true)) {
  if (!$detail || !$cl204CsrfReady || !is_string($_POST['csrf_token'] ?? null)
    || !hash_equals($cl204Csrf, $_POST['csrf_token'])) {
    http_response_code(403);
    exit('Opname opslaan niet toegestaan.');
  }
  try {
    require_once dirname(__DIR__) . '/src/Process/OfficeCl204BuildingDraftService.php';
    $formId = filter_var($_POST['form_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $revision = filter_var($_POST['revision'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (isset($_POST['form_id']) && !is_int($formId)) throw new InvalidArgumentException('Ongeldig opname-ID');
    if (isset($_POST['form_id']) && !is_int($revision)) throw new InvalidArgumentException('Ongeldige revisie');
    if (!isset($_POST['form_id']) && isset($_POST['revision'])) throw new InvalidArgumentException('Onverwachte revisie');
    $reason = $_POST['reason'] ?? '';
    if (!is_string($reason)) throw new InvalidArgumentException('Ongeldige reden');
    $saved = (new \Brebo\Office\Process\OfficeCl204BuildingDraftService($db))->save(
      (int)$buildingId, (int)$operator->operatorId, $_POST, $reason,
      is_int($formId) ? $formId : null, is_int($revision) ? $revision : null
    );
    header('Location: /office-test/gebouwen?id=' . (int)$buildingId . '&opname_id=' . (int)$saved['id'], true, 303);
    exit;
  } catch (InvalidArgumentException | RuntimeException $saveError) {
    http_response_code(422);
    exit('Opname niet opgeslagen: ' . htmlspecialchars($saveError->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
  } catch (Throwable $saveError) {
    error_log('CL-204 save failure: ' . $saveError->getCode());
    http_response_code(503);
    exit('Opname tijdelijk niet beschikbaar.');
  }
}
// PDOK persistence readiness is advisory only; never block the building page.
$pdokStorageReady = false;
$pdokStorageMissing = [];
if ($detail) {
  try {
    require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingPdokLocationRepository.php';
    $pdokStorageFields = (new \Brebo\Office\Buildings\OfficeBuildingPdokLocationRepository($db))->readiness();
    $pdokStorageMissing = array_keys(array_filter($pdokStorageFields, static fn(bool $ready): bool => !$ready));
    $pdokStorageReady = $pdokStorageMissing === [];
  } catch (Throwable $pdokReadinessError) {
    error_log('Office buildings: PDOK storage readiness unavailable');
    $pdokStorageMissing = ['schema-inspectie'];
  }
}
// PDOK lookup is read-only and intentionally isolated from the building query.
// Do not use PDOK availability as a prerequisite for showing an authorized building.
$pdokResults = [];
$pdokNotice = '';
$pdokQuery = '';
if ($detail && isset($_GET['pdok_q'])) {
  $pdokQuery = trim((string)$_GET['pdok_q']);
  if (strlen($pdokQuery) < 3 || strlen($pdokQuery) > 160) {
    $pdokNotice = 'Vul een adres in van 3 tot 160 tekens.';
  } else {
    try {
      $url = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free?' . http_build_query([
        'q' => $pdokQuery, 'rows' => 10, 'fq' => 'bron:BAG',
        'fl' => 'id,weergavenaam,type,centroide_ll,postcode,woonplaatsnaam',
      ]);
      $context = stream_context_create(['http' => [
        'timeout' => 5, 'header' => "Accept: application/json\r\nUser-Agent: BREBO-Office/1.0\r\n",
      ]]);
      $json = @file_get_contents($url, false, $context);
      if ($json === false || strlen($json) > 200000) {
        throw new RuntimeException('pdok_response_unavailable');
      }
      $payload = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
      foreach (($payload['response']['docs'] ?? []) as $doc) {
        if (!is_array($doc) || !preg_match('/^POINT\\s*\\(\\s*(-?\\d+(?:\\.\\d+)?)\\s+(-?\\d+(?:\\.\\d+)?)\\s*\\)$/i', (string)($doc['centroide_ll'] ?? ''), $m)) {
          continue;
        }
        $lon = (float)$m[1];
        $lat = (float)$m[2];
        if ($lat < 50 || $lat > 54 || $lon < 3 || $lon > 8) continue;
        $pdokResults[] = ['id' => (string)($doc['id'] ?? ''), 'bag_id'=>(string)($doc['nummeraanduiding_id']??''), 'label' => (string)($doc['weergavenaam'] ?? ''), 'lat' => $lat, 'lon' => $lon];
      }
      if ($pdokResults === []) $pdokNotice = 'Geen bruikbare BAG-locatie gevonden.';
    } catch (Throwable $pdokError) {
      error_log('Office buildings PDOK lookup unavailable');
      $pdokNotice = 'PDOK is tijdelijk niet bereikbaar. De gebouwgegevens blijven beschikbaar.';
    }
  }
}
$h = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gebouwen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}main{max-width:1100px;margin:32px auto;padding:24px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #dbe1e8}a{color:#175a91}@media(max-width:650px){section{overflow-x:auto}}</style></head><body><main><h1>' . ($detail ? $h($selectedBuilding['building_name']) : 'Gebouwen') . '</h1><p>' . ($detail ? '<a href="/office-test/gebouwen">← Terug naar gebouwen</a>' : 'Gebouwen waarvoor je toegangsrechten hebt.') . '</p>';
if ($detail) {
  echo '<section><h2>Gebouwgegevens</h2><dl><dt>Gebouw</dt><dd>' . $h($selectedBuilding['building_name']) . '</dd><dt>Adres</dt><dd>' . $h(trim(implode(' ', array_filter([$selectedBuilding['street_address'] ?? '', $selectedBuilding['postal_code'] ?? '', $selectedBuilding['city'] ?? ''])))) . '</dd><dt>Gekoppelde projecten</dt><dd>' . (int)$selectedBuilding['project_count'] . '</dd><dt>Actieve zones</dt><dd>' . (int)$selectedBuilding['active_zone_count'] . '</dd></dl></section>';
   if ($editToken!=='') {
     require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingUpdateService.php';
     echo '<section><details><summary>Gebouwgegevens wijzigen</summary><form method="post"><input type="hidden" name="building_action" value="update"><input type="hidden" name="csrf_token" value="'.$h($editToken).'"><input type="hidden" name="revision" value="'.$h(\Brebo\Office\Buildings\OfficeBuildingUpdateService::revision($selectedBuilding)).'">';
     foreach (['building_name'=>'Gebouwnaam','street_address'=>'Straat en huisnummer','postal_code'=>'Postcode','city'=>'Plaats'] as $key=>$label) echo '<p><label>'.$h($label).' <input name="building['.$key.']" value="'.$h($selectedBuilding[$key]??'').'"></label></p>';
     echo '<button type="submit">Wijzigingen opslaan</button></form></details></section>';
   }
  if($elementsEnabled){
    echo '<section id="building-elements"><h2>Bouwdelen</h2>';
    try{
      require_once dirname(__DIR__).'/src/Project/OfficeBuildingElementRepository.php';
      $elements=(new \Brebo\Office\Project\OfficeBuildingElementRepository($db))->forBuilding((int)$buildingId);
      echo '<table><thead><tr><th>Groep</th><th>Code</th><th>Omschrijving</th><th>Hoeveelheid</th><th>Scope</th></tr></thead><tbody>';
      foreach($elements as $element)echo '<tr><td>'.$h($element['classification_code']).'</td><td>'.$h($element['element_code']).'</td><td>'.$h($element['description']).'</td><td>'.$h($element['quantity']).' '.$h($element['unit']).'</td><td>'.($element['housing_type_id']===null?'Gebouwtotaal':'Woningtype #'.(int)$element['housing_type_id']).'</td></tr>';
      echo '</tbody></table>';
      if($elements===[])echo '<p>Geen bouwdelen geregistreerd.</p>';
      $housingOptions='<option value="">Gebouwtotaal</option>';
      $housingQuery=$db->prepare('SELECT id,code,name FROM office_housing_types WHERE building_id=? ORDER BY name,id');
      $housingQuery->execute([$buildingId]);
      foreach($housingQuery->fetchAll(PDO::FETCH_ASSOC) as $housing)$housingOptions.='<option value="'.(int)$housing['id'].'">'.$h($housing['code'].' · '.$housing['name']).'</option>';
      if($elementCsrf!=='')echo '<details><summary>Bouwdeel toevoegen</summary><form method="post" action="/office-test/gebouwen?id='.(int)$buildingId.'"><input type="hidden" name="action" value="create_building_element"><input type="hidden" name="csrf_token" value="'.$h($elementCsrf).'"><p><label>Groep <input name="classification" pattern="[0-9]{2}" maxlength="2" required></label> <label>Code <input name="code" maxlength="100" required></label></p><p><label>Omschrijving <input name="description" maxlength="255" required></label></p><p><label>Scope <select name="housing_type_id">'.$housingOptions.'</select></label></p><p><label>Eenheid <input name="unit" maxlength="30" required></label> <label>Hoeveelheid voor gekozen scope <input name="quantity" type="number" min="0" step="0.0001" required></label></p><button>Opslaan</button></form></details>';
    }catch(Throwable $e){echo '<p>Bouwdelen tijdelijk niet beschikbaar.</p>';}
    echo '</section>';
  }
  if ($editToken!=='' && getenv('OFFICE_BUILDING_AUDIT_SCHEMA_VERIFIED')==='enabled') {
    echo '<section><details><summary>Gebouwhistorie</summary>';
    try {
      $q=$db->prepare("SELECT e.event_type,e.before_json,e.after_json,e.created_at,a.username FROM office_building_audit_events e LEFT JOIN office_operator_accounts a ON a.id=e.operator_id WHERE e.building_id=? AND e.context_type='register' ORDER BY e.id DESC LIMIT 100");
      $q->execute([$buildingId]);
      $events=$q->fetchAll(PDO::FETCH_ASSOC);
      echo '<p>Laatste 100 registraties en wijzigingen.</p><table><thead><tr><th>Wanneer</th><th>Wie</th><th>Wat</th><th>Wijziging</th></tr></thead><tbody>';
      foreach ($events as $event) {
        $before=json_decode((string)$event['before_json'],true)?:[];
        $after=json_decode((string)$event['after_json'],true)?:[];
        $changes=[];
        foreach (['building_name'=>'Gebouwnaam','street_address'=>'Adres','postal_code'=>'Postcode','city'=>'Plaats'] as $key=>$label) {
          if (($before[$key]??null)!==($after[$key]??null)) $changes[]=$label.': '.($before[$key]??'—').' → '.($after[$key]??'—');
        }
        echo '<tr><td>'.$h($event['created_at']).'</td><td>'.$h($event['username']??'Onbekende medewerker').'</td><td>'.$h(['created'=>'Geregistreerd','updated'=>'Gewijzigd'][$event['event_type']]??$event['event_type']).'</td><td>'.$h(implode('; ',$changes)).'</td></tr>';
      }
      if (!$events) echo '<tr><td colspan="4">Geen geregistreerde historie.</td></tr>';
      echo '</tbody></table>';
    } catch (Throwable $e) {echo '<p>Historie tijdelijk niet beschikbaar.</p>';}
    echo '</details></section>';
  }
  // CL-204 register remains readable; authorized concept drafts may be revised.
  // Never interpret missing schema or inaccessible data as an empty confirmed register.
  if (getenv('OFFICE_TEST_CL204_OPNAMES_READ_ENABLED') === 'enabled') {
    echo '<section class="office-building-opnames"><h2>Opnames</h2><p>CL-204 Projectopname. Inspecties blijven een afzonderlijk onderdeel.</p>';
    $selectedOpname = null;
    $cl204RegisterReady = false;
    try {
      require_once dirname(__DIR__) . '/src/Process/OfficeProcessFormRepository.php';
      $opnames = (new \Brebo\Office\Process\OfficeProcessFormRepository($db))->listBuildingOpnames((int)$buildingId);
      $selectedOpname = $requestedCl204Opname ?? null;
      $cl204RegisterReady = true;
      if ($opnames === []) {
        echo '<p>Voor dit gebouw zijn nog geen CL-204-opnames geregistreerd.</p>';
      } else {
        echo '<table><thead><tr><th>Opname</th><th>Formulierstatus</th><th>Revisie</th><th>Bijgewerkt</th></tr></thead><tbody>';
        foreach ($opnames as $opname) {
          echo '<tr><td><a href="/office-test/gebouwen?id=' . (int)$buildingId . '&amp;opname_id=' . (int)$opname['id'] . '">CL-204 #' . (int)$opname['id'] . '</a></td><td>' . $h($opname['status']) . '</td><td>' . (int)$opname['revision'] . '</td><td>' . $h($opname['updated_at']) . '</td></tr>';
        }
        echo '</tbody></table>';
      }
    } catch (PDOException $opnameError) {
      error_log('Office building CL-204 opnames unavailable: ' . $opnameError->getCode());
      echo '<p role="status">Opnames zijn tijdelijk niet beschikbaar; opslag of toegang moet nog worden gecontroleerd.</p>';
    }
    if ($cl204RegisterReady && $selectedOpname !== null) {
      echo '<section class="office-cl204-readonly"><h3>Opname CL-204 #' . (int)$selectedOpname['id'] . '</h3><p>Status: ' . $h($selectedOpname['status']) . ' · Revisie ' . (int)$selectedOpname['revision'] . '</p>';
      if ($cl204CsrfReady && $selectedOpname['status'] === 'concept') {
        echo '<p><a href="#cl204-editor">Bewerken / nieuwe revisie</a></p>';
      }
      require_once dirname(__DIR__) . '/src/Process/OfficeCl204Projectopname.php';
      $controlData = is_array($selectedOpname['data']['controls'] ?? null) ? $selectedOpname['data']['controls'] : [];
      echo '<table><thead><tr><th>Controle</th><th>Stand</th></tr></thead><tbody>';
      foreach (\Brebo\Office\Process\OfficeCl204Projectopname::controls() as $controlKey => $definition) {
        echo '<tr><td>' . $h($definition['label']) . '</td><td>' . $h($controlData[$controlKey]['status'] ?? 'Niet vastgelegd') . '</td></tr>';
      }
      echo '</tbody></table><h4>Disciplines</h4><p>' . $h(implode(', ', array_filter($selectedOpname['data']['disciplines'] ?? [], 'is_string'))) . '</p></section>';
      // History is read-only and scoped to this authorized building and opname.
      try {
        $history = (new \Brebo\Office\Process\OfficeProcessFormRepository($db))->listBuildingOpnameHistory((int)$buildingId, (int)$selectedOpname['id']);
        echo '<details class="office-cl204-history"><summary>Revisiehistorie (' . count($history) . ' gebeurtenissen)</summary>';
        $previousSnapshot = null;
        foreach ($history as $event) {
          $snapshot = json_decode((string)$event['snapshot_json'], true);
          $currentData = is_array($snapshot) ? (isset($snapshot['data']) && is_array($snapshot['data']) ? $snapshot['data'] : $snapshot) : [];
          echo '<article><h4>Revisie ' . (int)$event['revision'] . ' · ' . $h($event['event_type']) . '</h4>';
          echo '<p>Datum: ' . $h($event['created_at']) . ' · Medewerker: ' . $h($event['actor_operator_id']) . '</p>';
          echo '<p>Reden: ' . $h($event['reason']) . '</p>';
          if ($previousSnapshot !== null && in_array($event['event_type'], ['revised','created'], true)) {
            $before = is_array($previousSnapshot['controls'] ?? null) ? $previousSnapshot['controls'] : [];
            $after = is_array($currentData['controls'] ?? null) ? $currentData['controls'] : [];
            $changes = [];
            foreach (\Brebo\Office\Process\OfficeCl204Projectopname::controls() as $key => $definition) {
              $old = (string)($before[$key]['status'] ?? 'onbekend');
              $new = (string)($after[$key]['status'] ?? 'onbekend');
              if ($old !== $new) $changes[] = $h($definition['label']) . ': ' . $h($old) . ' → ' . $h($new);
            }
            $oldDisciplines = $previousSnapshot['disciplines'] ?? [];
            $newDisciplines = $currentData['disciplines'] ?? [];
            if ($oldDisciplines !== $newDisciplines) $changes[] = 'Disciplines gewijzigd';
            echo $changes === [] ? '<p>Geen wijzigingen in controle-statussen of disciplines.</p>' : '<ul><li>' . implode('</li><li>', $changes) . '</li></ul>';
          }
          $previousSnapshot = $currentData;
          echo '</article>';
        }
        echo '</details>';
      } catch (\PDOException $historyError) {
        error_log('CL-204 history unavailable: ' . $historyError->getCode());
        echo '<p role="status">Revisiehistorie tijdelijk niet beschikbaar.</p>';
      }
    }
    if ($cl204RegisterReady && $cl204CsrfReady) {
      require_once dirname(__DIR__) . '/src/Process/OfficeCl204FormView.php';
      $draft = $selectedOpname !== null && $selectedOpname['status'] === 'concept' ? $selectedOpname['data'] : [];
      if ($selectedOpname === null || $selectedOpname['status'] === 'concept') {
        echo '<div id="cl204-editor" class="office-cl204-editor"><h3>' . ($selectedOpname !== null ? 'Conceptopname bewerken' : 'Nieuwe projectopname') . '</h3><p>' . ($selectedOpname !== null ? 'Wijzigingen worden als nieuwe revisie opgeslagen. Vermeld de reden van wijziging.' : 'Leg de opname vast als concept.') . '</p>';
        echo '<form method="post" action="/office-test/gebouwen?id=' . (int)$buildingId . '">';
        echo '<input type="hidden" name="csrf_token" value="' . $h($cl204Csrf) . '">';
        if ($selectedOpname !== null) {
          echo '<input type="hidden" name="form_id" value="' . (int)$selectedOpname['id'] . '">';
          echo '<input type="hidden" name="revision" value="' . (int)$selectedOpname['revision'] . '">';
        }
        echo \Brebo\Office\Process\OfficeCl204FormView::render($draft);
        echo '<label for="cl204_reason">' . ($selectedOpname !== null ? 'Reden van wijziging' : 'Reden van vastlegging') . '</label><input id="cl204_reason" name="reason" maxlength="500" required>';
        echo '<button type="submit">' . ($selectedOpname !== null ? 'Revisie opslaan' : 'Conceptopname opslaan') . '</button></form></div>';
      }
    } else {
      echo '<p>Opname wijzigen is nog niet geactiveerd.</p>';
    }
    echo '</section>';
  }
  echo '<section style="margin-top:20px"><h2>PDOK / BAG-locatie</h2><p>Zoek een officieel BAG-adres, zoals in SB Office. Selecteer een officieel adres om de locatie te bekijken of te koppelen.</p>';
  if ($pdokStorageReady) {
    echo '<p role="status">Opslagvelden beschikbaar.</p>';
  } else {
    echo '<p role="status">Permanente BAG-opslag nog niet beschikbaar: ontbrekende velden of controle: ' . $h(implode(', ', $pdokStorageMissing)) . '.</p>';
  }
  echo '<form method="get" action="/office-test/gebouwen"><input type="hidden" name="id" value="' . (int)$buildingId . '"><label for="pdok-q">Adres zoeken</label> <input id="pdok-q" name="pdok_q" maxlength="160" required value="' . $h($pdokQuery !== '' ? $pdokQuery : trim(implode(' ', array_filter([$selectedBuilding['street_address'] ?? '', $selectedBuilding['postal_code'] ?? '', $selectedBuilding['city'] ?? ''])))) . '" style="padding:9px;width:min(500px,80%)"> <button type="submit" style="padding:9px">Zoeken in BAG</button></form>';
  if ($pdokNotice !== '') echo '<p role="status">' . $h($pdokNotice) . '</p>';
  $selectedIndex = filter_input(INPUT_GET, 'pdok_choice', FILTER_VALIDATE_INT);
  if (!is_int($selectedIndex) || !array_key_exists($selectedIndex, $pdokResults)) $selectedIndex = 0;
  foreach ($pdokResults as $index => $result) {
    echo '<p><strong>' . $h($result['label']) . '</strong> — ' . $h($result['lat']) . ', ' . $h($result['lon']);
    if ($index === $selectedIndex) {
      echo ' <strong>(geselecteerd voor kaart)</strong>';
    } else {
      $choiceUrl = '/office-test/gebouwen?' . http_build_query([
        'id' => $buildingId, 'pdok_q' => $pdokQuery, 'pdok_choice' => $index,
      ]);
      echo ' <a href="' . $h($choiceUrl) . '">Toon op kaart</a>';
    }
    echo '</p>';
  }
  if ($bagRow!==null && !empty($bagRow['bag_id'])) echo '<p>Gekoppeld BAG-adres: '.$h($bagRow['bag_id']).' · geverifieerd '.$h($bagRow['bag_verified_at']).'</p>';
  if ($pdokResults !== [] || ($bagRow!==null && !empty($bagRow['bag_id']) && is_numeric($bagRow['latitude']) && is_numeric($bagRow['longitude']))) {
    $location = $pdokResults!==[]?$pdokResults[$selectedIndex]:['lat'=>$bagRow['latitude'],'lon'=>$bagRow['longitude']];
    if ($bagToken!=='' && preg_match('/^[0-9]{16}$/D',$location['bag_id']??'')) echo '<form method="post" action="/office-test/gebouwen?id='.(int)$buildingId.'"><input type="hidden" name="building_action" value="link_bag"><input type="hidden" name="csrf_token" value="'.$h($bagToken).'"><input type="hidden" name="revision" value="'.$h(\Brebo\Office\Buildings\OfficeBuildingPdokLocationRepository::revision($bagRow)).'"><input type="hidden" name="bag_id" value="'.$h($location['bag_id']).'"><button>BAG-adres koppelen</button></form>';
    $lat = (float)$location['lat']; $lon = (float)$location['lon'];
    echo '<p>Coördinaten: '.$h($lat).', '.$h($lon).'</p>';
    $r = 6378137.0; $x = $r * deg2rad($lon); $y = $r * log(tan(M_PI / 4 + deg2rad($lat) / 2));
    $bbox = implode(',', [$x - 300, $y - 173.333333, $x + 300, $y + 173.333333]);
    $mapUrl = 'https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0?' . http_build_query([
      'service' => 'WMS', 'version' => '1.3.0', 'request' => 'GetMap',
      'layers' => 'Actueel_orthoHR', 'styles' => '', 'crs' => 'EPSG:3857',
      'bbox' => $bbox, 'width' => 900, 'height' => 520,
      'format' => 'image/jpeg', 'transparent' => 'false',
    ]);
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingZoneMap.php';
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingZoneRepository.php';
    $zones=[];
    if (getenv('OFFICE_BUILDING_ZONES_SCHEMA_VERIFIED')==='enabled') {
      $zones=(new \Brebo\Office\Buildings\OfficeBuildingZoneRepository($db))->forBuilding((string)$buildingId,
        static fn(string $id):bool => $selectedBuilding!==null && $id===(string)$buildingId);
    }
    echo '<figure style="margin:20px 0 0"><svg viewBox="0 0 900 520" role="img" aria-label="PDOK-luchtfoto met actieve klokzones" style="width:100%;height:auto"><image href="'.$h($mapUrl).'" width="900" height="520"/>';
    foreach ($zones as $zone) {
      $geometry=\Brebo\Office\Buildings\OfficeBuildingZoneMap::circle((float)$zone['latitude'],(float)$zone['longitude'],(float)$zone['radius_metres'],[$x-300,$y-173.333333,$x+300,$y+173.333333],900,520);
      echo '<ellipse cx="'.$h($geometry['cx']).'" cy="'.$h($geometry['cy']).'" rx="'.$h($geometry['rx']).'" ry="'.$h($geometry['ry']).'" fill="#2684ff" fill-opacity=".18" stroke="#2684ff" stroke-width="3"><title>'.$h($zone['zone_name']).' · '.$h($zone['radius_metres']).' m</title></ellipse>';
    }
    echo '<circle cx="450" cy="260" r="5" fill="#fff" stroke="#175a91" stroke-width="3"/></svg><figcaption>PDOK-luchtfoto · actieve klokzones</figcaption></figure>';
    foreach ($zones as $zone) echo '<p>'.$h($zone['zone_name']).' · '.$h($zone['radius_metres']).' m</p>';
    if ($zoneToken!=='' && $bagRow!==null && is_numeric($bagRow['latitude']) && is_numeric($bagRow['longitude'])) {
      echo '<details><summary>+ Klokzone toevoegen</summary><form method="post" action="/office-test/gebouwen?id='.(int)$buildingId.'"><input type="hidden" name="building_action" value="create_zone"><input type="hidden" name="csrf_token" value="'.$h($zoneToken).'"><p><label>Naam <input name="zone_name" maxlength="160" required></label></p><p><label>Breedtegraad <input type="number" name="latitude" step="0.0000001" min="-85" max="85" required value="'.$h($bagRow['latitude']).'"></label></p><p><label>Lengtegraad <input type="number" name="longitude" step="0.0000001" min="-180" max="180" required value="'.$h($bagRow['longitude']).'"></label></p><p><label>Straal (meter) <input type="number" name="radius_metres" min="1" max="5000" step="0.01" value="150" required></label></p><button>Klokzone opslaan</button></form></details>';
    }
  }
  echo '</section>';
  echo '</main></body></html>';
  exit;
}
if ($canCreate) {
  require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCreateForm.php';
  echo '<section><details'.($registerError!=='' || $newBuilding!==[]?' open':'').'><summary>+ Nieuw gebouw</summary>';
  if ($registerError!=='') echo '<p role="alert">'.$h($registerError).'</p>';
  echo '<form method="post" action="/office-test/gebouwen"><input type="hidden" name="csrf_token" value="'.$h($registerToken).'">'.\Brebo\Office\Buildings\OfficeBuildingCreateForm::render($newBuilding).'</form></details></section>';
}
echo '<section>';
if ($buildings === []) {
  echo '<p>Geen toegankelijke gebouwen gevonden.</p>';
} else {
  echo '<table><thead><tr><th>Gebouw</th><th>Adres</th><th>Projecten</th><th>Actieve zones</th></tr></thead><tbody>';
  foreach ($buildings as $building) {
    $address = trim(implode(' ', array_filter([
      $building['street_address'] ?? '', $building['postal_code'] ?? '', $building['city'] ?? ''
    ], static fn($part) => is_string($part) && trim($part) !== '')));
    echo '<tr><td><a href="/office-test/gebouwen?id=' . (int)$building['id'] . '">' . $h($building['building_name']) . '</a></td><td>' . $h($address) .
      '</td><td>' . $h($building['project_count']) . '</td><td>' . $h($building['active_zone_count']) . '</td></tr>';
  }
  echo '</tbody></table>';
}
echo '</section></main></body></html>';
