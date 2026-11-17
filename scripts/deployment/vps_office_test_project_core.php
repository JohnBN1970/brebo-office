<?php
declare(strict_types=1);
/** Explicit, additive VPS Office-test project core. No users, memberships or projects are seeded. */
if (PHP_SAPI!=='cli' || !function_exists('posix_geteuid') || posix_geteuid()!==0) {fwrite(STDERR,"ROOT_CLI_REQUIRED\n");exit(2);}
$mode=$argv[1]??'';
if(!in_array($mode,['--plan','--apply'],true)){fwrite(STDERR,"USAGE: --plan|--apply\n");exit(2);}
$sha='983e6659447d803d2692d4f26ff2b932d551554f';
$release='/srv/brebo/office-test/releases/'.$sha;
if(trim((string)@file_get_contents($release.'/.office-source-sha'))!==$sha) throw new RuntimeException('RELEASE_IDENTITY_MISMATCH');
$env='/etc/brebo/office-test-db.env';
if(!is_file($env)||is_link($env)||fileowner($env)!==0) throw new RuntimeException('DB_ENV_INVALID');
foreach(file($env,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
 if(preg_match('/^\\s*(?:export\\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/',$line,$m)){
  $v=trim($m[2]);if(strlen($v)>1&&(($v[0]==='"'&&substr($v,-1)==='"')||($v[0]==="'"&&substr($v,-1)==="'"))) $v=substr($v,1,-1);
  putenv($m[1].'='.$v);
 }
}
if(getenv('OFFICE_TEST_DB_NAME')!=='brebo_office_test') throw new RuntimeException('WRONG_DATABASE');
$db=new PDO('mysql:host='.(getenv('OFFICE_TEST_DB_HOST')?:'localhost').';dbname=brebo_office_test;charset=utf8mb4',getenv('OFFICE_TEST_DB_USER'),getenv('OFFICE_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test') throw new RuntimeException('WRONG_DATABASE_CONNECTION');
$statements=[
 'office_projects'=>"CREATE TABLE office_projects (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 'office_operator_accounts'=>"CREATE TABLE office_operator_accounts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, display_name VARCHAR(200) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 'office_project_memberships'=>"CREATE TABLE office_project_memberships (project_id BIGINT UNSIGNED NOT NULL, operator_id BIGINT UNSIGNED NOT NULL, permission ENUM('read','manage') NOT NULL, PRIMARY KEY(project_id,operator_id), CONSTRAINT fk_project_memberships_project FOREIGN KEY (project_id) REFERENCES office_projects(id), CONSTRAINT fk_project_memberships_operator FOREIGN KEY (operator_id) REFERENCES office_operator_accounts(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
$existing=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
foreach(array_keys($statements) as $table) if(in_array($table,$existing,true)) throw new RuntimeException('EXISTING_TABLE_REQUIRES_REVIEW='.$table);
$build=$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_project_buildings' AND COLUMN_NAME='project_id'")->fetchColumn();
if(!preg_match('/^bigint(?:\\([0-9]+\\))? unsigned$/i',(string)$build)) throw new RuntimeException('BUILDING_LINK_ID_TYPE_MISMATCH');
foreach($statements as $table=>$sql) echo 'PLAN_TABLE='.$table.' SHA256='.hash('sha256',$sql).PHP_EOL;
if($mode==='--plan'){echo "PROJECT_CORE_PLAN_ONLY=YES\n";exit(0);}
foreach($statements as $table=>$sql){
 $db->exec($sql);
 $db->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
 echo 'VERIFIED_TABLE='.$table.PHP_EOL;
}
echo "PROJECT_CORE_APPLIED=YES\n";
