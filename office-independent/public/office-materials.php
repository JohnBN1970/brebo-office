<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);exit;}
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_MATERIALS_ENABLED')!=='enabled'){http_response_code(404);exit;}
if(!is_string(getenv('OFFICE_PUBLIC_HOST'))||!hash_equals((string)getenv('OFFICE_PUBLIC_HOST'),(string)($_SERVER['HTTP_HOST']??''))||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit;}
try{
 foreach(['OfficeMount','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider']as$class)require_once dirname(__DIR__).'/src/Intake/'.$class.'.php';
 foreach(['MaterialImportRows','MaterialSourceReader','MaterialCatalogStore','PdoMaterialCatalogStore','MaterialCatalogPublisher','MaterialCatalogConnection','OfficeMaterialAccess','MaterialInvoiceSource','MaterialManagedExtraction','MaterialDocumentSource']as$class)require_once dirname(__DIR__).'/src/Materials/'.$class.'.php';
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
 $message='';$proposal=null;$reviewToken='';
 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  $access->requireImport($operator->operatorId);
  if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['material_csrf'],$_POST['csrf'])){http_response_code(403);exit('Ongeldige materiaalreview.');}
  try{
   if(($_POST['action']??'')==='publish'){
    $reviewToken=(string)($_POST['review_token']??'');$review=$_SESSION['material_reviews'][$reviewToken]??null;
    if(!is_array($review)||$review['actor_id']!==$operator->operatorId||$review['expires']<time())throw new DomainException('De materiaalreview is verlopen. Lees de bron opnieuw in.');
    $supplierId=filter_var($_POST['supplier_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!is_int($supplierId)||!isset($supplierNames[$supplierId]))throw new DomainException('Kies een bestaande leverancier.');
    $input=[];
    foreach($review['proposal']['rows']as$i=>$original){
     $change=$_POST['rows'][$i]??[];if(!is_array($change)||($change['selected']??'')!=='1')continue;
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
    unset($_SESSION['material_reviews'][$reviewToken]);$reviewToken='';
    $message=($result['status']==='already_imported'?'Deze selectie was al verwerkt. ':'Materialen en prijsversies opgeslagen. ').$result['records'].' regel(s). Bestaande vastgestelde calculaties blijven behouden.';
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
    if(count($proposal['rows'])>1000)throw new DomainException('Deze bron bevat meer dan 1000 reviewregels. Splits de bron voor een controleerbare import.');
    $reviewToken=bin2hex(random_bytes(24));
    while(count($_SESSION['material_reviews'])>=3)array_shift($_SESSION['material_reviews']);
    $_SESSION['material_reviews'][$reviewToken]=['actor_id'=>$operator->operatorId,'expires'=>time()+1800,'proposal'=>$proposal];
   }else throw new DomainException('Onbekende materiaalactie.');
  }catch(DomainException|InvalidArgumentException|OutOfBoundsException $error){http_response_code(422);$message=$error->getMessage();if(isset($review))$proposal=$review['proposal'];}
 }
}catch(Throwable $error){http_response_code(503);exit('Materialen zijn nog niet beschikbaar: controleer de canonieke artikelbron, rechten en het documentregister.');}
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Materialen en prijzen — BREBO Office</title><style>
*{box-sizing:border-box}body{margin:0;background:#f3f6f8;color:#203441;font:14px system-ui,sans-serif}main{max-width:1600px;margin:28px auto;padding:0 20px}header{display:flex;justify-content:space-between;align-items:center}a{color:#16625b}section{background:white;border:1px solid #d6e0e6;border-radius:8px;padding:20px;margin:18px 0}h1{font-size:25px}h2{font-size:18px}label{display:block;margin:10px 0}input,select,textarea,button{font:inherit;padding:8px;border:1px solid #bccbd4;border-radius:4px}textarea{width:100%;max-width:850px}button{background:#165d51;color:white;cursor:pointer}fieldset{border:1px solid #d8e1e6;padding:12px;margin:10px 0}.grid{display:flex;flex-wrap:wrap;gap:18px}.grid label{flex:1;min-width:160px}.grid label input,.grid label select{width:100%}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1200px}th,td{padding:7px;border:1px solid #e0e6ea;text-align:left;vertical-align:top}th{background:#edf3f5}td input{width:100%;min-width:80px}td:nth-child(4) input{min-width:230px}.notice{padding:12px;background:#eaf5ef}.warning{color:#88540c}.muted{color:#627681;font-size:12px}.source{font-size:12px;max-width:300px;overflow-wrap:anywhere}
</style></head><body><main><header><div><h1>Materialen en prijzen</h1><p>Controleer leveranciersartikelen uit prijslijsten, offertes en inkoopfacturen.</p></div><a href="/office-test">Office</a></header>
<?php if($message!==''):?><p class="notice" role="status"><?=$h($message)?></p><?php endif?>
<?php if($canImport):?><section><h2>Bron inlezen</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=$h($_SESSION['material_csrf'])?>"><input type="hidden" name="action" value="preview">
<label>Bron<select name="source_mode"><option value="file">Bestand uploaden</option><option value="text">Tekst plakken</option><option value="invoice">Bestaande inkoopfactuur</option><option value="document">Bestaand Office-document uit dossier of e-mail</option></select></label>
<label>Bestand<input type="file" name="source" accept=".csv,.tsv,.txt,.xml,.xlsx,.docx,.pdf,.jpg,.jpeg,.png,.webp,.heic,.heif"></label><p class="muted">Maximaal 20 MB. PDF en scans gebruiken de geconfigureerde Office-documentherkenning.</p>
<label>Brontekst<textarea name="source_text" rows="4"></textarea></label><div class="grid"><label>Inkoopfactuurnummer in Office<input name="invoice_id" inputmode="numeric" value="<?=$h($_GET['invoice_id']??'')?>"></label><label>Office-documentnummer<input name="document_id" inputmode="numeric" value="<?=$h($_GET['document_id']??'')?>"></label></div><button type="submit">Inlezen en controleren</button></form></section><?php endif?>
<?php if(is_array($proposal)):?><section><h2>Herkenning controleren</h2><p class="muted">Neem uitsluitend materiaalregels op. Transport, arbeid en onderaanneming horen niet automatisch in de materialenprijslijst.</p>
<?php foreach($proposal['issues']??[]as$issue):?><p class="warning"><?=$h($issue)?></p><?php endforeach?>
<?php if(($proposal['rows']??[])!==[]):?><form method="post"><input type="hidden" name="csrf" value="<?=$h($_SESSION['material_csrf'])?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="review_token" value="<?=$h($reviewToken)?>">
<div class="grid"><label>Bestaande leverancier<select name="supplier_id" required><option value="">Kies leverancier…</option><?php foreach($suppliers as$supplier):?><option value="<?=$h($supplier['id'])?>"><?=$h($supplier['name'])?></option><?php endforeach?></select></label><label>Prijsdatum<input type="date" name="price_date" required value="<?=$h($proposal['rows'][0]['price_date']??date('Y-m-d'))?>"></label><label>Valuta<input name="currency" maxlength="3" value="<?=$h($proposal['rows'][0]['currency']??'EUR')?>" required></label><label>Getalnotatie<select name="number_format"><option value="auto">Automatisch, twijfel blijft open</option><option value="nl">Nederlands: 1.234,56</option><option value="international">Internationaal: 1,234.56</option></select></label></div>
<div class="scroll"><table><thead><tr><th>Materiaal opnemen</th><th>Leverancier uit bron</th><th>Artikelcode</th><th>Omschrijving</th><th>Prijs uit bron</th><th>Prijs per aantal</th><th>Eenheid</th><th>Korting %</th><th>Bron / controle</th></tr></thead><tbody>
<?php foreach($proposal['rows']as$i=>$row):?><tr><td><input aria-label="Materiaalregel <?=$i+1?> opnemen" type="checkbox" name="rows[<?=$i?>][selected]" value="1"></td><?php foreach(['supplier','article_code','description','price','price_quantity','unit','discount_percentage']as$field):?><td><input aria-label="<?=$h($field)?> regel <?=$i+1?>" name="rows[<?=$i?>][<?=$h($field)?>]" value="<?=$h($row[$field]??(in_array($field,['price_quantity'],true)?'1':($field==='discount_percentage'?'0':'')))?>"></td><?php endforeach?><td class="source"><?=$h($row['source_fragment']??('Bronregel '.($row['source_row']??$i+1)))?><?php foreach($row['issues']??[]as$issue):?><p class="warning"><?=$h($issue)?></p><?php endforeach?></td></tr><?php endforeach?>
</tbody></table></div><label><input type="checkbox" name="price_is_ex_vat" value="1" required> De geselecteerde prijzen, prijsbasis, korting en gebruikseenheden zijn gecontroleerd; de prijzen zijn exclusief btw.</label><button type="submit">Goedgekeurde materialen en prijsversies opslaan</button></form><?php endif?></section><?php endif?>
</main></body></html>
