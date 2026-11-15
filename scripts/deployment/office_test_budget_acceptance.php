<?php
declare(strict_types=1);
// Included only inside the central, isolated Office-test acceptance process.
// Source records and all amounts below are unmistakably fictitious.
function budgetQuery(PDO $db,string $sql,array $values=[]): PDOStatement {
    $q=$db->prepare($sql);$q->execute($values);return $q;
}
function budgetPerson(PDO $db,int $project,string $suffix): int {
    $number='BREBO-CI-WB-'.($suffix==='reviewer'?'reviewer':$project.'-'.$suffix);
    $row=budgetQuery($db,'SELECT id,display_name FROM office_inzet_people WHERE personnel_number=?',[$number])->fetch(PDO::FETCH_ASSOC);
    $name='FICTIEF werkbegroting '.$suffix;
    if ($row) {
        if ($row['display_name']!==$name) throw new RuntimeException('BUDGET_PERSON_COLLISION');
        return (int)$row['id'];
    }
    budgetQuery($db,'INSERT INTO office_inzet_people (personnel_number,display_name,active) VALUES (?,?,1)',[$number,$name]);
    return (int)$db->lastInsertId();
}
function budgetLabourAcceptance(PDO $db,string $active,array $fixture,CurlHandle $leader,CurlHandle $reader): void {
    $id=$fixture['project_id'];$actor=$fixture['users']['leader']['id'];
    $person=budgetPerson($db,$id,'worker');$reviewPerson=budgetPerson($db,$id,'reviewer');
    $mapped=budgetQuery($db,'SELECT person_id FROM office_operator_people WHERE operator_id=?',[$actor])->fetchAll(PDO::FETCH_COLUMN);
    if ($mapped!==[] && $mapped!==[(string)$reviewPerson] && $mapped!==[$reviewPerson]) throw new RuntimeException('BUDGET_REVIEWER_COLLISION');
    if ($mapped===[]) budgetQuery($db,'INSERT INTO office_operator_people (operator_id,person_id) VALUES (?,?)',[$actor,$reviewPerson]);
    budgetQuery($db,'INSERT IGNORE INTO office_inzet_project_team (project_id,person_id,role_label) VALUES (?,?,?)',[$id,$person,'FICTIEVE acceptatiemedewerker']);
    $date=gmdate('Y-m-d');$in='BREBO-CI-WB-'.$id.'-in';$out='BREBO-CI-WB-'.$id.'-out';
    foreach ([[$in,'in','07:00:00'],[$out,'out','15:00:00']] as [$event,$kind,$time]) {
        budgetQuery($db,'INSERT IGNORE INTO office_onsite_event_inbox (event_id,person_id,project_id,zone_id,event_kind,occurred_at_utc) VALUES (?,?,?,?,?,?)',[$event,$person,(string)$id,'BREBO-CI-WB-'.$id,$kind,$date.'T'.$time.'Z']);
    }
    $rates=budgetQuery($db,'SELECT hourly_rate_cents FROM office_inzet_actual_labour_rates WHERE project_id=? AND person_id=?',[$id,$person])->fetchAll(PDO::FETCH_COLUMN);
    if ($rates===[]) budgetQuery($db,'INSERT INTO office_inzet_actual_labour_rates (project_id,person_id,valid_from,hourly_rate_cents,authorized_by) VALUES (?,?,?,?,?)',[$id,$person,$date,4250,$fixture['users']['reviewer']['id']]);
    else ensureTest(count($rates)===1 && (int)$rates[0]===4250,'budget_fixture_rate_unchanged');
    $url='https://brebobv.nl/office-test/inzet/onsite/aanwezigheid?id='.$id;
    $decision=budgetQuery($db,'SELECT status FROM office_onsite_hours_decisions WHERE in_event_id=? AND out_event_id=?',[$in,$out])->fetchColumn();
    [$status]=requestTest($reader,$url);ensureTest($status===404,'budget_hours_reader_denied');
    if ($decision===false) {
        [$status,$html]=requestTest($leader,$url);
        ensureTest($status===200 && preg_match('/name="csrf"\s+value="([a-f0-9]{64})"/',$html,$m)===1,'budget_hours_form');
        $post=['id'=>(string)$id,'csrf'=>$m[1],'in_event_id'=>$in,'out_event_id'=>$out,'review_action'=>'approve','approved_minutes'=>'360'];
        [$status]=requestTest($leader,$url,array_replace($post,['csrf'=>str_repeat('0',64)]));
        ensureTest($status===403,'budget_hours_csrf_denied');
        [$status]=requestTest($leader,$url,$post);ensureTest($status===303,'budget_six_of_eight_hours_approved_http');
    }
    require_once $active.'/src/Inzet/OfficeOnSiteApprovalService.php';
    // Enable valuation ONLY in this CLI process, never temporarily in public config.
    // Retrying the real HTTP approval exercises the same atomic posting service.
    $flags=['OFFICE_TEST_ACTUAL_LABOUR_COST_POSTING_ENABLED'=>'enabled','OFFICE_TEST_LABOUR_BUDGET_DEBIT_ENABLED'=>'disabled','OFFICE_TEST_LABOUR_SHIFT_AGGREGATE_ENABLED'=>'disabled'];
    $old=[];foreach ($flags as $key=>$value) {$old[$key]=getenv($key);putenv($key.'='.$value);}
    try {
        $selfBlocked=false;
        try {(new \Brebo\Office\Inzet\OfficeOnSiteFourEyesGuard($db))->assertIndependent($actor,$reviewPerson);} catch (DomainException $e) {$selfBlocked=true;}
        ensureTest($selfBlocked,'budget_self_approval_rejected');
        $service=new \Brebo\Office\Inzet\OfficeOnSiteApprovalService($db);
        $booking=$service->approve($id,$in,$out,$actor,21600);
        ensureTest($service->approve($id,$in,$out,$actor,21600)===$booking,'budget_labour_retry_idempotent');
        $posted=budgetQuery($db,'SELECT approved_seconds,hourly_rate_cents,amount_cents FROM office_inzet_actual_labour_costs WHERE approved_hours_id=?',[$booking])->fetchAll(PDO::FETCH_ASSOC);
        ensureTest(count($posted)===1 && (int)$posted[0]['approved_seconds']===21600 && (int)$posted[0]['hourly_rate_cents']===4250 && (int)$posted[0]['amount_cents']===25500,'budget_actual_labour_255_euro');
        $blocked=false;
        try {$service->approve($id,$in,$out,$actor,28800);} catch (DomainException $e) {$blocked=true;}
        ensureTest($blocked,'budget_approved_hours_cannot_be_overwritten');
    } finally {foreach ($old as $key=>$value) putenv($value===false?$key:$key.'='.$value);}
    echo 'OFFICE_TEST_LABOUR_URL='.$url."\n";
}
function budgetSourceAcceptance(PDO $db,string $active,array $fixture,string $release): int {
    $id=$fixture['project_id'];$actor=$fixture['users']['leader']['id'];
    $code='BREBO-CI-WB-'.$id;$title='FICTIEF - werkbegroting keten '.$id;
    $project=budgetQuery($db,'SELECT code FROM office_projects WHERE id=?',[$id])->fetchColumn();
    if ($project!=='BREBO-CI-'.substr($release,0,16)) throw new RuntimeException('BUDGET_SEED_SCOPE_DENIED');
    $relation=budgetQuery($db,'SELECT id FROM office_crm_relations WHERE display_name=?',[$title])->fetchColumn();
    if ($relation===false) {
        budgetQuery($db,"INSERT INTO office_crm_relations (relation_kind,display_name,status) VALUES ('company',?,'active')",[$title]);$relation=(int)$db->lastInsertId();
    }
    budgetQuery($db,"INSERT IGNORE INTO office_crm_relation_roles (relation_id,role_code) VALUES (?,'supplier')",[$relation]);
    $opportunity=budgetQuery($db,'SELECT id FROM office_crm_opportunities WHERE relation_id=? AND title=?',[$relation,$title])->fetchColumn();
    if ($opportunity===false) {
        budgetQuery($db,"INSERT INTO office_crm_opportunities (relation_id,title,stage,status) VALUES (?,?,'won','won')",[$relation,$title]);$opportunity=(int)$db->lastInsertId();
    }
    $draft=budgetQuery($db,'SELECT id FROM office_calc_crm_drafts WHERE opportunity_id=?',[$opportunity])->fetchColumn();
    if ($draft===false) {
        budgetQuery($db,'INSERT INTO office_calc_crm_drafts (opportunity_id,crm_revision,created_by) VALUES (?,1,?)',[$opportunity,$actor]);$draft=(int)$db->lastInsertId();
    }
    require_once $active.'/src/Calculation/OfficeCalcDecimalBudgetMapper.php';
    // Fractional quantities and prices exercise the actual additive v2 columns.
    $rows=array_merge(
        \Brebo\Office\Calculation\OfficeCalcDecimalBudgetMapper::map('CI-WB-L','8.0000',['labor'=>'42.5000','material'=>'0','equipment'=>'0','subcontract'=>'0','other'=>'0'],'FICTIEF arbeid'),
        \Brebo\Office\Calculation\OfficeCalcDecimalBudgetMapper::map('CI-WB-M','1.2500',['labor'=>'0','material'=>'123.4560','equipment'=>'0','subcontract'=>'0','other'=>'0'],'FICTIEF materiaal')
    );
    // Canonical accepted snapshot is stored first, never a verifier that returns true blindly.
    $revision='ci-wb-'.$release;
    budgetQuery($db,"INSERT IGNORE INTO office_crm_calc_acceptances (opportunity_id,calculation_id,accepted_revision,status,accepted_by) VALUES (?,?,?,'accepted',?)",[$opportunity,$draft,$revision,$actor]);
    foreach ($rows as $line) budgetQuery($db,'INSERT IGNORE INTO office_crm_calc_accepted_lines (opportunity_id,source_line_id,cost_type,quantity,unit_cost_cents,activity_ref) VALUES (?,?,?,?,?,?)',[$opportunity,$line['line_id'],$line['cost_type'],$line['quantity'],$line['unit_cost_cents'],$line['activity_ref']]);
    $acceptance=['crm_opportunity_id'=>(string)$opportunity,'calculation_id'=>(int)$draft,'accepted_revision'=>$revision,'status'=>'accepted'];
    $verify=static function(PDO $connection,array $context,array $lines) use ($opportunity,$rows): bool {
        $stored=budgetQuery($connection,'SELECT calculation_id,accepted_revision,status FROM office_crm_calc_acceptances WHERE opportunity_id=? FOR UPDATE',[$opportunity])->fetch(PDO::FETCH_ASSOC);
        if (!$stored || (int)$stored['calculation_id']!==$context['calculation_id'] || $stored['accepted_revision']!==$context['accepted_revision'] || $stored['status']!=='accepted' || $lines!==$rows) return false;
        $source=budgetQuery($connection,'SELECT source_line_id,cost_type,quantity,unit_cost_cents,activity_ref FROM office_crm_calc_accepted_lines WHERE opportunity_id=? ORDER BY source_line_id FOR UPDATE',[$opportunity])->fetchAll(PDO::FETCH_ASSOC);
        $expected=$rows;usort($expected,static fn($a,$b)=>strcmp($a['line_id'],$b['line_id']));
        if (count($source)!==count($expected)) return false;
        foreach ($expected as $i=>$line) {
            if ($source[$i]['source_line_id']!==$line['line_id'] || $source[$i]['cost_type']!==$line['cost_type'] || bccomp((string)$source[$i]['quantity'],(string)$line['quantity'],4)!==0 || bccomp((string)$source[$i]['unit_cost_cents'],(string)$line['unit_cost_cents'],2)!==0 || $source[$i]['activity_ref']!==$line['activity_ref']) return false;
        }
        return true;
    };
    require_once $active.'/src/Calculation/OfficeCalcCrmTransferService.php';
    $transfer=new \Brebo\Office\Calculation\OfficeCalcCrmTransferService($db);
    $existing=budgetQuery($db,'SELECT b.id FROM office_calc_crm_acceptance_transfers t JOIN office_calc_work_budget_baselines b ON b.acceptance_transfer_id=t.id WHERE t.office_project_id=?',[$id])->fetchColumn();
    if ($existing===false) {
        $bad=$rows;$bad[0]['unit_cost_cents']++;
        $blocked=false;try {$transfer->transfer($acceptance,$bad,static fn()=>throw new RuntimeException('UNVERIFIED_PROJECT_CALLBACK'),$verify);} catch (DomainException $e) {$blocked=true;}
        ensureTest($blocked,'budget_tampered_accepted_amount_rejected');
        $result=$transfer->transfer($acceptance,$rows,static fn(PDO $connection,array $a): int=>$id,$verify);
        $baseline=$result['work_budget_id'];
        budgetQuery($db,'UPDATE office_calc_crm_drafts SET project_id=? WHERE id=? AND project_id IS NULL',[$id,$draft]);
    } else $baseline=(int)$existing;
    $blocked=false;try {$transfer->transfer($acceptance,$rows,static fn()=>throw new RuntimeException('DUPLICATE_PROJECT_CALLBACK'),$verify);} catch (DomainException $e) {$blocked=true;}
    ensureTest($blocked,'budget_duplicate_transfer_rejected');
    require_once $active.'/src/WorkingBudget/LockedBaselineLines.php';
    $locked=(new \Brebo\Office\WorkingBudget\LockedBaselineLines($db))->forProject($id);
    ensureTest($locked!==null && count($locked['lines'])===2 && $locked['locked_at']!=='' && array_sum(array_column($locked['lines'],'baseline_cents'))===49432,'budget_accepted_source_locked_494_32');
    ensureTest($locked['lines'][1]['quantity_decimal']==='1.2500' && $locked['lines'][1]['unit_cost_decimal']==='123.4560','budget_exact_quantity_and_price_retained');
    echo 'OFFICE_TEST_BUDGET_BASELINE='.$baseline."\n";
    return (int)$relation;
}
function budgetProcurementAcceptance(PDO $db,string $active,array $fixture,int $supplier): void {
    $id=$fixture['project_id'];$leader=$fixture['users']['leader']['id'];$reviewer=$fixture['users']['reviewer']['id'];
    $code='BREBO-CI-WB-'.$id;$name='FICTIEF - werkbegroting gebouw '.$id;
    $building=budgetQuery($db,'SELECT id FROM office_buildings WHERE code=? AND name=?',[$code,$name])->fetchColumn();
    if ($building===false) {budgetQuery($db,'INSERT INTO office_buildings (code,name) VALUES (?,?)',[$code,$name]);$building=(int)$db->lastInsertId();}
    budgetQuery($db,'INSERT IGNORE INTO office_project_buildings (project_id,building_id) VALUES (?,?)',[$id,$building]);
    foreach ([$leader,$reviewer] as $actor) foreach (['read','write','approve','award','receive'] as $permission) budgetQuery($db,"INSERT IGNORE INTO office_module_permissions (operator_id,module,permission) VALUES (?,'procurement',?)",[$actor,$permission]);
    require_once $active.'/src/Governance/bootstrap.php';
    require_once $active.'/src/Governance/OfficeProcurementOrderSource.php';
    $service=new \Brebo\Office\Governance\OfficeGovernanceService(new \Brebo\Office\Governance\Infrastructure\PdoOfficeGovernanceStore($db));
    $source=new \Brebo\Office\Governance\OfficeProcurementOrderSource($service);
    $orders=$source->awardedProjectOrders($leader,$id,[(int)$building]);
    if ($orders===[]) {
        $input=['project_id'=>$id,'building_id'=>(int)$building,'title'=>'FICTIEF - inkoop werkbegroting','status'=>'draft','supplier_relation_id'=>$supplier,'quantity'=>1,'unit_price_cents'=>12000,'description'=>'FICTIEVE materiaalopdracht','due_date'=>gmdate('Y-m-d'),'owner'=>$leader,'selection_reason'=>'FICTIEVE ketenacceptatie','evidence'=>'FICTIEF bestelbewijs'];
        foreach (['draft','submitted','approved','ordered'] as $state) {
            $input['status']=$state;$input['command']=bin2hex(random_bytes(16));
            $record=$service->save($state==='approved'?$reviewer:$leader,'procurement',$input);
            $input['id']=(int)$record['id'];$input['revision']=(int)$record['revision'];
        }
    }
    $commitment=$source->awardedProjectCommitment($leader,$id,[(int)$building]);
    ensureTest($commitment['commitment_cents']===12000 && $commitment['awarded_order_count']===1,'budget_awarded_procurement_120_euro');
    require_once $active.'/src/Governance/OfficeWorkBudgetBaselineReader.php';
    require_once $active.'/src/Governance/OfficeWorkBudgetCommitmentComparison.php';
    $baseline=(new \Brebo\Office\Governance\OfficeWorkBudgetBaselineReader($db))->forProject($id);
    $comparison=\Brebo\Office\Governance\OfficeWorkBudgetCommitmentComparison::summarize($baseline,$commitment);
    ensureTest($comparison['budget_cents']===15432 && $comparison['remaining_cents']===3432,'budget_procurement_control_remaining_34_32');
}
