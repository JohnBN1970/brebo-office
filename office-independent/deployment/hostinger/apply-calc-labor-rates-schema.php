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
$db->exec("CREATE TABLE IF NOT EXISTS office_calc_labor_rates (
 code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 description VARCHAR(160) NOT NULL,
 hourly_rate DECIMAL(12,4) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT chk_calc_labor_rate_nonnegative CHECK(hourly_rate>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$columns=$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_labor_rates'")->fetchAll(PDO::FETCH_COLUMN);
foreach(['code','description','hourly_rate','active','created_at','updated_at'] as $column)if(!in_array($column,$columns,true))throw new RuntimeException('SCHEMA');
echo "LABOR_RATE_SCHEMA=PASS\n";
}catch(Throwable $e){fwrite(STDERR,"LABOR_RATE_SCHEMA=FAILED\n");exit(1);}
