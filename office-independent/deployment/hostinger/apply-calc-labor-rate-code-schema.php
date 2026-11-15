<?php
declare(strict_types=1);
// Explicit, additive Office-test migration. Never inserts default rates.
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--apply'){fwrite(STDERR,"MIGRATION=EXPLICIT_APPLY_REQUIRED\n");exit(2);}
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
$cfg=$base&&is_file($base.'/config/config.php')?require $base.'/config/config.php':null;
if(!is_array($cfg)||($cfg['OFFICE_BASE_PATH']??null)!=='/office-test')exit(2);
$dsn=(string)($cfg['OFFICE_INTAKE_PDO_DSN']??'');
if(!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn))exit(2);
try{
$db=new PDO($dsn,(string)$cfg['OFFICE_INTAKE_DB_USER'],(string)$cfg['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if((string)$db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test')throw new RuntimeException('DB_SCOPE');
$existing=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calculation_lines' AND COLUMN_NAME='calc_labor_rate_code'")->fetchColumn();
if((int)$existing===0)$db->exec("ALTER TABLE calculation_lines ADD COLUMN calc_labor_rate_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL");
$columns=$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calculation_lines'")->fetchAll(PDO::FETCH_COLUMN);
foreach(['calc_labor_rate_code'] as $column)if(!in_array($column,$columns,true))throw new RuntimeException('SCHEMA');
echo "LABOR_RATE_CODE_SCHEMA=PASS\n";
}catch(Throwable $e){fwrite(STDERR,"LABOR_RATE_SCHEMA=FAILED\n");exit(1);}
