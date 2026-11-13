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
echo "MATERIAL_CURRENT_PRICE=PASS\n";
