<?php
declare(strict_types=1);

// Metadata-only inspection; no credential, person or document contents are printed.
$configFile=$argv[1]??'';
if(!is_file($configFile))exit(2);
$config=require $configFile;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test')exit(3);
$allowed=['office_operator_accounts','office_module_permissions','office_documents','office_document_originals',
 'office_document_contexts','office_finance_invoices','office_finance_invoice_lines','brebo_supplier','brebo_article',
 'brebo_supplier_article','brebo_article_price','brebo_catalog_import','office_calc_calculations','office_calc_project_access',
 'office_calc_crm_drafts','calculations','calculation_versions','calculation_lines','office_crm_opportunities'];
$inspect=static function(string $label,string $dsn,string $user,string $password,string $prefix='')use($allowed):void{
 if($dsn===''){echo $label."_CONNECTION=NOT_CONFIGURED\n";return;}
 if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Unsupported read source');
 $db=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $db->exec('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();
 try{
  echo $label."_CONNECTION=OK_READ_ONLY\n";
  $query=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
  foreach($allowed as $table){
   $name=$prefix.$table;$query->execute([$name]);$columns=$query->fetchAll(PDO::FETCH_ASSOC);
   echo $label.'.'.$table.'='.($columns===[]?'MISSING':'PRESENT')."\n";
   if(in_array($table,['office_operator_accounts','office_finance_invoices','office_finance_invoice_lines','office_calc_crm_drafts','office_calc_calculations','calculations','calculation_versions'],true)&&$columns!==[]){
    echo $label.'.'.$table.'.COLUMNS='.implode(',',array_map(static fn(array $c):string=>$c['COLUMN_NAME'].':'.$c['COLUMN_TYPE'],$columns))."\n";
   }
  }
 }finally{if($db->inTransaction())$db->rollBack();}
};
try{
 $inspect('NATIVE',(string)($config['OFFICE_INTAKE_PDO_DSN']??''),(string)($config['OFFICE_INTAKE_DB_USER']??''),(string)($config['OFFICE_INTAKE_DB_PASSWORD']??''));
 $inspect('LEGACY_READ',(string)($config['OFFICE_FINANCE_READ_PDO_DSN']??''),(string)($config['OFFICE_FINANCE_READ_DB_USER']??''),(string)($config['OFFICE_FINANCE_READ_DB_PASSWORD']??''),(string)($config['OFFICE_FINANCE_TABLE_PREFIX']??''));
 $root=realpath((string)($config['OFFICE_PRIVATE_ROOT']??''));
 $base=realpath(dirname($configFile).'/..');
 if($root!==false&&$base!==false&&str_starts_with($root,$base.'/release-')&&str_ends_with($root,'/private')){
  foreach(['public/office-calculatie.php','public/office-calculation.php','public/office-calc-launch.php','public/office-calc-browser-handoff.php','src/Calculation/OfficeCanonicalCalcDraftAdapter.php']as$file)echo 'ACTIVE_FILE.'.$file.'='.(is_file($root.'/'.$file)?'PRESENT':'MISSING')."\n";
 }
 foreach(['BREBO_DOCUMENT_EXTRACTION_ENDPOINT','DOCUMENT_EXTRACTION_TOKEN']as$key)echo 'EXTRACTION.'.$key.'='.(!empty($config[$key])?'CONFIGURED':'MISSING')."\n";
 echo "MATERIAL_AUDIT=READ_ONLY_COMPLETE\n";
}catch(Throwable $error){fwrite(STDERR,"MATERIAL_AUDIT=FAILED_WITHOUT_MUTATION\n");exit(4);}
