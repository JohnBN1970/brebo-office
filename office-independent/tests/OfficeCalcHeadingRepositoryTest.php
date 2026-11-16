<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcHeadingRepository.php';
use Brebo\Office\Calculation\OfficeCalcHeadingRepository;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE calculation_versions(id INTEGER PRIMARY KEY,status TEXT);
CREATE TABLE calculation_lines(id INTEGER PRIMARY KEY,version_id INTEGER,line_type TEXT);
CREATE TABLE office_calc_version_headings(id INTEGER PRIMARY KEY AUTOINCREMENT,version_id INTEGER,code_system TEXT,heading_code TEXT,title TEXT,sort_order INTEGER,UNIQUE(version_id,code_system,heading_code));
CREATE TABLE office_calc_heading_line_links(heading_id INTEGER,calculation_line_id INTEGER PRIMARY KEY);
INSERT INTO calculation_versions VALUES(1,'draft'),(2,'draft'),(3,'locked');
INSERT INTO calculation_lines VALUES(10,1,'item'),(11,2,'item'),(12,1,'section');");
$r=new OfficeCalcHeadingRepository($db);
$id=$r->add(1,'nlsfb','21','Buitenwanden',1);
$r->assign(1,$id,10);
if(count($r->list(1))!==1 || (int)$db->query('SELECT heading_id FROM office_calc_heading_line_links WHERE calculation_line_id=10')->fetchColumn()!==$id)throw new RuntimeException('Heading persistence failed');
foreach([fn()=>$r->assign(1,$id,11),fn()=>$r->assign(1,$id,12),fn()=>$r->assign(2,$id,11),fn()=>$r->add(3,'free','X','Locked',1)] as $reject){
 try{$reject();}catch(DomainException){continue;}throw new RuntimeException('Cross-version or locked write accepted');
}
echo "CALC_HEADING_PERSISTENCE=PASS\n";
