<?php
declare(strict_types=1);
$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:') || !str_contains($dsn, 'dbname=office_inzet_ci')) {
  throw new RuntimeException('Dedicated office_inzet_ci database required');
}
$db = new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'),
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
foreach (['OfficeInzetSchema.php','OfficePersonnelDetailsSchema.php','OfficePersonnelDetailsRepository.php',
  'OfficeInzetPersonnelProfileCsrf.php','OfficePersonnelDetailsAction.php'] as $file) {
  require_once dirname(__DIR__) . '/src/Inzet/' . $file;
}
$db->exec('CREATE TABLE office_projects (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY, active TINYINT(1) NOT NULL) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_project_memberships (operator_id VARCHAR(128), project_id BIGINT UNSIGNED, permission VARCHAR(16)) ENGINE=InnoDB');
foreach (\Brebo\Office\Inzet\OfficeInzetSchema::statements() as $sql) $db->exec($sql);
foreach (\Brebo\Office\Inzet\OfficePersonnelDetailsSchema::statements() as $sql) $db->exec($sql);
$db->exec('INSERT INTO office_projects VALUES (17)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1),('intruder',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('manager',17,'manage')");
$db->exec("INSERT INTO office_inzet_people (id,display_name,active) VALUES (101,'Medewerker',1),(102,'Inactief',0)");
$db->exec('INSERT INTO office_inzet_project_team (project_id,person_id) VALUES (17,101),(17,102)');
$repo = new \Brebo\Office\Inzet\OfficePersonnelDetailsRepository($db);
$action = new \Brebo\Office\Inzet\OfficePersonnelDetailsAction($repo);
$session = str_repeat('a',64);
$secret = str_repeat('s',48);
$post = [
  'csrf_token'=>\Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf::token($session,$secret),
  'project_id'=>'17','person_id'=>'101','phone_country'=>'BE','phone_number'=>'0470123456',
  'nationality'=>'NL','preferred_language'=>'nl','vca_expiry'=>'2027-12-31',
  'skills'=>['Glaszetten','Kozijnmontage']
];
if (!$action->update($post,'manager',$session,$secret)) throw new RuntimeException('Authorized save failed');
$read = $repo->find('manager',17,101);
if (!is_array($read) || $read['phone_e164'] !== '+32470123456' || $read['nationality'] !== 'NL'
  || $read['vca_expiry'] !== '2027-12-31' || count($read['skills']) !== 2) {
  throw new RuntimeException('Persisted personnel details mismatch');
}
$post['skills'] = ['Kitten'];
$post['phone_number'] = '+32470123456';
if (!$action->update($post,'manager',$session,$secret)) throw new RuntimeException('Update failed');
$read = $repo->find('manager',17,101);
if ($read['skills'] !== ['Kitten']) throw new RuntimeException('Skills replacement failed');
if ($repo->find('intruder',17,101) !== null || $action->update($post,'intruder',$session,$secret)) {
  throw new RuntimeException('Unauthorized personnel access succeeded');
}
$post['person_id'] = '102';
if (!$action->update($post,'manager',$session,$secret)) throw new RuntimeException('Inactive employee update failed');
if (!is_array($repo->find('manager',17,102))) throw new RuntimeException('Inactive employee not readable');
echo "Personnel details MySQL round trip OK\n";
