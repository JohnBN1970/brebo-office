<?php
declare(strict_types=1);
require __DIR__.'/OfficeProjectContractServiceTest.php';
require_once dirname(__DIR__).'/src/Project/OfficeProjectFinanceControls.php';
use Brebo\Office\Project\OfficeProjectFinanceControls as Controls;
$db->exec("INSERT INTO office_module_permissions VALUES(1,'finance','draft_edit'),(2,'finance','draft_edit');
CREATE TABLE office_project_terms(id INTEGER PRIMARY KEY AUTOINCREMENT,contract_id INTEGER,term_number INTEGER,description TEXT,percentage TEXT,amount TEXT,status TEXT,due_date TEXT,created_at TEXT);
CREATE TABLE office_project_change_orders(id INTEGER PRIMARY KEY AUTOINCREMENT,contract_id INTEGER,change_number TEXT,description TEXT,change_type TEXT,amount TEXT,status TEXT,approved_at TEXT,created_at TEXT);");
$input=['action'=>'create_project_contract','contract_number'=>'FICTIEF-2','client_name'=>'Fictief','amount'=>'1.234,56'];
Controls::handle($db,'1',12,$input);
$contract=(int)$db->lastInsertId();
if ((string)$db->query("SELECT contract_amount FROM office_project_contracts WHERE id=$contract")->fetchColumn()!=='1234.56') throw new RuntimeException('Decimal amount lost');
$term=['action'=>'create_project_term','contract_id'=>$contract,'term_number'=>'1','description'=>'Fictief termijn','amount'=>'123,45'];
Controls::handle($db,'1',12,$term);
if ($db->query('SELECT amount FROM office_project_terms')->fetchColumn()!=='123.45') throw new RuntimeException('Term missing');
Controls::handle($db,'1',12,['action'=>'create_project_change','contract_id'=>$contract,'change_number'=>'FICTIEF-M1','description'=>'Meerwerk','change_type'=>'more','amount'=>'12,50']);
expectDenied(fn()=>Controls::handle($db,'2',12,$input),'Reader wrote contract');
expectDenied(fn()=>Controls::handle($db,'3',12,$input),'Missing Finance grant');
expectDenied(fn()=>Controls::handle($db,'1',99,$term),'Cross-project contract accepted');
try {Controls::handle($db,'1',12,array_replace($input,['amount'=>'1,234']));throw new LogicException('Precision accepted');} catch (InvalidArgumentException $expected) {}
if (Controls::render($db,'2',12,'csrf')!=='') throw new RuntimeException('Reader controls exposed');
$html=Controls::render($db,'1',12,'<token>');
if (!str_contains($html,'value="&lt;token&gt;"') || !str_contains($html,'create_project_term')) throw new RuntimeException('Forms or CSRF missing');
echo "OfficeProjectFinanceControlsTest OK\n";
