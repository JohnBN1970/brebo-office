<?php
declare(strict_types=1);
// Host integration test using only connection-local TEMPORARY copies of catalogue schemas.
// Every table is shadowed before the first fixture insert; persistent articles/prices are never written.
$configFile=$argv[1]??'';$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
if($base===false||realpath($configFile)!==$base.'/config/config.php')exit(2);
$config=require $configFile;$root=realpath((string)($config['OFFICE_PRIVATE_ROOT']??''));
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||$root===false||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
$sourceDb=null;$db=null;$temporary=[];$status=0;$step='connect';
try{
 foreach(['MaterialImportRows','MaterialCatalogStore','PdoMaterialCatalogStore','MaterialCatalogPublisher']as$class)require_once $root.'/src/Materials/'.$class.'.php';
 $prefix=$config['OFFICE_MATERIALS_CANONICAL_TABLE_PREFIX']??'';
 if(!is_string($prefix)||!preg_match('/^[A-Za-z0-9_]{0,32}$/D',$prefix))throw new RuntimeException('Invalid prefix');
 $sourceDb=new PDO((string)$config['OFFICE_MATERIALS_CANONICAL_PDO_DSN'],(string)$config['OFFICE_MATERIALS_CANONICAL_DB_USER'],(string)$config['OFFICE_MATERIALS_CANONICAL_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $sourceDb->exec('SET SESSION TRANSACTION READ ONLY');$sourceDb->beginTransaction();
 $db=new PDO((string)$config['OFFICE_INTAKE_PDO_DSN'],(string)$config['OFFICE_INTAKE_DB_USER'],(string)$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 if($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test')throw new RuntimeException('Isolated native database required');
 $step='temporary_schema';
 foreach(['brebo_supplier','brebo_article','brebo_supplier_article','brebo_catalog_import','brebo_article_price','brebo_material_import_reviews']as$table){
  $step='temporary_schema_'.$table;$name='`'.$prefix.$table.'`';
  // Copy schema metadata only into the isolated native connection. The source connection is read-only.
  $definition=$sourceDb->query('SHOW CREATE TABLE '.$name)->fetch(PDO::FETCH_NUM);$ddl=$definition[1]??'';
  if(!is_string($ddl)||!str_starts_with($ddl,'CREATE TABLE '.$name.' ('))throw new RuntimeException('Unexpected source schema');
  $ddl=preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$ddl,1);
  $db->exec($ddl);$temporary[]=$name;
 }
 $sourceDb->rollBack();$sourceDb=null;
 if(count($temporary)!==6)throw new RuntimeException('Incomplete isolation');
 $step='fixture';$db->exec("INSERT INTO `".$prefix."brebo_supplier` (code,name,active)VALUES('MATERIAL-RUNTIME-PROBE','Runtime fixture supplier',1)");$supplier=(int)$db->lastInsertId();
 $row=['approved'=>true,'supplier'=>'Runtime fixture supplier','article_code'=>'000001','description'=>'Synthetic runtime material','unit'=>'st','price'=>'45,00','price_quantity'=>'100','discount_percentage'=>'10','currency'=>'EUR','price_date'=>(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d'),'price_is_ex_vat'=>true,'number_format'=>'nl'];
 $source=['sha256'=>hash('sha256','synthetic material runtime contract'),'reference'=>'fixture:material-runtime'];
 $publisher=new \Brebo\Office\Materials\MaterialCatalogPublisher(new \Brebo\Office\Materials\PdoMaterialCatalogStore($db,$prefix),new \Brebo\Office\Materials\MaterialImportRows(),static fn(string $actor):bool=>$actor==='runtime-fixture');
 $step='publish';$first=$publisher->publish('runtime-fixture',$supplier,$source,[$row],time());
 if(($first['status']??'')!=='completed'||(string)$db->query('SELECT net_price FROM `'.$prefix.'brebo_article_price`')->fetchColumn()!=='0.4050')throw new RuntimeException('Price contract failed');
 if((string)$db->query('SELECT actor_id FROM `'.$prefix.'brebo_material_import_reviews`')->fetchColumn()!=='runtime-fixture')throw new RuntimeException('Author contract failed');
 $step='idempotence';$repeat=$publisher->publish('runtime-fixture',$supplier,$source,[$row],time());if(($repeat['status']??'')!=='already_imported')throw new RuntimeException('Duplicate review');
 $step='price_revision';$row['price']='50,00';$source['reference']='fixture:material-runtime-revision';$source['sha256']=hash('sha256','runtime revision');$publisher->publish('runtime-fixture',$supplier,$source,[$row],time());
 if((int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'brebo_article`')->fetchColumn()!==1||(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'brebo_article_price`')->fetchColumn()!==2)throw new RuntimeException('Article reuse or price history failed');
 echo "MATERIAL_CATALOG_RUNTIME=PASS\nMATERIAL_CATALOG_RUNTIME_DATA=TEMPORARY_FIXTURES_ONLY\nMATERIAL_CATALOG_RUNTIME_SCHEMA=EXISTING_SOURCE_METADATA\n";
}catch(Throwable $error){if($error instanceof PDOException)echo 'MATERIAL_CATALOG_SQL_CODE='.(int)($error->errorInfo[1]??0)."\n";echo 'MATERIAL_CATALOG_RUNTIME=FAILED_'.strtoupper($step)."\n";$status=10;}
finally{if($sourceDb instanceof PDO&&$sourceDb->inTransaction())$sourceDb->rollBack();$sourceDb=null;if($db instanceof PDO){if($db->inTransaction())$db->rollBack();foreach(array_reverse($temporary)as$name)$db->exec('DROP TEMPORARY TABLE IF EXISTS '.$name);}$db=null;}
exit($status);
