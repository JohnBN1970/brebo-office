<?php
declare(strict_types=1);
function probeQuotationHandoff(PDO $db,string $root):void
{
 $tables=['office_quotations','office_quotation_versions','office_quotation_sections','office_quotation_budget_bindings','office_commercial_acceptances','office_projects','office_project_memberships','office_calc_crm_acceptance_transfers','office_calc_work_budget_baselines','office_calc_work_budget_lines','office_crm_calc_acceptances','office_crm_calc_accepted_lines','office_crm_opportunities','office_calc_crm_drafts','office_calc_crm_draft_collaborators','calculations','calculation_versions','calculation_lines','office_documents','office_document_originals','office_document_contexts'];
 foreach($tables as$t){$body=$db->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1];$parts=array_filter(explode("\n",$body),static fn(string$l):bool=>!str_contains($l,'FOREIGN KEY'));$body=preg_replace('/,\n\)/',"\n)",implode("\n",$parts));$body=preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$body,1,$count);if($count!==1||str_contains($body,'FOREIGN KEY'))throw new RuntimeException('probe_table_definition');$db->exec($body);}
 // Match the string collation of the cloned application tables in the session.
 $db->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
 $db->exec('CREATE TEMPORARY TABLE office_operator_accounts(id VARCHAR(128) PRIMARY KEY,active INTEGER,can_edit_crm INTEGER)');$db->exec('CREATE TEMPORARY TABLE office_crm_relations(id BIGINT PRIMARY KEY,status VARCHAR(20))');
 $db->exec("INSERT INTO office_operator_accounts VALUES('7',1,1),('8',1,1)");$db->exec("INSERT INTO office_crm_relations VALUES(10,'active')");
 $db->exec("INSERT INTO office_crm_opportunities(id,title,relation_id,revision,stage,status)VALUES(11,'Temporary quotation probe',10,1,'proposal','open')");
 $db->exec('INSERT INTO office_calc_crm_drafts(id,opportunity_id,crm_revision,created_by)VALUES(1,11,1,7)');
 $db->exec("INSERT INTO calculations(id,office_calculation_id,code,title)VALUES(21,1,'probe','Temporary source')");
 $db->exec("INSERT INTO calculation_versions(id,calculation_id,version_no,direct_cost)VALUES(31,21,1,'30.8640')");
 $db->exec("INSERT INTO calculation_lines(id,version_id,sort_order,line_type,description,quantity,material_unit_cost)VALUES(41,31,1,'item','Temporary item','2.5000','12.3456')");
 $sha=str_repeat('a',64);$q=$db->prepare('INSERT INTO office_document_originals(id,sha256,storage_provider,storage_key,byte_size)VALUES(50,?,?,?,100)');$q->execute([$sha,'office_private','originals/aa/'.$sha]);
 $db->exec("INSERT INTO office_documents(id,original_id,title,original_filename,mime_type)VALUES(51,50,'Temporary evidence','probe.pdf','application/pdf')");$db->exec("INSERT INTO office_document_contexts(document_id,context_type,context_id,relation_role)VALUES(51,'organization','10','contractual')");
 require_once $root.'/src/Quotation/OfficeQuotationCalcGenerationService.php';require_once $root.'/src/Quotation/OfficeQuotationBudgetHandoff.php';
 $source=['relation_id'=>10,'opportunity_id'=>11,'title'=>'Temporary quotation probe','sales_snapshot'=>['calculation_id'=>1,'version'=>1,'sales_lines'=>[['rubric'=>'Material','description'=>'Temporary item','quantity'=>'2.5000','unit'=>'m2','amount_ex_vat_cents'=>5000]],'total_ex_vat_cents'=>5000]];
 $resolve=static fn(int$a,int$id,int$v):array=>$source;
 $old=getenv('OFFICE_QUOTATION_COST_BINDING_ENABLED');putenv('OFFICE_QUOTATION_COST_BINDING_ENABLED=enabled');
 try{
  $quote=(new \Brebo\Office\Quotation\OfficeQuotationCalcGenerationService($db))->generate(7,['action'=>'generate_quotation','csrf'=>str_repeat('c',64),'calculation_id'=>1,'calculation_version'=>1],$resolve);
  $hash=(string)$db->query('SELECT cost_sha256 FROM office_quotation_budget_bindings')->fetchColumn();
  $q=$db->prepare("UPDATE office_quotations SET status='sent',approved_by=8,approved_at=CURRENT_TIMESTAMP WHERE id=?");$q->execute([$quote]);
  $service=new \Brebo\Office\Quotation\OfficeQuotationBudgetHandoff($db);
  $rejected=false;try{$service->transfer($quote,7,1,$hash,null,true);}catch(DomainException){$rejected=true;}if(!$rejected||(int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()!==0)throw new RuntimeException('evidence_probe_failed');
  $result=$service->transfer($quote,7,1,$hash,51,true);$again=$service->transfer($quote,7,1,$hash,null,true);
  $row=$db->query('SELECT quantity,unit_cost_cents,budget_cents FROM office_calc_work_budget_lines')->fetch(PDO::FETCH_ASSOC);
  if(!$again['replayed']||$result['project_id']!==$again['project_id']||!$row||$row['quantity']!=='2.5000'||$row['unit_cost_cents']!=='1234.56'||(int)$row['budget_cents']!==3086||(int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()!==1||$db->query('SELECT status FROM office_quotations')->fetchColumn()!=='accepted')throw new RuntimeException('quotation_handoff_probe_failed');
  echo "QUOTATION_HANDOFF_HOST_PROBE=GENERATION_BINDING_EVIDENCE_ACCEPTANCE_PROJECT_LOCKED_BUDGET_PASS\nQUOTATION_HANDOFF_HOST_PROBE_TABLES=SESSION_LOCAL_ONLY\n";
 }finally{if($old===false)putenv('OFFICE_QUOTATION_COST_BINDING_ENABLED');else putenv('OFFICE_QUOTATION_COST_BINDING_ENABLED='.$old);}
}
