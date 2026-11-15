<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);exit;}
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_MATERIALS_ENABLED')!=='enabled'){http_response_code(404);exit;}
if(!is_string(getenv('OFFICE_PUBLIC_HOST'))||!hash_equals((string)getenv('OFFICE_PUBLIC_HOST'),(string)($_SERVER['HTTP_HOST']??''))||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit;}
try{
 foreach(['OfficeMount','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider']as$class)require_once dirname(__DIR__).'/src/Intake/'.$class.'.php';
 foreach(['MaterialCatalogReadModel','MaterialImportRows','MaterialSourceReader','MaterialCatalogStore','PdoMaterialCatalogStore','MaterialCatalogPublisher','MaterialCatalogConnection','OfficeMaterialAccess','MaterialInvoiceSource','MaterialManagedExtraction','MaterialDocumentSource']as$class)require_once dirname(__DIR__).'/src/Materials/'.$class.'.php';
 require_once dirname(__DIR__).'/src/Finance/OfficeFinanceAccess.php';
 foreach(['OfficePrivateFileStorage','OfficeDocumentRegistry','OfficeDocumentAccessGate']as$class)require_once dirname(__DIR__).'/src/Documents/'.$class.'.php';
 require_once dirname(__DIR__).'/src/Project/OfficeNativeProjectAccess.php';
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
 if($operator===null){header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302);exit;}
 $access=new \Brebo\Office\Materials\OfficeMaterialAccess($db);
 if(!$access->allows($operator->operatorId,'read')){http_response_code(403);exit('Geen toegang tot materialen.');}
 $canonical=\Brebo\Office\Materials\MaterialCatalogConnection::fromEnvironment();$prefix=getenv('OFFICE_MATERIALS_CANONICAL_TABLE_PREFIX')?:'';
 if(!preg_match('/^[A-Za-z0-9_]{0,32}$/D',$prefix))throw new RuntimeException('Ongeldig artikelbronprefix.');
 $suppliers=$canonical->query('SELECT id,name FROM `'.$prefix.'brebo_supplier` WHERE active=1 ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC);
 $supplierNames=array_column($suppliers,'name','id');$canImport=$access->allows($operator->operatorId,'import');
 if(session_status()!==PHP_SESSION_ACTIVE){session_name('brebo_material_review_test');session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/office-test']);session_start();}
 $_SESSION['material_csrf']??=bin2hex(random_bytes(32));$_SESSION['material_reviews']??=[];
 foreach($_SESSION['material_reviews']as$key=>$review)if(($review['expires']??0)<time()||($review['actor_id']??null)!==$operator->operatorId)unset($_SESSION['material_reviews'][$key]);
 $documentSource=static function()use($db,$access):\Brebo\Office\Materials\MaterialDocumentSource{
  $storage=new \Brebo\Office\Documents\OfficePrivateFileStorage((string)(getenv('OFFICE_DOCUMENT_STORAGE_ROOT')?:''));
  $gate=new \Brebo\Office\Documents\OfficeDocumentAccessGate(static function(string $actor,string $type,string $id,string $action)use($db,$access):bool{
   if($type==='brebo'&&$id==='material-catalog')return $access->allows($actor,'read');
   if($type==='project'&&ctype_digit($actor)&&ctype_digit($id))return (new \Brebo\Office\Project\OfficeNativeProjectAccess($db))->canRead((int)$actor,(int)$id);
   return false;
  });
  return new \Brebo\Office\Materials\MaterialDocumentSource($db,$storage,$gate,new \Brebo\Office\Materials\MaterialSourceReader(\Brebo\Office\Materials\MaterialManagedExtraction::fromEnvironment()));
 };
 $storeUpload=static function(string $path,string $filename,string $mime)use($db):int{
  // Fail before storing a binary when the central registry has not been installed.
  $db->query('SELECT id FROM office_documents LIMIT 0');
  $storage=new \Brebo\Office\Documents\OfficePrivateFileStorage((string)(getenv('OFFICE_DOCUMENT_STORAGE_ROOT')?:''));
  $stored=$storage->put($path);$registry=new \Brebo\Office\Documents\OfficeDocumentRegistry($db);
  $id=$registry->register($stored,$filename,$filename,$mime,'office_material_import');$registry->attachContext($id,'brebo','material-catalog','supporting');return $id;
 };
 $message='';$proposal=null;$reviewToken='';$page=max(0,(int)($_GET['page']??0));
 if(is_string($_GET['review_token']??null)){
  $reviewToken=$_GET['review_token'];$pending=$_SESSION['material_reviews'][$reviewToken]??null;
  if(is_array($pending)&&$pending['actor_id']===$operator->operatorId&&$pending['expires']>=time())$proposal=$pending['proposal'];
  else{$reviewToken='';$message='De materiaalreview is verlopen.';}
 }
 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  if(!$canImport){http_response_code(403);exit('Geen recht om materialen te importeren.');}
  $access->requireImport($operator->operatorId);
  if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['material_csrf'],$_POST['csrf'])){http_response_code(403);exit('Ongeldige materiaalreview.');}
  try{
   if(($_POST['action']??'')==='publish'){
    $reviewToken=(string)($_POST['review_token']??'');$review=$_SESSION['material_reviews'][$reviewToken]??null;
    if(!is_array($review)||$review['actor_id']!==$operator->operatorId||$review['expires']<time())throw new DomainException('De materiaalreview is verlopen. Lees de bron opnieuw in.');
    $supplierId=filter_var($_POST['supplier_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!is_int($supplierId)||!isset($supplierNames[$supplierId]))throw new DomainException('Kies een bestaande leverancier.');
    $visible=array_filter(explode(',',(string)($_POST['visible_rows']??'')),static fn(string $id):bool=>ctype_digit($id));
    if($visible===[]||!is_array($_POST['rows']??null))throw new DomainException('De review is onvolledig verzonden.');
    foreach($visible as$id){foreach(['article_code','description','price','unit','price_quantity','discount_percentage','supplier']as$field)if(!is_string($_POST['rows'][$id][$field]??null))throw new DomainException('De review is onvolledig verzonden. Verwerk minder regels tegelijk.');}
    $input=[];$selectedIds=[];
    foreach($review['proposal']['rows']as$i=>$original){
     $change=$_POST['rows'][$i]??[];if(!is_array($change)||!in_array((string)$i,$visible,true)||(($change['selected']??'')!=='1'&&($_POST['select_visible']??'')!=='1'))continue;
     $selectedIds[]=$i;
     $row=$original;
     foreach(['article_code','description','price','unit','price_quantity','discount_percentage','supplier']as$field)if(isset($change[$field])&&is_string($change[$field]))$row[$field]=$change[$field];
     if(trim((string)($row['supplier']??''))==='')$row['supplier']=$supplierNames[$supplierId];
     $row['currency']=(string)($_POST['currency']??'');$row['price_date']=(string)($_POST['price_date']??'');$row['number_format']=(string)($_POST['number_format']??'auto');
     $row['price_is_ex_vat']=($_POST['price_is_ex_vat']??'')==='1';$row['approved']=true;$input[]=$row;
    }
    $source=$review['proposal']['source'];
    if($source['kind']==='native_purchase_invoice')(new \Brebo\Office\Materials\MaterialInvoiceSource($db))->verify($operator->operatorId,$source);
    elseif($source['kind']==='office_document')$documentSource()->verify($operator->operatorId,$source);
    else throw new DomainException('Onbekende materiaalbron.');
    $publisher=new \Brebo\Office\Materials\MaterialCatalogPublisher(new \Brebo\Office\Materials\PdoMaterialCatalogStore($canonical,$prefix),new \Brebo\Office\Materials\MaterialImportRows(),static fn(string $actor):bool=>$access->allows($actor,'read')&&$access->allows($actor,'import'));
    $result=$publisher->publish($operator->operatorId,$supplierId,$source,$input,time());
    $remaining=$review['proposal'];foreach($selectedIds as$id)unset($remaining['rows'][$id]);
    unset($_SESSION['material_reviews'][$reviewToken]);$reviewToken='';
    if($remaining['rows']!==[]){$reviewToken=bin2hex(random_bytes(24));$proposal=$remaining;$_SESSION['material_reviews'][$reviewToken]=['actor_id'=>$operator->operatorId,'expires'=>time()+1800,'proposal'=>$remaining];$page=0;}
    $message=($result['status']==='already_imported'?'Deze selectie was al verwerkt. ':'Materialen en prijsversies opgeslagen. ').$result['records'].' regel(s). Bestaande vastgestelde calculaties blijven behouden.';
   }elseif(($_POST['action']??'')==='map'){
    $reviewToken=(string)($_POST['review_token']??'');$review=$_SESSION['material_reviews'][$reviewToken]??null;
    if(!is_array($review)||$review['actor_id']!==$operator->operatorId||$review['expires']<time())throw new DomainException('De materiaalreview is verlopen.');
    $original=$review['proposal'];$matrix=$original['matrix']??[];
    if(isset($original['unmapped_tables'])&&isset($_POST['table_index'])){
      $tableIndex=filter_var($_POST['table_index'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
      if(!is_int($tableIndex)||!isset($original['unmapped_tables'][$tableIndex]))throw new DomainException('Ongeldige werkbladkeuze.');
      $matrix=$original['unmapped_tables'][$tableIndex]['matrix'];
    }
    $first=filter_var($_POST['first_row']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
    if(!is_int($first)||!is_array($matrix))throw new DomainException('Kies de eerste gegevensregel.');
    $mapping=[];
    foreach((array)($_POST['mapping']??[])as$field=>$column){
      if($column==='')continue;
      $column=filter_var($column,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>16383]]);
      if(!is_int($column))throw new DomainException('Ongeldige bronkolom.');$mapping[$field]=$column;
    }
    if($mapping===[])throw new DomainException('Koppel minimaal één bronkolom.');
    $selected=array_filter($matrix,static fn($key):bool=>is_int($key)&&$key>=$first-1,ARRAY_FILTER_USE_KEY);
    $proposal=(new \Brebo\Office\Materials\MaterialImportRows())->proposeTable($selected,$mapping);
    $proposal['source']=$original['source'];$proposal['matrix']=$matrix;$proposal['unmapped_tables']=$original['unmapped_tables']??[];
    $_SESSION['material_reviews'][$reviewToken]['proposal']=$proposal;
   }elseif(($_POST['action']??'')==='preview'){
    $mode=(string)($_POST['source_mode']??'file');
    if($mode==='invoice'){
     $invoiceId=filter_var($_POST['invoice_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!is_int($invoiceId))throw new InvalidArgumentException('Geldig factuurnummer vereist.');
     $proposal=(new \Brebo\Office\Materials\MaterialInvoiceSource($db))->read($operator->operatorId,$invoiceId);
    }elseif($mode==='document'){
     $id=filter_var($_POST['document_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!is_int($id))throw new InvalidArgumentException('Geldig Office-documentnummer vereist.');$proposal=$documentSource()->read($operator->operatorId,$id);
    }elseif($mode==='file'){
     $file=$_FILES['source']??null;
     if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)$file['tmp_name'])||(int)$file['size']>20000000)throw new DomainException('Kies een geldig bestand van maximaal 20 MB.');
     $name=basename(str_replace('\\','/',(string)$file['name']));if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['csv','tsv','txt','xml','xlsx','docx','pdf','jpg','jpeg','png','webp','heic','heif'],true))throw new DomainException('Dit bronformaat wordt nog niet ondersteund.');
     $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);$id=$storeUpload((string)$file['tmp_name'],$name,(string)$mime);$proposal=$documentSource()->read($operator->operatorId,$id);
    }elseif($mode==='text'){
     $text=trim((string)($_POST['source_text']??''));if($text===''||strlen($text)>1000000)throw new DomainException('Vul maximaal 1 MB brontekst in.');
     $tmp=tempnam(sys_get_temp_dir(),'material-text-');if($tmp===false)throw new RuntimeException('Brontekst kon niet worden verwerkt.');
     try{file_put_contents($tmp,$text);$id=$storeUpload($tmp,'Materiaallijst.txt','text/plain');$proposal=$documentSource()->read($operator->operatorId,$id);}finally{@unlink($tmp);}
    }else throw new DomainException('Onbekende bronkeuze.');
    if(count($proposal['rows'])>10000)throw new DomainException('Deze bron bevat meer dan 10.000 reviewregels. Splits de bron voor een controleerbare import.');
    $reviewToken=bin2hex(random_bytes(24));
    while(count($_SESSION['material_reviews'])>=3)array_shift($_SESSION['material_reviews']);
    $_SESSION['material_reviews'][$reviewToken]=['actor_id'=>$operator->operatorId,'expires'=>time()+1800,'proposal'=>$proposal];
   }else throw new DomainException('Onbekende materiaalactie.');
  }catch(DomainException|InvalidArgumentException|OutOfBoundsException $error){http_response_code(422);$message=$error->getMessage();if(isset($review))$proposal=$review['proposal'];}
 }
 $catalogSearch=is_string($_GET['q']??null)?mb_substr($_GET['q'],0,120):'';
 $catalogSupplier=filter_var($_GET['catalog_supplier']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!is_int($catalogSupplier))$catalogSupplier=null;
 $catalogPage=max(0,min(100000,(int)($_GET['catalog_page']??0)));
 $catalogRows=(new \Brebo\Office\Materials\MaterialCatalogReadModel($canonical,$prefix))->search($catalogSearch,$catalogSupplier,(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d'),$catalogPage);
 $catalogHasMore=count($catalogRows)>50;$catalogRows=array_slice($catalogRows,0,50);$catalogAuthors=[];
 foreach($catalogRows as$row)if(is_string($row['actor_id']??null))$catalogAuthors[$row['actor_id']]=$row['actor_id'];
 if($catalogAuthors!==[]){$q=$db->prepare('SELECT id,username FROM office_operator_accounts WHERE id IN ('.implode(',',array_fill(0,count($catalogAuthors),'?')).')');$q->execute(array_keys($catalogAuthors));foreach($q->fetchAll(PDO::FETCH_ASSOC)as$author)$catalogAuthors[$author['id']]=$author['username'];}
}catch(Throwable $error){http_response_code(503);exit('Materialen zijn nog niet beschikbaar: controleer de canonieke artikelbron, rechten en het documentregister.');}
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Materialen en prijzen — BREBO Office</title><style>
*{box-sizing:border-box}body{margin:0;background:#f3f6f8;color:#203441;font:14px system-ui,sans-serif}main{max-width:1600px;margin:28px auto;padding:0 20px}header{display:flex;justify-content:space-between;align-items:center}a{color:#16625b}section{background:white;border:1px solid #d6e0e6;border-radius:8px;padding:20px;margin:18px 0}h1{font-size:25px}h2{font-size:18px}label{display:block;margin:10px 0}input,select,textarea,button{font:inherit;padding:8px;border:1px solid #bccbd4;border-radius:4px}textarea{width:100%;max-width:850px}button{background:#165d51;color:white;cursor:pointer}fieldset{border:1px solid #d8e1e6;padding:12px;margin:10px 0}.grid{display:flex;flex-wrap:wrap;gap:18px}.grid label{flex:1;min-width:160px}.grid label input,.grid label select{width:100%}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1200px}th,td{padding:7px;border:1px solid #e0e6ea;text-align:left;vertical-align:top}th{background:#edf3f5}td input{width:100%;min-width:80px}td:nth-child(4) input{min-width:230px}.notice{padding:12px;background:#eaf5ef}.warning{color:#88540c}.muted{color:#627681;font-size:12px}.source{font-size:12px;max-width:300px;overflow-wrap:anywhere}
</style></head><body><main><header><div><h1>Materialen en prijzen</h1><p>Controleer leveranciersartikelen uit prijslijsten, offertes en inkoopfacturen.</p></div><a href="/office-test">Office</a></header>
<?php if($message!==''):?><p class="notice" role="status"><?=$h($message)?></p><?php endif?>
<section><h2>Vaste materialenprijslijst</h2><p class="muted">Actuele basisprijzen in EUR exclusief btw per gebruikseenheid. Prijsversies uit eerdere importen blijven bewaard.</p>
<form method="get"><div class="grid"><label>Artikelcode, omschrijving of leverancier<input name="q" maxlength="120" value="<?=$h($catalogSearch)?>"></label><label>Leverancier<select name="catalog_supplier"><option value="">Alle leveranciers</option><?php foreach($suppliers as$supplier):?><option value="<?=$h($supplier['id'])?>"<?=$catalogSupplier===(int)$supplier['id']?' selected':''?>><?=$h($supplier['name'])?></option><?php endforeach?></select></label></div><button type="submit">Zoeken</button></form>
<div class="scroll"><table><thead><tr><th>Leverancier</th><th>Artikelcode</th><th>Omschrijving</th><th>Prijs excl. btw</th><th>Eenheid</th><th>Prijsdatum</th><th>Goedgekeurd door</th></tr></thead><tbody><?php foreach($catalogRows as$row):?><tr><td><?=$h($row['supplier'])?></td><td><?=$h($row['article_code'])?></td><td><?=$h($row['description'])?></td><td>€ <?=$h(number_format((float)$row['net_price'],4,',','.'))?></td><td><?=$h($row['unit'])?></td><td><?=$h($row['valid_from'])?></td><td><?=$h($catalogAuthors[$row['actor_id']]??'Bestaande artikelbron')?><?php if($row['reviewed_at']!==null):?><br><span class="muted"><?=$h((new DateTimeImmutable('@'.(int)$row['reviewed_at']))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i'))?></span><?php endif?></td></tr><?php endforeach?><?php if($catalogRows===[]):?><tr><td colspan="7">Geen actuele materialen gevonden voor deze selectie.</td></tr><?php endif?></tbody></table></div>
<p>Pagina <?=$catalogPage+1?> <?php foreach(['Vorige'=>$catalogPage-1,'Volgende'=>$catalogPage+1]as$label=>$target):if($target<0||($label==='Volgende'&&!$catalogHasMore))continue;?><a href="?<?=$h(http_build_query(['q'=>$catalogSearch,'catalog_supplier'=>$catalogSupplier,'catalog_page'=>$target]))?>"><?=$label?></a> <?php endforeach?></p></section>
<?php if($canImport):?><section><h2>Bron inlezen</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=$h($_SESSION['material_csrf'])?>"><input type="hidden" name="action" value="preview">
<label>Bron<select name="source_mode"><option value="file">Bestand uploaden</option><option value="text">Tekst plakken</option><option value="invoice"<?=isset($_GET['invoice_id'])?' selected':''?>>Bestaande inkoopfactuur</option><option value="document">Bestaand Office-document uit dossier of e-mail</option></select></label>
<label>Bestand<input type="file" name="source" accept=".csv,.tsv,.txt,.xml,.xlsx,.docx,.pdf,.jpg,.jpeg,.png,.webp,.heic,.heif"></label><p class="muted">Maximaal 20 MB. PDF en scans gebruiken de geconfigureerde Office-documentherkenning.</p>
<label>Brontekst<textarea name="source_text" rows="4"></textarea></label><div class="grid"><label>Inkoopfactuurnummer in Office<input name="invoice_id" inputmode="numeric" value="<?=$h($_GET['invoice_id']??'')?>"></label><label>Office-documentnummer<input name="document_id" inputmode="numeric" value="<?=$h($_GET['document_id']??'')?>"></label></div><button type="submit">Inlezen en controleren</button></form></section><?php endif?>
<?php if(is_array($proposal)):?><section><h2>Herkenning controleren</h2><p class="muted">Neem uitsluitend materiaalregels op. Transport, arbeid en onderaanneming horen niet automatisch in de materialenprijslijst.</p>
<?php foreach($proposal['issues']??[]as$issue):?><p class="warning"><?=$h($issue)?></p><?php endforeach?>
<?php if(!empty($proposal['matrix'])):
$matrix=$proposal['matrix'];$columns=[];foreach(array_slice($matrix,0,20,true)as$rawRow)foreach(array_keys($rawRow)as$column)$columns[(int)$column]=(int)$column;ksort($columns);
?><details<?=($proposal['rows']??[])===[]?' open':''?>><summary>Bronkolommen controleren of koppelen</summary><form method="post"><input type="hidden" name="csrf" value="<?=$h($_SESSION['material_csrf'])?>"><input type="hidden" name="action" value="map"><input type="hidden" name="review_token" value="<?=$h($reviewToken)?>">
<?php if(!empty($proposal['unmapped_tables'])):?><label>Werkblad<select name="table_index"><?php foreach($proposal['unmapped_tables']as$index=>$table):?><option value="<?=$index?>"><?=$h($table['sheet'])?></option><?php endforeach?></select></label><?php endif?>
<div class="scroll"><table><thead><tr><th>Bronregel</th><?php foreach($columns as$column):?><th>Kolom <?=$column+1?></th><?php endforeach?></tr></thead><tbody><?php foreach(array_slice($matrix,0,8,true)as$index=>$rawRow):?><tr><td><?=$index+1?></td><?php foreach($columns as$column):?><td><?=$h($rawRow[$column]??'')?></td><?php endforeach?></tr><?php endforeach?></tbody></table></div>
<div class="grid"><?php foreach(['supplier'=>'Leverancier','article_code'=>'Artikelcode','description'=>'Omschrijving','price'=>'Prijs','unit'=>'Eenheid','price_quantity'=>'Prijs per aantal','discount_percentage'=>'Korting']as$field=>$label):?><label><?=$label?><select name="mapping[<?=$field?>]"><option value="">Niet in de bron</option><?php foreach($columns as$column):?><option value="<?=$column?>"<?=isset($proposal['mapping'][$field])&&$proposal['mapping'][$field]===$column?' selected':''?>>Kolom <?=$column+1?></option><?php endforeach?></select></label><?php endforeach?></div>
<label>Eerste gegevensregel<input type="number" name="first_row" min="1" max="100000" value="1" required></label><button type="submit">Veldkoppeling toepassen</button></form></details>
<?php endif?>
<?php if(($proposal['rows']??[])!==[]):
$inputLimit=(int)ini_get('max_input_vars');$pageSize=$inputLimit<=0?100:min(100,max(1,intdiv(max(40,$inputLimit)-32,8)));$page=min($page,max(0,(int)ceil(count($proposal['rows'])/$pageSize)-1));$visibleRows=array_slice($proposal['rows'],$page*$pageSize,$pageSize,true);$firstRow=reset($proposal['rows']);
?><p><?=count($proposal['rows'])?> nog te beoordelen regel(s) · pagina <?=$page+1?> van <?=(int)ceil(count($proposal['rows'])/$pageSize)?></p><nav><?php if($page>0):?><a href="?review_token=<?=$h($reviewToken)?>&amp;page=<?=$page-1?>">Vorige regels</a> <?php endif?><?php if(($page+1)*$pageSize<count($proposal['rows'])):?><a href="?review_token=<?=$h($reviewToken)?>&amp;page=<?=$page+1?>">Volgende regels</a><?php endif?></nav>
<form method="post"><input type="hidden" name="visible_rows" value="<?=$h(implode(',',array_keys($visibleRows)))?>"><input type="hidden" name="csrf" value="<?=$h($_SESSION['material_csrf'])?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="review_token" value="<?=$h($reviewToken)?>">
<div class="grid"><label>Bestaande leverancier<select name="supplier_id" required><option value="">Kies leverancier…</option><?php foreach($suppliers as$supplier):?><option value="<?=$h($supplier['id'])?>"><?=$h($supplier['name'])?></option><?php endforeach?></select></label><label>Prijsdatum<input type="date" name="price_date" required value="<?=$h($firstRow['price_date']??date('Y-m-d'))?>"></label><label>Valuta<input name="currency" maxlength="3" value="<?=$h($firstRow['currency']??'EUR')?>" required></label><label>Getalnotatie<select name="number_format"><option value="auto">Automatisch, twijfel blijft open</option><option value="nl">Nederlands: 1.234,56</option><option value="international">Internationaal: 1,234.56</option></select></label></div>
<label><input type="checkbox" name="select_visible" value="1"> Alle getoonde regels zijn gecontroleerd materiaal en mogen worden opgenomen.</label><div class="scroll"><table><thead><tr><th>Materiaal opnemen</th><th>Leverancier uit bron</th><th>Artikelcode</th><th>Omschrijving</th><th>Prijs uit bron</th><th>Prijs per aantal</th><th>Eenheid</th><th>Korting %</th><th>Bron / controle</th></tr></thead><tbody>
<?php foreach($visibleRows as$i=>$row):?><tr><td><input aria-label="Materiaalregel <?=$i+1?> opnemen" type="checkbox" name="rows[<?=$i?>][selected]" value="1"></td><?php foreach(['supplier','article_code','description','price','price_quantity','unit','discount_percentage']as$field):?><td><input aria-label="<?=$h($field)?> regel <?=$i+1?>" name="rows[<?=$i?>][<?=$h($field)?>]" value="<?=$h($row[$field]??(in_array($field,['price_quantity'],true)?'1':($field==='discount_percentage'?'0':'')))?>"></td><?php endforeach?><td class="source"><?=$h($row['source_fragment']??('Bronregel '.($row['source_row']??$i+1)))?><?php foreach($row['issues']??[]as$issue):?><p class="warning"><?=$h($issue)?></p><?php endforeach?></td></tr><?php endforeach?>
</tbody></table></div><label><input type="checkbox" name="price_is_ex_vat" value="1" required> De geselecteerde prijzen, prijsbasis, korting en gebruikseenheden zijn gecontroleerd; de prijzen zijn exclusief btw.</label><button type="submit">Goedgekeurde materialen en prijsversies opslaan</button></form><?php endif?></section><?php endif?>
</main></body></html>
