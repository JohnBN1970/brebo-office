<?php
declare(strict_types=1);
$root = dirname(__DIR__);
foreach ([
  'Intake/OfficeMount.php','Intake/WebsiteCrmOperatorContext.php',
  'Intake/WebsiteCrmOperatorSessionProviderInterface.php',
  'Intake/WebsiteOfficeCrmSessionRepository.php',
  'Intake/WebsiteOfficeCrmCookieSessionProvider.php',
  'Inzet/OfficeInzetShiftAssignmentControl.php','Inzet/OfficeInzetShiftWriter.php',
  'Inzet/OfficeInzetShiftCommand.php','Inzet/OfficeInzetLocalTime.php',
  'Inzet/OfficeInzetShiftSubmission.php','Inzet/OfficeInzetShiftCsrf.php',
  'Inzet/OfficeInzetSessionCsrf.php','Inzet/OfficeInzetShiftPostHandler.php',
  'Inzet/OfficeInzetAuthenticatedShiftAction.php',
] as $file) require_once $root . '/src/' . $file;
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) throw new RuntimeException('MySQL DSN required');
$db = new PDO($dsn,getenv('OFFICE_INTAKE_TEST_USER') ?: '',getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach (['office_inzet_shifts','office_inzet_project_team','office_project_memberships','office_inzet_people','office_operator_sessions','office_operator_accounts'] as $table) $db->exec('DROP TEMPORARY TABLE IF EXISTS ' . $table);
$db->exec('CREATE TEMPORARY TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY,active TINYINT,can_edit_crm TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_operator_sessions (token_hash CHAR(64) PRIMARY KEY,operator_id VARCHAR(128),expires_at DATETIME,revoked_at DATETIME NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_project_memberships (operator_id VARCHAR(128),project_id BIGINT,permission VARCHAR(20),PRIMARY KEY(operator_id,project_id)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_people (id BIGINT PRIMARY KEY,active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_project_team (project_id BIGINT,person_id BIGINT,PRIMARY KEY(project_id,person_id)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_shifts (id BIGINT PRIMARY KEY AUTO_INCREMENT,project_id BIGINT,person_id BIGINT,starts_at DATETIME,ends_at DATETIME,status VARCHAR(24)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_operator_accounts VALUES ('john.boon',1,0)");
$db->exec("INSERT INTO office_project_memberships VALUES ('john.boon',17,'manage')");
$db->exec('INSERT INTO office_inzet_people VALUES (101,1)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,101)');
$token = str_repeat('a',64);
$sessionHash = hash('sha256',$token);
$stmt = $db->prepare('INSERT INTO office_operator_sessions VALUES (?, ?, ?, NULL)');
$stmt->execute([$sessionHash,'john.boon',gmdate('Y-m-d H:i:s',time()+3600)]);
putenv('OFFICE_BASE_PATH=/office-test');
$provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),['office_test_session'=>$token],time());
$operator = $provider->currentOperator();
if ($operator === null || $operator->operatorId !== 'john.boon') throw new RuntimeException('Verified session missing');
$secret = str_repeat('b',48);
$action = new \Brebo\Office\Inzet\OfficeInzetAuthenticatedShiftAction(
  new \Brebo\Office\Inzet\OfficeInzetShiftPostHandler(
    new \Brebo\Office\Inzet\OfficeInzetShiftSubmission(
      new \Brebo\Office\Inzet\OfficeInzetShiftCommand($db,new \Brebo\Office\Inzet\OfficeInzetShiftWriter($db))
    )
  )
);
$post = ['project_id'=>17,'person_id'=>101,'starts_at_local'=>'2026-10-19T08:00','ends_at_local'=>'2026-10-19T16:00','csrf_token'=>\Brebo\Office\Inzet\OfficeInzetAuthenticatedShiftAction::formToken($operator,$secret)];
$id = $action->handle('POST',$operator,$post,$secret);
if ($id < 1 || (int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn() !== 1) throw new RuntimeException('Authenticated shift was not saved');
foreach ([
  array_replace($post,['csrf_token'=>str_repeat('0',64),'starts_at_local'=>'2026-10-20T08:00']),
  array_replace($post,['project_id'=>18,'starts_at_local'=>'2026-10-20T08:00']),
  array_replace($post,['person_id'=>102,'starts_at_local'=>'2026-10-20T08:00']),
  $post,
] as $attempt) {
  try { $action->handle('POST',$operator,$attempt,$secret); throw new RuntimeException('Invalid request accepted'); }
  catch (InvalidArgumentException|RuntimeException $e) { if ($e->getMessage() === 'Invalid request accepted') throw $e; }
}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn() !== 1) throw new RuntimeException('Rejected request wrote shift');
$db->exec("UPDATE office_operator_sessions SET revoked_at = UTC_TIMESTAMP()");
if ($provider->currentOperator() !== null) throw new RuntimeException('Revoked session accepted');
echo "Inzet authenticated session-to-MySQL create integration OK\n";
