<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
use PDO;
require_once __DIR__.'/OfficeSalesBilling.php';
require_once __DIR__.'/OfficeSalesApproval.php';
require_once __DIR__.'/OfficeSalesParties.php';

/** Office owns customer snapshots, lines, totals and history. No invoice imports. */
final class OfficeSalesService {
  public function __construct(private readonly PDO $db, private readonly string $operatorId) {}

  private function authorize(bool $write = false): void {
    foreach ($write ? ['read','draft_edit'] : ['read'] as $permission) {
      $q=$this->db->prepare("SELECT 1 FROM office_module_permissions g JOIN office_operator_accounts a ON a.id=g.operator_id WHERE g.operator_id=? AND g.module='finance' AND g.permission=? AND a.active=1");
      $q->execute([$this->operatorId,$permission]);
      if ($q->fetchColumn()===false) throw new \DomainException('Geen toegang tot verkoopfacturen.');
    }
  }

  private function project(?int $id): void {
    if ($id === null) return;
    $q=$this->db->prepare("SELECT 1 FROM office_project_memberships WHERE operator_id=? AND project_id=? AND permission IN ('read','manage')");
    $q->execute([$this->operatorId,$id]);
    if ($q->fetchColumn()===false) throw new \DomainException('Geen toegang tot dit project.');
  }

