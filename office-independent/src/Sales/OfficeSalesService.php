<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
use PDO;

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
    $q->execute([$this->operatorId]); return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  public function customers(): array {
    $this->authorize();
    return $this->db->query("SELECT id,display_name FROM office_crm_relations WHERE status='active' ORDER BY display_name")->fetchAll(PDO::FETCH_ASSOC);
  }

  public function list(): array {
    $this->authorize();
    $q=$this->db->prepare("SELECT i.* FROM office_finance_sales_invoice i WHERE (i.office_project_id IS NULL AND i.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_memberships m WHERE m.project_id=i.office_project_id AND m.operator_id=? AND m.permission IN ('read','manage')) ORDER BY i.id DESC LIMIT 200");
    $q->execute([$this->operatorId,$this->operatorId]); return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get(int $id, bool $write = false): array {
    $this->authorize($write);
    $q=$this->db->prepare('SELECT * FROM office_finance_sales_invoice WHERE id=?'); $q->execute([$id]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row || ($row['office_project_id']===null && (string)$row['operator_id']!==$this->operatorId)) throw new \DomainException('Factuur niet beschikbaar.');
    $this->project($row['office_project_id']===null ? null : (int)$row['office_project_id']);
    return $row;
  }

  public static function totals(array $lines): array {
    if ($lines===[] || count($lines)>100) throw new \InvalidArgumentException('Vul 1 tot 100 factuurregels in.');
    $net=0; $vat=0; $clean=[];
    foreach ($lines as $line) {
      $description=trim((string)($line['description']??''));
      $quantity=str_replace(',','.',(string)($line['quantity']??''));
      $price=str_replace(',','.',(string)($line['price']??''));
      $rate=(string)($line['vat']??'');
      if ($description==='' || strlen($description)>1000 || preg_match('/[\x00-\x1f]/',$description)
          || !preg_match('/^\d{1,6}(?:\.\d{1,3})?$/D',$quantity)
          || !preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$price) || !in_array($rate,['0','9','21'],true)) throw new \InvalidArgumentException('Ongeldige factuurregel.');
      [$a,$b]=array_pad(explode('.',$quantity),2,''); $units=(int)$a*1000+(int)str_pad($b,3,'0');
      [$a,$b]=array_pad(explode('.',$price),2,''); $cents=(int)$a*100+(int)str_pad($b,2,'0');
      if ($units<=0) throw new \InvalidArgumentException('Aantal moet positief zijn.');
      $amount=intdiv($units*$cents+500,1000); $tax=intdiv($amount*(int)$rate+50,100);
      $net+=$amount; $vat+=$tax;
      if ($net+$vat>999999999999) throw new \InvalidArgumentException('Factuurtotaal te groot.');
      $clean[]=['description'=>$description,'quantity'=>$quantity,'price'=>$price,'vat'=>$rate,'net_cents'=>$amount,'vat_cents'=>$tax];
    }
    return ['lines'=>$clean,'net_cents'=>$net,'vat_cents'=>$vat,'gross_cents'=>$net+$vat];
  }

  private static function date(string $date): string {
    $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$d || $d->format('Y-m-d')!==$date) throw new \InvalidArgumentException('Ongeldige datum.');
    return $date;
  }

  public function save(array $input, ?int $id = null, int $revision = 0): int {
    $this->authorize(true);
    $project=($input['office_project_id']??'')==='' ? null : filter_var($input['office_project_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($project===false) throw new \InvalidArgumentException('Ongeldig project.');
    $this->project($project);
    $customerId=filter_var($input['relation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($customerId===false) throw new \InvalidArgumentException('Kies een relatie.');
    $q=$this->db->prepare("SELECT id,display_name,legal_name,email,street,postal_code,city,country_code,vat_number FROM office_crm_relations WHERE id=? AND status='active'");
    $q->execute([$customerId]); $customer=$q->fetch(PDO::FETCH_ASSOC);
    if (!$customer) throw new \InvalidArgumentException('Relatie niet beschikbaar.');
    $date=self::date((string)($input['invoice_date']??'')); $due=self::date((string)($input['due_date']??''));
    if ($due<$date) throw new \InvalidArgumentException('Vervaldatum ligt voor factuurdatum.');
    $reference=trim((string)($input['reference']??''));
    if (strlen($reference)>255 || preg_match('/[\x00-\x1f]/',$reference)) throw new \InvalidArgumentException('Ongeldige referentie.');
    $totals=self::totals($input['lines']??[]); $now=gmdate('c');
    $this->db->beginTransaction();
    try {
      $values=[$project,$customerId,json_encode($customer,JSON_THROW_ON_ERROR),$date,$due,$reference,json_encode($totals['lines'],JSON_THROW_ON_ERROR),$totals['net_cents'],$totals['vat_cents'],$totals['gross_cents'],$now];
      if ($id===null) {
        $q=$this->db->prepare('INSERT INTO office_finance_sales_invoice (office_project_id,relation_id,customer_json,invoice_date,due_date,reference,lines_json,net_cents,vat_cents,gross_cents,updated_at,operator_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([...$values,$this->operatorId,$now]); $id=(int)$this->db->lastInsertId();
      } else {
        $row=$this->get($id,true);
        if ($row['status']!=='draft') throw new \DomainException('Een vrijgegeven factuur is niet wijzigbaar.');
        $q=$this->db->prepare("UPDATE office_finance_sales_invoice SET office_project_id=?,relation_id=?,customer_json=?,invoice_date=?,due_date=?,reference=?,lines_json=?,net_cents=?,vat_cents=?,gross_cents=?,updated_at=?,revision=revision+1 WHERE id=? AND revision=? AND status='draft'");
        $q->execute([...$values,$id,$revision]);
        if ($q->rowCount()!==1) throw new \DomainException('Het concept is inmiddels gewijzigd; laad opnieuw.');
      }
      $this->audit($id,'draft_saved',[]); $this->db->commit(); return $id;
    } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
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
        $payload=['sales_invoice'=>['contact_id'=>(string)$ids[0],'reference'=>'BREBO-'.$id.($row['reference']!=='' ? ' · '.$row['reference'] : ''),'invoice_date'=>$row['invoice_date'],'due_date'=>$row['due_date'],'currency'=>'EUR','prices_are_incl_tax'=>false,'details_attributes'=>$details], 'sending'=>['delivery_method'=>'Email','email_address'=>$recipient,'email_message'=>$message]];
      } else {
        $provider=json_decode($row['provider_json']??'null',true,32,JSON_THROW_ON_ERROR);
        foreach (['id','contact_id','workflow_id','document_style_id','identity_id'] as $field) if (!is_string($provider[$field]??null) || !preg_match('/^\d+$/D',$provider[$field])) throw new \DomainException('Verzendbewijs voor deze factuur is onvolledig.');
        $payload=['reminder'=>array_intersect_key($provider,array_flip(['id','contact_id','workflow_id','document_style_id','identity_id']))+['email_address'=>$recipient,'reminder_text'=>$message]];
      }
      $key='office-sales-'.$operation.'-'.bin2hex(random_bytes(24)); $payload['idempotency_key']=$key;
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

  private function audit(int $id, string $event, array $detail): void {
    $this->db->prepare('INSERT INTO office_finance_sales_audit (invoice_id,operator_id,event,detail_json,created_at) VALUES (?,?,?,?,?)')->execute([$id,$this->operatorId,$event,json_encode($detail,JSON_THROW_ON_ERROR),gmdate('c')]);
  }
}
