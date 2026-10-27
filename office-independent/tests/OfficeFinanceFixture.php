<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/bootstrap.php';
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';

function financeCheck(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
}

/** Synthetic fixtures only. MySQL CI must use the dedicated empty database. */
function financeFixture(?string $sqliteFile = null): PDO {
  $dsn = getenv('OFFICE_FINANCE_TEST_DSN');
  if ($dsn !== false && $dsn !== '') {
    if (!preg_match('/^mysql:host=127\.0\.0\.1;port=3306;dbname=office_finance_ci;charset=utf8mb4$/D', $dsn)) throw new RuntimeException('Unsafe CI DSN');
    $db = new PDO($dsn, 'root', 'finance-ci-only', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
  } else {
    $db = new PDO('sqlite:' . ($sqliteFile ?? ':memory:'), null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  }
  foreach ([
    'CREATE TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY, active INTEGER, can_edit_crm INTEGER)',
    'CREATE TABLE office_operator_sessions (token_hash VARCHAR(64) PRIMARY KEY,operator_id VARCHAR(128),expires_at VARCHAR(30),revoked_at VARCHAR(30))',
    'CREATE TABLE office_projects (id INTEGER PRIMARY KEY,title VARCHAR(255))',
    'CREATE TABLE office_project_memberships (operator_id VARCHAR(128),project_id INTEGER,permission VARCHAR(16))',
    ...\Brebo\Office\Finance\OfficeFinanceSchema::statements(),
    'CREATE TABLE brebo_finance_purchase_invoice (id INTEGER PRIMARY KEY,project_nid INTEGER,invoice_number VARCHAR(128),supplier_name VARCHAR(255),invoice_date VARCHAR(10),due_date VARCHAR(10),amount_ex_vat DECIMAL(14,2),vat_amount DECIMAL(14,2),amount_inc_vat DECIMAL(14,2),status VARCHAR(32),match_status VARCHAR(32))',
    'CREATE TABLE brebo_finance_purchase_invoice_line (id INTEGER PRIMARY KEY,invoice_id INTEGER,line_number INTEGER,description VARCHAR(255),commitment_line_id INTEGER,amount_ex_vat DECIMAL(14,2),vat_amount DECIMAL(14,2),amount_inc_vat DECIMAL(14,2),match_status VARCHAR(32),variance_code VARCHAR(255))',
    'CREATE TABLE brebo_finance_payment_release (id INTEGER PRIMARY KEY,invoice_id INTEGER,status VARCHAR(32))',
    'CREATE TABLE brebo_finance_g_account_instruction (id INTEGER PRIMARY KEY,source_type VARCHAR(32),source_id INTEGER,direction VARCHAR(32))',
    'CREATE TABLE brebo_finance_commitment (id INTEGER PRIMARY KEY,project_nid INTEGER)',
    'CREATE TABLE brebo_finance_commitment_line (id INTEGER PRIMARY KEY,commitment_id INTEGER)',
    'CREATE TABLE brebo_finance_performance_receipt (id INTEGER PRIMARY KEY,commitment_line_id INTEGER,status VARCHAR(32),description VARCHAR(255),amount_ex_vat DECIMAL(14,2),building_evidence_complete INTEGER,quality_accepted INTEGER,created INTEGER,changed INTEGER)',
  ] as $sql) $db->exec($sql);
  $db->exec("INSERT INTO office_operator_accounts VALUES ('1',1,0),('2',1,1),('3',0,1),('4',1,0)");
  $db->exec("INSERT INTO office_projects VALUES (17,'Zichtbaar project'),(99,'Verborgen project')");
  $db->exec("INSERT INTO office_project_memberships VALUES ('1',17,'read'),('2',17,'manage'),('3',17,'manage'),('4',99,'read')");
  $db->exec("INSERT INTO office_module_permissions VALUES ('1','finance','read'),('3','finance','read'),('4','finance','read')");
  $db->exec('INSERT INTO office_finance_project_mapping VALUES (101,17,1),(17,99,1)');
  $db->exec("INSERT INTO brebo_finance_commitment VALUES (1,101),(2,17)");
  $db->exec('INSERT INTO brebo_finance_commitment_line VALUES (10,1),(20,2)');
  $db->exec("INSERT INTO brebo_finance_performance_receipt VALUES (1,10,'verified','Zichtbaar bewijs',100,1,1,100,100),(2,20,'draft','Verborgen bewijs',9000,0,0,100,100)");
  $invoice = $db->prepare('INSERT INTO brebo_finance_purchase_invoice VALUES (?,?,?,?,?,?,?,?,?,?,?)');
  $line = $db->prepare('INSERT INTO brebo_finance_purchase_invoice_line VALUES (?,?,?,?,?,?,?,?,?,?)');
  foreach (range(1,12) as $id) {
    $project = $id === 7 ? 17 : ($id === 8 ? 0 : ($id === 11 ? 909 : 101));
    $invoice->execute([$id,$project,'INV-'.$id,$id===7 ? 'Geheime leverancier' : 'Leverancier <script>alert(1)</script>','2026-10-01','2026-10-20',100,21,121,$id===9?'paid':($id===10?'cancelled':'open'),$id===2?'exception':($id===3?'unmatched':'matched')]);
    if ($id!==1 && $id!==8) $line->execute([$id,$id,1,'Regel <img src=x onerror=alert(1)>',$id===12?20:10,100,21,121,'matched','']);
  }
  $db->exec("INSERT INTO brebo_finance_payment_release VALUES (1,5,'approved'),(2,5,'pending_approval'),(3,6,'approved')");
  $token = str_repeat('s',48);
  $q=$db->prepare('INSERT INTO office_operator_sessions VALUES (?,?,?,NULL)');
  $q->execute([hash('sha256',$token),'1',gmdate('Y-m-d H:i:s',time()+3600)]);
  return $db;
}
