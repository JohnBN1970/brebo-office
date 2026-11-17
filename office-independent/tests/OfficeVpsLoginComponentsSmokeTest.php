<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Intake/OfficeMount.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeLoginCsrf.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeLoginFormRenderer.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeOperatorAuthenticator.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeCrmSessionIssuer.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeLoginController.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeLoginThrottle.php';
use Brebo\Office\Intake\WebsiteOfficeLoginCsrf;
use Brebo\Office\Intake\WebsiteOfficeLoginFormRenderer;
putenv('OFFICE_BASE_PATH=/office-test');
$token=WebsiteOfficeLoginCsrf::issue();
if(!WebsiteOfficeLoginCsrf::verify($token,$token)||WebsiteOfficeLoginCsrf::verify($token,str_repeat('0',64)))throw new RuntimeException('CSRF failure');
$form=(new WebsiteOfficeLoginFormRenderer())->render($token,true);
if(!str_contains($form,'csrf_token')||!str_contains($form,'type="password"')||!str_contains($form,'Ongeldige aanmeldgegevens'))throw new RuntimeException('Login form failure');
if(in_array('sqlite',PDO::getAvailableDrivers(),true)){
  $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password_hash TEXT, active INTEGER, can_edit_crm INTEGER)');
  $db->exec('CREATE TABLE office_operator_sessions (token_hash TEXT PRIMARY KEY, operator_id INTEGER, expires_at TEXT, revoked_at TEXT)');
  $password='test-password-'.bin2hex(random_bytes(8));
  $q=$db->prepare('INSERT INTO office_operator_accounts (id,username,password_hash,active,can_edit_crm) VALUES (1,?,?,1,0)');
  $q->execute(['test-user',password_hash($password,PASSWORD_DEFAULT)]);
  $auth=new \\Brebo\\Office\\Intake\\WebsiteOfficeOperatorAuthenticator($db);
  $issuer=new \\Brebo\\Office\\Intake\\WebsiteOfficeCrmSessionIssuer($db);
  $controller=new \\Brebo\\Office\\Intake\\WebsiteOfficeLoginController($auth,$issuer);
  if($controller->login('test-user','wrong',time())!==null)throw new RuntimeException('Invalid password accepted');
  $session=$controller->login('test-user',$password,time());
  if(!is_string($session)||strlen($session)!==64)throw new RuntimeException('Session not issued');
  $q=$db->prepare('SELECT COUNT(*) FROM office_operator_sessions WHERE token_hash=?');
  $q->execute([hash('sha256',$session)]);
  if((int)$q->fetchColumn()!==1)throw new RuntimeException('Session token not hashed');
}
echo "PASS login component smoke\n";
