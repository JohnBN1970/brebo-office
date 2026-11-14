<?php
declare(strict_types=1);
/** Session-local tables shadow real names. No business record is inserted. */
function probeCalcAcceptance(PDO $db, string $root): void
{
    foreach(['office_crm_calc_acceptances','office_crm_calc_accepted_lines','office_calc_crm_acceptance_transfers','office_calc_work_budget_baselines','office_calc_work_budget_lines']as$table)$db->exec('CREATE TEMPORARY TABLE `'.$table.'` LIKE `'.$table.'`');
    $db->exec('CREATE TEMPORARY TABLE office_crm_opportunities(id BIGINT PRIMARY KEY,stage VARCHAR(20),status VARCHAR(20))');
    $db->exec('CREATE TEMPORARY TABLE office_projects(id BIGINT PRIMARY KEY AUTO_INCREMENT,title VARCHAR(191))');
    $db->exec("INSERT INTO office_crm_opportunities VALUES(11,'won','closed')");
    foreach(['OfficeCalcAcceptanceSnapshotWriter','OfficeCalcCrmTransferService','OfficeCalcPersistedAcceptanceVerifier']as$class)require_once $root.'/src/Calculation/'.$class.'.php';
    $lines=[['line_id'=>'decimal-probe','cost_type'=>'material','quantity'=>2,'unit_cost_cents'=>1235,'quantity_decimal'=>'2.5000','unit_cost_decimal'=>'12.3456','activity_ref'=>null]];
    $source=static fn(PDO $db,int $opp,int $calc,string $revision,int $actor):array=>['verified'=>true,'calculation_id'=>$calc,'revision'=>$revision,'lines'=>$lines];
    (new \Brebo\Office\Calculation\OfficeCalcAcceptanceSnapshotWriter($db))->accept(11,400,'probe-1',7,$source);
    $acceptance=['crm_opportunity_id'=>'11','calculation_id'=>400,'accepted_revision'=>'probe-1','status'=>'accepted'];
    $create=static function(PDO $db,array $a):int{$db->exec("INSERT INTO office_projects(title) VALUES('Session-local acceptance probe')");return(int)$db->lastInsertId();};
    $transfer=new \Brebo\Office\Calculation\OfficeCalcCrmTransferService($db);
    $verify=new \Brebo\Office\Calculation\OfficeCalcPersistedAcceptanceVerifier();
    $changed=$lines;$changed[0]['unit_cost_decimal']='12.3457';$denied=false;
    try{$transfer->transfer($acceptance,$changed,$create,$verify);}catch(DomainException){$denied=true;}
    if(!$denied||(int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()!==0)throw new RuntimeException('price_mismatch_probe_failed');
    $result=$transfer->transfer($acceptance,$lines,$create,$verify);
    $q=$db->query('SELECT l.quantity,l.unit_cost_cents,l.budget_cents,b.locked_at FROM office_calc_work_budget_lines l JOIN office_calc_work_budget_baselines b ON b.id=l.baseline_id');$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row||$row['quantity']!=='2.5000'||$row['unit_cost_cents']!=='1234.56'||(int)$row['budget_cents']!==3086||empty($row['locked_at'])||$result['project_id']!==1)throw new RuntimeException('exact_roundtrip_probe_failed');
    $denied=false;try{$transfer->transfer($acceptance,$lines,$create,$verify);}catch(DomainException){$denied=true;}
    if(!$denied||(int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()!==1)throw new RuntimeException('duplicate_probe_failed');
    echo "CALC_ACCEPTANCE_HOST_PROBE=EXACT_DECIMALS_LOCKED_BASELINE_PASS\nCALC_ACCEPTANCE_HOST_PROBE_TABLES=SESSION_LOCAL_ONLY\n";
}