  public function projects(): array {
    $this->authorize();
    $q=$this->db->prepare("SELECT DISTINCT p.id,p.title FROM office_projects p JOIN office_project_memberships m ON m.project_id=p.id WHERE m.operator_id=? AND m.permission IN ('read','manage') ORDER BY p.title");
    $q->execute([$this->operatorId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$p)$p['billing']=OfficeSalesParties::billing($this->db,(int)$p['id']);return $rows;
  }

  public function customers(): array {
    $this->authorize();
    $rows=$this->db->query("SELECT * FROM office_crm_relations WHERE status='active' ORDER BY display_name")->fetchAll(PDO::FETCH_ASSOC);$result=[];foreach($rows as $row){$c=OfficeSalesParties::snapshot($this->db,$row,0);$c['contacts']=OfficeSalesParties::contacts($this->db,(int)$row['id']);$result[]=$c;}return $result;
  }

  public function list(): array {
    $this->authorize();
    $q=$this->db->prepare("SELECT i.*,c.price_mode,c.labor_cents,c.g_rate_units,c.g_cents,c.g_iban,c.source_contract_id,c.source_term_id,c.term_percent_units,c.source_snapshot,c.summary_json FROM office_finance_sales_invoice i LEFT JOIN office_finance_sales_context c ON c.invoice_id=i.id WHERE (i.office_project_id IS NULL AND i.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_memberships m WHERE m.project_id=i.office_project_id AND m.operator_id=? AND m.permission IN ('read','manage')) ORDER BY i.id DESC LIMIT 200");
    $q->execute([$this->operatorId,$this->operatorId]); return array_map(OfficeSalesBilling::defaults(...),$q->fetchAll(PDO::FETCH_ASSOC));
  }

  public function get(int $id, bool $write = false): array {
    $this->authorize($write);
    $q=$this->db->prepare('SELECT i.*,c.price_mode,c.labor_cents,c.g_rate_units,c.g_cents,c.g_iban,c.source_contract_id,c.source_term_id,c.term_percent_units,c.source_snapshot,c.summary_json FROM office_finance_sales_invoice i LEFT JOIN office_finance_sales_context c ON c.invoice_id=i.id WHERE i.id=?'); $q->execute([$id]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row || ($row['office_project_id']===null && (string)$row['operator_id']!==$this->operatorId)) throw new \DomainException('Factuur niet beschikbaar.');
    $this->project($row['office_project_id']===null ? null : (int)$row['office_project_id']);
    $row=OfficeSalesBilling::defaults($row);if(OfficeSalesApproval::enabled()){$q=$this->db->prepare('SELECT * FROM office_finance_sales_approvals WHERE invoice_id=?');$q->execute([$id]);$approval=$q->fetch(PDO::FETCH_ASSOC);if($approval&&(int)$approval['invoice_revision']===(int)$row['revision']&&hash_equals($approval['content_hash'],OfficeSalesApproval::hash($row)))$row['_approval']=$approval;}return $row;
  }

  public static function totals(array $lines,string $mode='excl'): array {return OfficeSalesBilling::totals($lines,$mode);}

  public function termsAvailable(): bool {
    $this->authorize();
    if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return (int)$this->db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('office_project_contracts','office_project_terms')")->fetchColumn()===2;
    return (int)$this->db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('office_project_contracts','office_project_terms')")->fetchColumn()===2;
  }

  public function termChoices(): array {
    if(!$this->termsAvailable())return [];
    $q=$this->db->prepare("SELECT t.id,t.term_number,t.description,t.percentage,t.amount,t.due_date,c.contract_number,c.office_project_id,p.title AS project_title
      FROM office_project_terms t JOIN office_project_contracts c ON c.id=t.contract_id JOIN office_projects p ON p.id=c.office_project_id
      LEFT JOIN office_finance_sales_context x ON x.source_term_id=t.id
      WHERE x.invoice_id IS NULL AND t.amount>0 AND c.contract_amount>0 AND c.currency='EUR'
      AND t.status NOT IN ('cancelled','canceled','void','paid','invoiced') AND c.status NOT IN ('cancelled','canceled','void')
      AND EXISTS(SELECT 1 FROM office_project_memberships m WHERE m.project_id=p.id AND m.operator_id=? AND m.permission IN ('read','manage'))
      ORDER BY p.title,t.term_number LIMIT 300");$q->execute([$this->operatorId]);return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  public function sourceTerm(int $id,bool $lock=false): array {
    $this->authorize();if($id<1||!$this->termsAvailable())throw new \DomainException('Termijn niet beschikbaar.');
    $q=$this->db->prepare("SELECT t.id,t.contract_id,t.term_number,t.description,t.percentage,t.amount,t.status AS term_status,t.due_date,c.office_project_id,c.contract_number,c.contract_amount,c.currency,c.status AS contract_status,p.title AS project_title
      FROM office_project_terms t JOIN office_project_contracts c ON c.id=t.contract_id JOIN office_projects p ON p.id=c.office_project_id WHERE t.id=?".($lock&&$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$id]);$term=$q->fetch(PDO::FETCH_ASSOC);
    if(!$term)throw new \DomainException('Termijn niet beschikbaar.');$this->project((int)$term['office_project_id']);
    if($term['currency']!=='EUR'||in_array($term['term_status'],['cancelled','canceled','void','paid','invoiced'],true)||in_array($term['contract_status'],['cancelled','canceled','void'],true))throw new \DomainException('Deze termijn is niet beschikbaar voor een nieuwe factuur.');
    return $term;
  }

  public function contractSummary(array $term,?int $id,int $net,int $gross,?int $percent): array {
    $this->authorize();$this->project((int)$term['office_project_id']);
    $q=$this->db->prepare("SELECT COALESCE(SUM(i.net_cents),0) AS net,COALESCE(SUM(i.gross_cents),0) AS gross,COALESCE(SUM(i.paid_cents),0) AS paid
      FROM office_finance_sales_invoice i JOIN office_finance_sales_context x ON x.invoice_id=i.id WHERE x.source_contract_id=? AND i.id<>? AND i.invoice_number IS NOT NULL");$q->execute([$term['contract_id'],$id??0]);$previous=$q->fetch(PDO::FETCH_ASSOC);
    $q=$this->db->prepare("SELECT COUNT(*) FROM office_project_terms t LEFT JOIN office_finance_sales_context x ON x.source_term_id=t.id WHERE t.contract_id=? AND t.status IN ('paid','invoiced') AND x.invoice_id IS NULL");$q->execute([$term['contract_id']]);$complete=(int)$q->fetchColumn()===0;
    $contract=OfficeSalesBilling::cents((string)$term['contract_amount']);$open=max(0,(int)$previous['gross']-(int)$previous['paid']);
    return ['contract_number'=>(string)($term['contract_number']??''),'contract_net_cents'=>$contract,'term_number'=>(int)$term['term_number'],'term_percent_units'=>$percent,'term_net_cents'=>$net,'term_gross_cents'=>$gross,'previous_net_cents'=>(int)$previous['net'],'paid_gross_cents'=>(int)$previous['paid'],'previous_open_gross_cents'=>$open,'due_including_current_gross_cents'=>$open+$gross,'unbilled_after_net_cents'=>max(0,$contract-(int)$previous['net']-$net),'coverage_complete'=>$complete];
  }

  public function termDraft(int $termId,string $today): array {
    $term=$this->sourceTerm($termId);$billing=OfficeSalesParties::billing($this->db,(int)$term['office_project_id']);$percent=$term['percentage']===null?'':(string)$term['percentage'];$amount=OfficeSalesBilling::termAmount($term,$percent);
    $line=['description'=>'Termijn '.$term['term_number'].' - '.$term['description'],'quantity'=>'1','price'=>OfficeSalesBilling::decimal($amount['net_cents']),'vat'=>'21'];$totals=self::totals([$line]);
    return OfficeSalesBilling::defaults(['id'=>0,'revision'=>0,'status'=>'draft','relation_id'=>$billing['relation_id'],'_contact_input'=>$billing['contact_id'],'office_project_id'=>(int)$term['office_project_id'],'invoice_date'=>$today,'due_date'=>$term['due_date']?:((new \DateTimeImmutable($today))->modify('+14 days')->format('Y-m-d')),'reference'=>'','lines_json'=>json_encode($totals['lines'],JSON_THROW_ON_ERROR),'net_cents'=>$totals['net_cents'],'vat_cents'=>$totals['vat_cents'],'gross_cents'=>$totals['gross_cents'],'source_term_id'=>$termId,'source_contract_id'=>(int)$term['contract_id'],'term_percent_units'=>$amount['term_percent_units'],'source_snapshot'=>json_encode($term,JSON_THROW_ON_ERROR),'summary_json'=>json_encode($this->contractSummary($term,null,$totals['net_cents'],$totals['gross_cents'],$amount['term_percent_units']),JSON_THROW_ON_ERROR)]);
  }

  private static function date(string $date): string {
    $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$d || $d->format('Y-m-d')!==$date) throw new \InvalidArgumentException('Ongeldige datum.');
    return $date;
  }

  public function save(array $input,?int $id=null,int $revision=0): int {
    $this->authorize(true);$prior=$id===null?null:$this->get($id,true);
    $termId=filter_var($input['source_term_id']??($prior['source_term_id']??0),FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if($termId===false)throw new \InvalidArgumentException('Ongeldige termijn.');
    if($prior!==null&&(int)($prior['source_term_id']??0)!==$termId)throw new \DomainException('De brontermijn van een opgeslagen factuur kan niet worden gewijzigd.');
    $source=$termId>0?$this->sourceTerm($termId):null;
    $project=($input['office_project_id']??'')===''?null:filter_var($input['office_project_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($project===false)throw new \InvalidArgumentException('Ongeldig project.');
    if($source!==null){if($project!==(int)$source['office_project_id'])throw new \DomainException('De termijn hoort bij een ander project.');$project=(int)$source['office_project_id'];}
    $this->project($project);
    $customerId=filter_var($input['relation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($customerId===false)throw new \InvalidArgumentException('Kies een relatie.');
    $q=$this->db->prepare("SELECT * FROM office_crm_relations WHERE id=? AND status='active'");$q->execute([$customerId]);$customer=$q->fetch(PDO::FETCH_ASSOC);if(!$customer)throw new \InvalidArgumentException('Relatie niet beschikbaar.');$customer=OfficeSalesParties::snapshot($this->db,$customer,$input['contact_id']??0);
    if($project!==null){$q=$this->db->prepare('SELECT title FROM office_projects WHERE id=?');$q->execute([$project]);$title=$q->fetchColumn();if($title===false)throw new \DomainException('Project niet beschikbaar.');$customer['_office_project']=['id'=>$project,'title'=>(string)$title];}
    $date=self::date((string)($input['invoice_date']??''));$due=array_key_exists('payment_days',$input)?OfficeSalesBilling::dueDate($date,$input['payment_days']):self::date((string)($input['due_date']??''));if($due<$date)throw new \InvalidArgumentException('Vervaldatum ligt voor factuurdatum.');
    $reference=trim((string)($input['reference']??''));if(strlen($reference)>255||preg_match('/[\x00-\x1f]/',$reference))throw new \InvalidArgumentException('Ongeldige referentie.');
    $mode=(string)($input['price_mode']??($prior['price_mode']??'excl'));$percent=null;$lines=$input['lines']??[];
    if($source!==null){
      if($mode!=='excl')throw new \InvalidArgumentException('Termijnen worden berekend over de aanneemsom exclusief btw.');
      $termAmount=OfficeSalesBilling::termAmount($source,(string)($input['term_percent']??''));$percent=$termAmount['term_percent_units'];
      if(!is_array($lines)||count($lines)!==1)throw new \InvalidArgumentException('Een termijnfactuur bevat één termijn.');$line=array_values($lines)[0];
      if(!in_array(str_replace(',','.',(string)($line['quantity']??'')),['1','1.0','1.00','1.000'],true)||OfficeSalesBilling::cents((string)($line['price']??''),7)!==$termAmount['net_cents'])throw new \InvalidArgumentException('Het termijnbedrag is gewijzigd; controleer percentage en bedrag opnieuw.');
      $lines=[['description'=>'Termijn '.$source['term_number'].' - '.$source['description'],'quantity'=>'1','price'=>OfficeSalesBilling::decimal($termAmount['net_cents']),'vat'=>(string)($line['vat']??'')]];
    }
    $totals=self::totals($lines,$mode);$allocation=OfficeSalesBilling::allocation($input,$totals['net_cents'],$totals['gross_cents']);$now=gmdate('c');
    $this->db->beginTransaction();
    try{
      if($source!==null){
        if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
          $q=$this->db->prepare('SELECT id FROM office_project_contracts WHERE id=? FOR UPDATE');$q->execute([$source['contract_id']]);
          $q=$this->db->prepare('SELECT id FROM office_project_terms WHERE id=? FOR UPDATE');$q->execute([$termId]);
        }
        if($this->sourceTerm($termId,true)!==$source)throw new \DomainException('De termijn is gewijzigd; laad het concept opnieuw.');
        $q=$this->db->prepare('SELECT COALESCE(SUM(i.net_cents),0) FROM office_finance_sales_invoice i JOIN office_finance_sales_context x ON x.invoice_id=i.id WHERE x.source_contract_id=? AND i.id<>?');$q->execute([$source['contract_id'],$id??0]);
        if((int)$q->fetchColumn()+$totals['net_cents']>OfficeSalesBilling::cents((string)$source['contract_amount']))throw new \DomainException('De gekoppelde facturen en concepten overschrijden samen de aanneemsom.');
      }
      $values=[$project,$customerId,json_encode($customer,JSON_THROW_ON_ERROR),$date,$due,$reference,json_encode($totals['lines'],JSON_THROW_ON_ERROR),$totals['net_cents'],$totals['vat_cents'],$totals['gross_cents'],$now];
      if($id===null){
        $q=$this->db->prepare('INSERT INTO office_finance_sales_invoice (office_project_id,relation_id,customer_json,invoice_date,due_date,reference,lines_json,net_cents,vat_cents,gross_cents,updated_at,operator_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute([...$values,$this->operatorId,$now]);$id=(int)$this->db->lastInsertId();
      }else{
        $row=$this->get($id,true);if($row['status']!=='draft')throw new \DomainException('Een vrijgegeven factuur is niet wijzigbaar.');
        $q=$this->db->prepare("UPDATE office_finance_sales_invoice SET office_project_id=?,relation_id=?,customer_json=?,invoice_date=?,due_date=?,reference=?,lines_json=?,net_cents=?,vat_cents=?,gross_cents=?,updated_at=?,revision=revision+1 WHERE id=? AND revision=? AND status='draft'");$q->execute([...$values,$id,$revision]);if($q->rowCount()!==1)throw new \DomainException('Het concept is inmiddels gewijzigd; laad opnieuw.');
      }
      $summary=$source===null?null:json_encode($this->contractSummary($source,$id,$totals['net_cents'],$totals['gross_cents'],$percent),JSON_THROW_ON_ERROR);
      $context=[$mode,$allocation['labor_cents'],$allocation['g_rate_units'],$allocation['g_cents'],$allocation['g_iban'],$source===null?null:(int)$source['contract_id'],$source===null?null:$termId,$percent,$source===null?null:json_encode($source,JSON_THROW_ON_ERROR),$summary];
      $q=$this->db->prepare('SELECT invoice_id FROM office_finance_sales_context WHERE invoice_id=?');$q->execute([$id]);
      if($q->fetchColumn()===false){$q=$this->db->prepare('INSERT INTO office_finance_sales_context (price_mode,labor_cents,g_rate_units,g_cents,g_iban,source_contract_id,source_term_id,term_percent_units,source_snapshot,summary_json,invoice_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)');}
      else{$q=$this->db->prepare('UPDATE office_finance_sales_context SET price_mode=?,labor_cents=?,g_rate_units=?,g_cents=?,g_iban=?,source_contract_id=?,source_term_id=?,term_percent_units=?,source_snapshot=?,summary_json=? WHERE invoice_id=?');}
      $q->execute([...$context,$id]);$this->audit($id,'draft_saved',['source_term_id'=>$source===null?null:$termId]);$this->db->commit();return $id;
    }catch(\Throwable $e){$this->db->rollBack();if($source!==null&&$e instanceof \PDOException&&in_array((string)$e->getCode(),['23000','23505','19'],true))throw new \DomainException('Voor deze termijn bestaat al een factuur.');throw $e;}
  }

  public static function status(array $row, string $today): string {
    if ($row['status']!=='sent') return (string)$row['status'];
    if ((int)$row['paid_cents'] >= (int)$row['gross_cents']) return 'paid';
    if ((string)$row['due_date']<$today) return 'overdue';
    return (int)$row['paid_cents']>0 ? 'partially_paid' : 'sent';
  }

  /** Freeze the reviewed Office payload before any provider call. */
  public function prepare(int $id, int $revision, string $operation, string $recipient, string $message, array $taxMap, string $today): int {
    $this->authorize(true);
    if (!in_array($operation,['invoice','reminder'],true) || !filter_var($recipient,FILTER_VALIDATE_EMAIL) || strlen($recipient)>254 || str_contains($recipient,"\r") || str_contains($recipient,"\n") || trim($message)==='' || strlen($message)>10000) throw new \InvalidArgumentException('Controleer ontvanger en bericht.');
    self::date($today);
    $this->db->beginTransaction();
    try {
      $row=$this->get($id,true);
      if ((int)$row['revision']!==$revision) throw new \DomainException('Factuur gewijzigd; controleer opnieuw.');
      if ($operation==='invoice' && ($row['status']!=='draft' || (int)$row['gross_cents']<=0)) throw new \DomainException('Alleen een positief concept kan worden verzonden.');
      if ($operation==='reminder' && (self::status($row,$today)!=='overdue' || $row['last_reminder_date']===$today)) throw new \DomainException('Alleen vervallen openstaande facturen kunnen worden herinnerd; maximaal eenmaal per dag.');
      if ($operation==='invoice') {
        if((int)$row['g_cents']>0&&!OfficeSalesBilling::validGiban((string)$row['g_iban']))throw new \DomainException('Vul eerst het bevestigde G-rekeningnummer in.');
        if((int)($row['source_term_id']??0)>0){
          if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$lock=$this->db->prepare('SELECT id FROM office_project_contracts WHERE id=? FOR UPDATE');$lock->execute([$row['source_contract_id']]);}
          $term=$this->sourceTerm((int)$row['source_term_id'],true);
          if(json_encode($term,JSON_THROW_ON_ERROR)!==$row['source_snapshot'])throw new \DomainException('De brontermijn is gewijzigd; controleer en sla het concept opnieuw op.');
          if(!in_array($term['contract_status'],['signed','accepted','approved','active','confirmed'],true))throw new \DomainException('Het contract is nog niet vrijgegeven voor verzending.');
          $summary=$this->contractSummary($term,$id,(int)$row['net_cents'],(int)$row['gross_cents'],$row['term_percent_units']===null?null:(int)$row['term_percent_units']);
          if(!$summary['coverage_complete'])throw new \DomainException('Koppel eerdere gefactureerde termijnen voordat deze termijn wordt verzonden.');
          $this->db->prepare('UPDATE office_finance_sales_context SET summary_json=? WHERE invoice_id=?')->execute([json_encode($summary,JSON_THROW_ON_ERROR),$id]);
        }
        $q=$this->db->prepare("SELECT external_id FROM office_crm_external_identities WHERE relation_id=? AND provider_code='moneybird'"); $q->execute([$row['relation_id']]);
        $ids=$q->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids)!==1 || !preg_match('/^\d+$/D',(string)$ids[0])) throw new \DomainException('Koppel deze BREBO-relatie eerst aan één Moneybird-contact.');
        $customer=json_decode($row['customer_json'],true,32,JSON_THROW_ON_ERROR);
        foreach (['street','postal_code','city','country_code'] as $field) if (trim((string)($customer[$field]??''))==='') throw new \DomainException('Factuuradres ontbreekt in het concept.');
        $details=[];
        foreach (json_decode($row['lines_json'],true,32,JSON_THROW_ON_ERROR) as $line) {
          $tax=$taxMap[$line['vat']]??null;
          if (!is_string($tax) || !preg_match('/^\d+$/D',$tax)) throw new \DomainException('Btw-koppeling voor Moneybird ontbreekt.');
          $details[]=['description'=>$line['description'],'amount'=>$line['quantity'],'price'=>$line['price'],'tax_rate_id'=>$tax];
        }
        $payload=['sales_invoice'=>['contact_id'=>(string)$ids[0],'reference'=>'BREBO-'.$id.($row['reference']!=='' ? ' · '.$row['reference'] : ''),'invoice_date'=>$row['invoice_date'],'due_date'=>$row['due_date'],'currency'=>'EUR','prices_are_incl_tax'=>$row['price_mode']==='incl','details_attributes'=>$details], 'sending'=>['delivery_method'=>'Email','email_address'=>$recipient,'email_message'=>$message]];
        if((int)$row['g_cents']>0)$payload['sales_invoice']['payment_conditions']='Van dit factuurbedrag: EUR '.OfficeSalesBilling::decimal((int)$row['g_cents']).' naar G-rekening '.$row['g_iban'].' ('.OfficeSalesBilling::percentText((int)$row['g_rate_units']).'% van arbeid EUR '.OfficeSalesBilling::decimal((int)$row['labor_cents']).' exclusief btw), en EUR '.OfficeSalesBilling::decimal((int)$row['gross_cents']-(int)$row['g_cents']).' naar NL19 ABNA 0894 3515 67. Vermeld het factuurnummer.';
      } else {
        $provider=json_decode($row['provider_json']??'null',true,32,JSON_THROW_ON_ERROR);
        foreach (['id','contact_id','workflow_id','document_style_id','identity_id'] as $field) if (!is_string($provider[$field]??null) || !preg_match('/^\d+$/D',$provider[$field])) throw new \DomainException('Verzendbewijs voor deze factuur is onvolledig.');
        $payload=['reminder'=>array_intersect_key($provider,array_flip(['id','contact_id','workflow_id','document_style_id','identity_id']))+['email_address'=>$recipient,'reminder_text'=>$message]];
      }
      if($operation==='invoice'&&OfficeSalesApproval::enabled()){
        if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$q=$this->db->prepare('SELECT id FROM office_finance_sales_invoice WHERE id=? FOR UPDATE');$q->execute([$id]);}
        $current=$this->get($id,true);$approval=$current['_approval']??null;
        if((int)$current['revision']!==$revision||!$approval||$approval['state']!=='approved'||$approval['recipient']!==$recipient||$approval['message']!==$message)throw new \DomainException('Keur eerst deze conceptversie met dit e-mailadres en bericht goed.');
      }
      $key='office-sales-'.$operation.'-'.bin2hex(random_bytes(24)); $payload['idempotency_key']=$key;
      if (strlen(json_encode($payload,JSON_THROW_ON_ERROR))>30000) throw new \InvalidArgumentException('Verzendopdracht te groot; verkort de omschrijvingen of het bericht.');
      $q=$this->db->prepare('UPDATE office_finance_sales_invoice SET status=?,revision=revision+1,updated_at=? WHERE id=? AND revision=? AND status=?');
      $q->execute([$operation==='invoice' ? 'sending' : 'reminder_sending',gmdate('c'),$id,$revision,$row['status']]);
      if ($q->rowCount()!==1) throw new \DomainException('Verzending al gestart of factuur gewijzigd.');
      $q=$this->db->prepare("INSERT INTO office_finance_sales_delivery (invoice_id,operation,command_key,status,recipient,message,payload_json,created_at) VALUES (?,?,?,'pending',?,?,?,?)");
      $q->execute([$id,$operation,$key,$recipient,$message,json_encode($payload,JSON_THROW_ON_ERROR),gmdate('c')]); $delivery=(int)$this->db->lastInsertId();
      $this->audit($id,$operation.'_requested',['delivery_id'=>$delivery]); $this->db->commit(); return $delivery;
    } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
  }

  /** One attempt only. Uncertain results require operator reconciliation, never blind retries. */
  public function dispatch(int $deliveryId, callable $transport, string $today): void {
    $q=$this->db->prepare('SELECT * FROM office_finance_sales_delivery WHERE id=?'); $q->execute([$deliveryId]); $delivery=$q->fetch(PDO::FETCH_ASSOC);
    if (!$delivery) throw new \DomainException('Verzending niet gevonden.');
    $row=$this->get((int)$delivery['invoice_id'],true);
    $q=$this->db->prepare("UPDATE office_finance_sales_delivery SET status='processing' WHERE id=? AND status='pending'"); $q->execute([$deliveryId]);
    if ($q->rowCount()!==1) throw new \DomainException('Verzending al verwerkt of te controleren.');
    try {
      $result=$transport((string)$delivery['operation'],json_decode($delivery['payload_json'],true,64,JSON_THROW_ON_ERROR));
      if (!is_array($result) || ($result['status']??null)!=='ok') throw new \RuntimeException('Verzendbevestiging ontbreekt.');
      $provider=$delivery['operation']==='invoice' ? ($result['sales_invoice']??null) : json_decode($row['provider_json'],true,32,JSON_THROW_ON_ERROR);
      if ($delivery['operation']==='invoice' && (!is_array($provider) || !is_string($provider['id']??null) || !preg_match('/^\d+$/D',$provider['id']) || !is_string($provider['invoice_id']??null) || trim($provider['invoice_id'])==='' || empty($provider['sent_at']) || !in_array($provider['state']??'', ['open','late','reminded','pending_payment'],true))) throw new \RuntimeException('Factuurverzending niet bevestigd.');
      $sentPayload=json_decode($delivery['payload_json'],true,64,JSON_THROW_ON_ERROR);
      if ($delivery['operation']==='invoice' && ($provider['contact_id']??null)!==($sentPayload['sales_invoice']['contact_id']??null)) throw new \RuntimeException('Debiteur niet bevestigd.');
      if ($delivery['operation']==='reminder' && ($result['reminder']['invoice_id']??null)!==($provider['id']??null)) throw new \RuntimeException('Herinnering niet bevestigd.');
      $this->db->beginTransaction();
      $q=$this->db->prepare("UPDATE office_finance_sales_delivery SET status='sent',completed_at=?,result_json=? WHERE id=? AND status='processing'");
      $q->execute([gmdate('c'),json_encode($result,JSON_THROW_ON_ERROR),$deliveryId]);
      $reminder=$delivery['operation']==='reminder';
      $q=$this->db->prepare("UPDATE office_finance_sales_invoice SET status='sent',provider_json=?,invoice_number=?,reminder_count=reminder_count+?,last_reminder_date=?,updated_at=? WHERE id=? AND status=?");
      $q->execute([json_encode($provider,JSON_THROW_ON_ERROR),$provider['invoice_id'],$reminder?1:0,$reminder?$today:$row['last_reminder_date'],gmdate('c'),$row['id'],$reminder?'reminder_sending':'sending']);
      if ($q->rowCount()!==1) throw new \RuntimeException('Factuurstatus gewijzigd tijdens verzending.');
      $this->audit((int)$row['id'],$delivery['operation'].'_sent',['delivery_id'=>$deliveryId]); $this->db->commit();
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      $this->db->beginTransaction();
      $this->db->prepare("UPDATE office_finance_sales_delivery SET status='reconciliation_required' WHERE id=? AND status='processing'")->execute([$deliveryId]);
      $this->db->prepare("UPDATE office_finance_sales_invoice SET status='reconciliation_required',updated_at=? WHERE id=? AND status IN ('sending','reminder_sending')")->execute([gmdate('c'),$row['id']]);
      $this->audit((int)$row['id'],'reconciliation_required',['delivery_id'=>$deliveryId]); $this->db->commit();
      throw new \RuntimeException('Verzenduitkomst controleren; probeer niet opnieuw.',0,$e);
    }
  }

  public function history(int $id): array {
    $this->get($id);
    $q=$this->db->prepare('SELECT operation,status,recipient,message,created_at,completed_at FROM office_finance_sales_delivery WHERE invoice_id=? ORDER BY id DESC');
    $q->execute([$id]); return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  /** Operator-recorded receipt with evidence; never a bank transfer or inferred payment. */
  public function recordReceipt(int $id, int $revision, string $amount, string $date, string $reference, string $today): void {
    $this->authorize(true);
    self::date($date); self::date($today);
    $reference=trim($reference); $amount=str_replace(',','.',$amount);
    if ($date>$today || $reference==='' || strlen($reference)>128 || preg_match('/[\x00-\x1f]/',$reference) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$amount)) throw new \InvalidArgumentException('Controleer ontvangstdatum, bedrag en bankreferentie.');
    [$a,$b]=array_pad(explode('.',$amount),2,''); $cents=(int)$a*100+(int)str_pad($b,2,'0');
    $ownsTransaction=!$this->db->inTransaction();if($ownsTransaction)$this->db->beginTransaction();
    try {
      if(OfficeSalesApproval::enabled()){$q=$this->db->prepare('SELECT 1 FROM office_finance_cash_records WHERE bank_reference=? LIMIT 1');$q->execute([$reference]);if($q->fetchColumn())throw new \DomainException('Bankreferentie is al verwerkt.');}
      $row=$this->get($id,true);
      if ($row['status']!=='sent' || (int)$row['revision']!==$revision || $date<$row['invoice_date'] || $cents<=0 || $cents>(int)$row['gross_cents']-(int)$row['paid_cents']) throw new \DomainException('Ontvangst past niet bij de actuele openstaande factuur.');
      $q=$this->db->prepare("UPDATE office_finance_sales_invoice SET paid_cents=paid_cents+?,revision=revision+1,updated_at=? WHERE id=? AND revision=? AND status='sent'");
      $q->execute([$cents,gmdate('c'),$id,$revision]);
      if ($q->rowCount()!==1) throw new \DomainException('Factuur gewijzigd; controleer opnieuw.');
      $this->db->prepare('INSERT INTO office_finance_sales_receipt (invoice_id,amount_cents,receipt_date,evidence_reference,operator_id,created_at) VALUES (?,?,?,?,?,?)')->execute([$id,$cents,$date,$reference,$this->operatorId,gmdate('c')]);
      $this->audit($id,'manual_receipt_recorded',['amount_cents'=>$cents,'receipt_date'=>$date,'evidence_reference'=>$reference]);
      if($ownsTransaction)$this->db->commit();
    } catch (\Throwable $e) { if($ownsTransaction&&$this->db->inTransaction())$this->db->rollBack(); throw $e; }
  }

  public function receipts(int $id): array {
    $this->get($id);
    $q=$this->db->prepare('SELECT amount_cents,receipt_date,evidence_reference,operator_id FROM office_finance_sales_receipt WHERE invoice_id=? ORDER BY id');
    $q->execute([$id]); return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  public function requestApproval(int $id,int $revision,string $recipient,string $message): void {
    $this->authorize(true);if(!OfficeSalesApproval::enabled())throw new \DomainException('Goedkeuringsstap nog niet actief.');OfficeSalesApproval::recipient($recipient,$message);$this->db->beginTransaction();
    try{if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$q=$this->db->prepare('SELECT id FROM office_finance_sales_invoice WHERE id=? FOR UPDATE');$q->execute([$id]);}$row=$this->get($id,true);if($row['status']!=='draft'||(int)$row['revision']!==$revision||(int)$row['gross_cents']<=0)throw new \DomainException('Controleer de actuele conceptfactuur.');
      $q=$this->db->prepare('SELECT invoice_id FROM office_finance_sales_approvals WHERE invoice_id=?');$q->execute([$id]);$exists=$q->fetchColumn();$values=[$revision,'pending',$this->operatorId,null,$recipient,$message,OfficeSalesApproval::hash($row),gmdate('c'),null];
      if($exists!==false)$this->db->prepare('UPDATE office_finance_sales_approvals SET invoice_revision=?,state=?,requested_by=?,approved_by=?,recipient=?,message=?,content_hash=?,created_at=?,approved_at=? WHERE invoice_id=?')->execute([...$values,$id]);else $this->db->prepare('INSERT INTO office_finance_sales_approvals(invoice_revision,state,requested_by,approved_by,recipient,message,content_hash,created_at,approved_at,invoice_id) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([...$values,$id]);
      $this->audit($id,'approval_requested',['recipient'=>$recipient,'revision'=>$revision]);$this->db->commit();
    }catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
  public function approve(int $id,int $revision): void {
    $this->authorize(true);if(!OfficeSalesApproval::enabled())throw new \DomainException('Goedkeuringsstap nog niet actief.');$this->db->beginTransaction();try{
      if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$q=$this->db->prepare('SELECT id FROM office_finance_sales_invoice WHERE id=? FOR UPDATE');$q->execute([$id]);}$row=$this->get($id,true);$a=$row['_approval']??null;if($row['status']!=='draft'||(int)$row['revision']!==$revision||!$a||$a['state']!=='pending')throw new \DomainException('Geen actuele goedkeuringsaanvraag.');
      if(getenv('OFFICE_FINANCE_DUAL_APPROVAL_ENABLED')==='enabled'&&(string)$a['requested_by']===$this->operatorId)throw new \DomainException('Een tweede bevoegde medewerker moet deze factuur goedkeuren.');
      $this->db->prepare("UPDATE office_finance_sales_approvals SET state='approved',approved_by=?,approved_at=? WHERE invoice_id=? AND invoice_revision=? AND state='pending'")->execute([$this->operatorId,gmdate('c'),$id,$revision]);$this->audit($id,'invoice_approved',['revision'=>$revision,'recipient'=>$a['recipient']]);$this->db->commit();
    }catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }

  private function audit(int $id, string $event, array $detail): void {
    $this->db->prepare('INSERT INTO office_finance_sales_audit (invoice_id,operator_id,event,detail_json,created_at) VALUES (?,?,?,?,?)')->execute([$id,$this->operatorId,$event,json_encode($detail,JSON_THROW_ON_ERROR),gmdate('c')]);
  }
}
