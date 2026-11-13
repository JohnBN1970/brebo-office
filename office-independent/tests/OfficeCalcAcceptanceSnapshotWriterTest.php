<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcAcceptanceSnapshotWriter.php';
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcPersistedAcceptanceVerifier.php';
use Brebo\Office\Calculation\OfficeCalcAcceptanceSnapshotWriter;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {fwrite(STDERR,"SQLite required\n");exit(1);}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY,stage TEXT,status TEXT)');
$db->exec('CREATE TABLE office_crm_calc_acceptances (opportunity_id INTEGER PRIMARY KEY,calculation_id INTEGER,accepted_revision TEXT,status TEXT,accepted_by INTEGER)');
$db->exec('CREATE TABLE office_crm_calc_accepted_lines (opportunity_id INTEGER,source_line_id TEXT,cost_type TEXT,quantity INTEGER,unit_cost_cents INTEGER,activity_ref TEXT,PRIMARY KEY(opportunity_id,source_line_id))');
$db->exec("INSERT INTO office_crm_opportunities VALUES (1,'won','closed'),(2,'proposal','open'),(3,'won','closed')");
$writer=new OfficeCalcAcceptanceSnapshotWriter($db);
$lines=[['line_id'=>'line-1','cost_type'=>'labor','quantity'=>2,'unit_cost_cents'=>120,'activity_ref'=>null]];
$source=static fn(PDO $pdo,int $opp,int $calc,string $rev,int $actor):array=>['verified'=>true,'calculation_id'=>$calc,'revision'=>$rev,'lines'=>$lines];
$reject=static function(callable $run):void {$rejected=false;try{$run();}catch(Throwable){$rejected=true;}if(!$rejected)throw new RuntimeException('Expected rejection');};
$writer->accept(1,123,'r1',9,$source);
if ((int)$db->query('SELECT COUNT(*) FROM office_crm_calc_accepted_lines')->fetchColumn()!==1)throw new RuntimeException('Snapshot missing');
$reject(static fn()=> $writer->accept(1,123,'r1',9,$source));
$reject(static fn()=> $writer->accept(2,123,'r1',9,$source));
$reject(static fn()=> $writer->accept(3,123,'r2',9,static fn()=>['verified'=>false,'calculation_id'=>123,'revision'=>'r2','lines'=>$lines]));
$reject(static fn()=> $writer->accept(3,123,'r2',9,static fn()=>['verified'=>true,'calculation_id'=>123,'revision'=>'old','lines'=>$lines]));
$reject(static fn()=> $writer->accept(3,123,'r2',9,static function(PDO $pdo)use($lines):array {$pdo->rollBack();return ['verified'=>true,'calculation_id'=>123,'revision'=>'r2','lines'=>$lines];}));
if ((int)$db->query('SELECT COUNT(*) FROM office_crm_calc_acceptances')->fetchColumn()!==1)throw new RuntimeException('Rollback failed');
echo "CRM_CALC_ACCEPTANCE_SNAPSHOT_WRITER=PASS\n";
