<?php
declare(strict_types=1);

// CLI-only migration. Explicit opt-in and test DSN; never auto-run on page requests.
if (PHP_SAPI !== 'cli') {http_response_code(403);exit;}
if (getenv('OFFICE_BUILDING_ADDRESS_MIGRATE') !== 'yes'
    || getenv('OFFICE_ENV') !== 'test') {
  fwrite(STDERR,"Migration blocked: requires OFFICE_ENV=test and OFFICE_BUILDING_ADDRESS_MIGRATE=yes\n");
  exit(2);
}
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingAddressSchema.php';
$dsn=getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
if (!str_starts_with($dsn,'mysql:')) {
  fwrite(STDERR,"Migration blocked: expected MySQL DSN\n");exit(2);
}
$db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER') ?: '',getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES=>false
]);
$check=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_buildings'");
if ((int)$check->fetchColumn()!==1) {fwrite(STDERR,"Missing office_buildings\n");exit(2);}
$statements=\Brebo\Office\Buildings\OfficeBuildingAddressSchema::statements();
foreach ($statements as $sql) {
  if (!preg_match('/^CREATE TABLE ([a-z_]+) /',$sql,$match)) throw new RuntimeException('invalid_ddl');
  $name=$match[1];
  $present=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
  $present->execute([$name]);
  if ((int)$present->fetchColumn()!==0) {
    echo "EXISTS $name (unchanged)\n";
    continue;
  }
  $db->exec($sql);
  echo "CREATED $name\n";
}
echo "Address inventory schema migration complete\n";
