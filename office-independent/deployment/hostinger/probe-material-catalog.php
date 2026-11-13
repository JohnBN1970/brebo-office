<?php
declare(strict_types=1);
// Host integration test using only connection-local TEMPORARY copies of catalogue schemas.
// Every table is shadowed before the first fixture insert; persistent articles/prices are never written.
$configFile=$argv[1]??'';$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
if($base===false||realpath($configFile)!==$base.'/config/config.php')exit(2);
$config=require $configFile;$root=realpath((string)($config['OFFICE_PRIVATE_ROOT']??''));
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||$root===false||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
$db=null;$temporary=[];$status=0;$step='connect';
try{
 foreach(['MaterialImportRows','MaterialCatalogStore','PdoMaterialCatalogStore','MaterialCatalogPublisher']as$class)require_once $root.'/src/Materials/'.$class.'.php';
 $prefix=$config['OFFICE_MATERIALS_CANONICAL_TABLE_PREFIX']??'';
 if(!is_string($prefix)||!preg_match('/^[A-Za-z0-9_]{0,32}$/D',$prefix))throw new RuntimeException('Invalid prefix');
 $db=new PDO((string)$config['OFFICE_MATERIALS_CANONICAL_PDO_DSN'],(string)$config['OFFICE_MATERIALS_CANONICAL_DB_USER'],(string)$config['OFFICE_MATERIALS_CANONICAL_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $step='temporary_schema';
 foreach(['brebo_supplier','brebo_article','brebo_supplier_article','brebo_catalog_import','brebo_article_price','brebo_material_import_reviews']as$table){$name='`'.$prefix.$table.'`';$db->exec('CREATE TEMPORARY TABLE '.$name.' LIKE '.$name);$temporary[]=$name;}
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
 echo "MATERIAL_CATALOG_RUNTIME=PASS\nMATERIAL_CATALOG_RUNTIME_DATA=TEMPORARY_FIXTURES_ONLY\n";
}catch(Throwable){echo 'MATERIAL_CATALOG_RUNTIME=FAILED_'.strtoupper($step)."\n";$status=10;}
finally{if($db instanceof PDO){if($db->inTransaction())$db->rollBack();foreach(array_reverse($temporary)as$name)$db->exec('DROP TEMPORARY TABLE IF EXISTS '.$name);}$db=null;}
exit($status);
