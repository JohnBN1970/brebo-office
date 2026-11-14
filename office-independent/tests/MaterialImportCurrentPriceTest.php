<?php
declare(strict_types=1);
require_once __DIR__.'/../../web/modules/custom/brebo_article/src/Infrastructure/ArticleCurrentPriceQuery.php';
use Drupal\brebo_article\Infrastructure\ArticleCurrentPriceQuery;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE brebo_catalog_import(id INTEGER PRIMARY KEY,status TEXT); CREATE TABLE brebo_article_price(id INTEGER PRIMARY KEY,supplier_article_id INTEGER,catalog_import_id INTEGER,valid_from TEXT,valid_until TEXT,quantity_from INTEGER,net_price TEXT,currency TEXT);
 INSERT INTO brebo_catalog_import VALUES(1,"actief"),(2,"actief"),(3,"archief");
 INSERT INTO brebo_article_price VALUES
 (1,1,1,"2026-11-01",NULL,1,"10","EUR"),
 (2,1,2,"2026-11-01",NULL,1,"11","EUR"),
 (3,1,2,"2026-12-01",NULL,1,"12","EUR"),
 (4,1,2,"2026-11-02","2026-11-12",1,"13","EUR"),
 (5,1,3,"2026-11-03",NULL,1,"14","EUR"),
 (6,1,2,"2026-11-04",NULL,100,"8","EUR"),
 (7,1,2,"2026-11-05",NULL,1,"15","USD"),
 (8,2,1,"2026-11-02",NULL,1,"20","EUR")');
$sql='SELECT p.id FROM brebo_article_price p JOIN brebo_catalog_import ci ON ci.id=p.catalog_import_id WHERE ci.status=\'actief\' AND '.str_replace(['{brebo_article_price}','{brebo_catalog_import}'],['brebo_article_price','brebo_catalog_import'],ArticleCurrentPriceQuery::predicate()).' ORDER BY p.supplier_article_id LIMIT 2';
$q=$db->prepare($sql);$q->execute(ArticleCurrentPriceQuery::arguments('2026-11-13'));
if(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN))!==[2,8])throw new RuntimeException('Current price must exclude future, expired, archived, bulk and foreign-currency prices before pagination');
require_once __DIR__.'/../src/Materials/MaterialCatalogReadModel.php';
$db->sqliteCreateFunction('LOCATE',static function(string $needle,string $haystack):int{$position=strpos($haystack,$needle);return $position===false?0:$position+1;},2);
$db->exec('CREATE TABLE brebo_supplier(id INTEGER PRIMARY KEY,name TEXT,active INTEGER);
 CREATE TABLE brebo_article(id INTEGER PRIMARY KEY,description TEXT,active INTEGER);
 CREATE TABLE brebo_supplier_article(id INTEGER PRIMARY KEY,supplier_id INTEGER,article_id INTEGER,supplier_article_no TEXT,use_unit TEXT,active INTEGER);
 CREATE TABLE brebo_material_import_reviews(catalog_import_id INTEGER PRIMARY KEY,actor_id TEXT,reviewed_at INTEGER);
 INSERT INTO brebo_supplier VALUES(1,"Supplier A",1),(2,"Supplier B",1);
 INSERT INTO brebo_article VALUES(1,"Schroeven",1),(2,"Plaat 10%",1);
 INSERT INTO brebo_supplier_article VALUES(1,1,1,"000123","st",1),(2,2,2,"P2","m2",1);
 INSERT INTO brebo_material_import_reviews VALUES(2,"verified-office-actor",1794567600)');
$read=new \Brebo\Office\Materials\MaterialCatalogReadModel($db);
$rows=$read->search('',null,'2026-11-13');
if(count($rows)!==2||$rows[0]['net_price']!=='11'||$rows[0]['article_code']!=='000123'||$rows[0]['actor_id']!=='verified-office-actor')throw new RuntimeException('Native price list must expose current price and verified provenance');
if(count($read->search('',2,'2026-11-13'))!==1||count($read->search('%',null,'2026-11-13'))!==1||$read->search('',null,'2026-11-13',1)!==[])throw new RuntimeException('Supplier filtering, literal search and bounded pagination');
$db->exec('UPDATE brebo_supplier SET active=0 WHERE id=1');
if(count($read->search('',null,'2026-11-13'))!==1)throw new RuntimeException('Inactive supplier must not appear');
echo "MATERIAL_CURRENT_PRICE=PASS\n";
