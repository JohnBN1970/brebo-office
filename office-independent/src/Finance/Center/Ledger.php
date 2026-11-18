<?php
declare(strict_types=1);
namespace Brebo\Office\Finance\Center;
use PDO;
use Brebo\Office\Finance\OfficeFinanceAccess;
require_once __DIR__.'/Amounts.php';
final class Ledger {
  public function __construct(private readonly PDO $db,public readonly string $actor){}
  public function authorize(bool $write=false): void{$a=new OfficeFinanceAccess($this->db,$this->actor);if(!$a->allows()||($write&&!$a->allows('draft_edit')))throw new \DomainException('Geen Finance-recht voor deze actie.');}
  public function bankAccess(): bool{return (new OfficeFinanceAccess($this->db,$this->actor))->allowsBankRead();}
  public function project(?int $id): void{if($id===null)return;$q=$this->db->prepare("SELECT 1 FROM office_project_memberships WHERE operator_id=? AND project_id=? AND permission IN ('read','manage')");$q->execute([$this->actor,$id]);if(!$q->fetchColumn())throw new \DomainException('Project niet toegankelijk.');}
  public function projects(): array{$this->authorize();return (new OfficeFinanceAccess($this->db,$this->actor))->draftProjects();}
  private function lock(): string{return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
  private function audit(string $event,?int $id,array $data=[]): void{$this->db->prepare('INSERT INTO office_finance_center_audit(operator_id,event,entity_id,detail_json,created_at) VALUES(?,?,?,?,?)')->execute([$this->actor,$event,$id,json_encode($data,JSON_THROW_ON_ERROR),gmdate('c')]);}
  public function scope(string $alias='l'): string{return "(($alias.office_project_id IS NULL AND p.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_memberships m WHERE m.project_id=$alias.office_project_id AND m.operator_id=? AND m.permission IN ('read','manage')))";}
  public function postings(): array{
    $this->authorize();$scope=$this->scope();$q=$this->db->prepare("SELECT p.* FROM office_finance_postings p WHERE EXISTS (SELECT 1 FROM office_finance_posting_lines l WHERE l.posting_id=p.id) AND NOT EXISTS (SELECT 1 FROM office_finance_posting_lines l WHERE l.posting_id=p.id AND NOT $scope) ORDER BY p.booking_date DESC,p.id DESC");$q->execute([$this->actor,$this->actor]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row)$row['lines']=$this->lines((int)$row['id']);return $rows;
  }
  private function lines(int $id): array{$q=$this->db->prepare('SELECT * FROM office_finance_posting_lines WHERE posting_id=? ORDER BY id');$q->execute([$id]);return $q->fetchAll(PDO::FETCH_ASSOC);}
  public function get(int $id,bool $lock=false): array{
    $this->authorize();$q=$this->db->prepare('SELECT * FROM office_finance_postings WHERE id=?'.($lock?$this->lock():''));$q->execute([$id]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p)throw new \DomainException('Boeking niet gevonden.');$p['lines']=$this->lines($id);if(!$p['lines'])throw new \DomainException('Boeking zonder regels.');foreach($p['lines'] as $line){if($line['office_project_id']===null){if((string)$p['operator_id']!==$this->actor)throw new \DomainException('Geen toegang tot deze boeking.');}else $this->project((int)$line['office_project_id']);}return $p;
  }
  /** Existing native drafts are locked, reviewed and frozen before becoming a cost posting. */
  public function purchase(int $id,bool $lock=false): array{
    $this->authorize();$q=$this->db->prepare('SELECT * FROM office_finance_invoices WHERE id=?'.($lock?$this->lock():''));$q->execute([$id]);$i=$q->fetch(PDO::FETCH_ASSOC);if(!$i||$i['status']!=='draft')throw new \DomainException('Alleen een eigen inkoopconcept kan worden geboekt.');
    $q=$this->db->prepare('SELECT * FROM office_finance_invoice_lines WHERE invoice_id=? ORDER BY line_number,id'.($lock?$this->lock():''));$q->execute([$id]);$i['lines']=$q->fetchAll(PDO::FETCH_ASSOC);if(!$i['lines'])throw new \DomainException('Factuurregels ontbreken.');foreach($i['lines'] as $l){if($l['office_project_id']===null)throw new \DomainException('Koppel alle inkoopregels eerst aan een project.');$this->project((int)$l['office_project_id']);}return $i;
  }
  public function purchaseChoices(): array{
    $this->authorize();$q=$this->db->prepare("SELECT i.id,i.supplier_name,i.invoice_number,i.invoice_date,i.total FROM office_finance_invoices i WHERE i.status='draft' AND EXISTS (SELECT 1 FROM office_finance_invoice_lines l WHERE l.invoice_id=i.id) AND NOT EXISTS (SELECT 1 FROM office_finance_invoice_lines l WHERE l.invoice_id=i.id AND (l.office_project_id IS NULL OR NOT EXISTS (SELECT 1 FROM office_project_memberships m WHERE m.project_id=l.office_project_id AND m.operator_id=? AND m.permission IN ('read','manage')))) ORDER BY i.id DESC");$q->execute([$this->actor]);return $q->fetchAll(PDO::FETCH_ASSOC);
  }
  private function insert(array $p,array $lines): int{
    $net=0;$vat=0;foreach($lines as $l){$net+=$l['net_cents'];$vat+=$l['vat_cents'];}if($net<0||$vat<0||$net+$vat<=0)throw new \DomainException('Boeking moet positief zijn; gebruik tegenboeken voor een correctie.');
    $q=$this->db->prepare('INSERT INTO office_finance_postings(source_key,document_key,source_kind,source_id,document_ref,booking_date,due_date,operator_id,net_cents,vat_cents,gross_cents,g_cents,cash_due,source_hash,snapshot_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute([$p['key'],$p['document_key']??null,$p['kind'],$p['source_id']??null,$p['reference'],$p['date'],$p['due']??null,$this->actor,$net,$vat,$net+$vat,$p['g_cents']??0,$p['cash_due']??1,Amounts::hash($p['snapshot']),json_encode($p['snapshot'],JSON_THROW_ON_ERROR),gmdate('c')]);$id=(int)$this->db->lastInsertId();
    $q=$this->db->prepare('INSERT INTO office_finance_posting_lines(posting_id,office_project_id,category,net_cents,vat_cents,gross_cents,description) VALUES(?,?,?,?,?,?,?)');foreach($lines as $l)$q->execute([$id,$l['project'],$l['category'],$l['net_cents'],$l['vat_cents'],$l['net_cents']+$l['vat_cents'],$l['description']]);$this->audit('cost_booked',$id,['source_kind'=>$p['kind'],'source_id'=>$p['source_id']??null]);return $id;
  }
  public function bookPurchase(int $id,string $expectedHash,string $today,string $gAmount='0'): int{
    $this->authorize(true);$today=Amounts::date($today);$this->db->beginTransaction();try{
      $i=$this->purchase($id,true);if(!hash_equals(Amounts::hash($i),$expectedHash))throw new \DomainException('Concept gewijzigd; controleer opnieuw.');if($i['currency']!=='EUR')throw new \DomainException('Alleen EUR-facturen worden ondersteund.');
      $date=Amounts::date($i['invoice_date']);$due=Amounts::date($i['due_date']);if($date>$today||$due<$date)throw new \DomainException('Controleer factuur- en vervaldatum.');Amounts::text($i['supplier_name']);Amounts::text($i['invoice_number']);
      $lines=[];$net=0;$vat=0;foreach($i['lines'] as $l){$n=Amounts::cents($l['net_amount']);$v=Amounts::cents($l['vat_amount']);$net+=$n;$vat+=$v;$lines[]=['project'=>(int)$l['office_project_id'],'category'=>'direct','net_cents'=>$n,'vat_cents'=>$v,'description'=>Amounts::text($l['description'],1000)];}
      $g=Amounts::cents($gAmount);if($net!==Amounts::cents($i['subtotal'])||$vat!==Amounts::cents($i['vat_total'])||$net+$vat!==Amounts::cents($i['total'])||$g>$net+$vat)throw new \DomainException('Factuurkop, regels en betaalverdeling sluiten niet aan.');
      $dup=$this->db->prepare("SELECT 1 FROM office_finance_postings WHERE source_kind='purchase' AND document_ref=? AND status='booked' LIMIT 1");$reference=Amounts::text($i['supplier_name'].' / '.$i['invoice_number']);$dup->execute([$reference]);if($dup->fetchColumn())throw new \DomainException('Deze leveranciersfactuur is al geboekt.');
      $posting=$this->insert(['key'=>'purchase:'.$id,'kind'=>'purchase','document_key'=>Amounts::hash([strtolower((string)($i['supplier_id']?:$i['supplier_name'])),strtolower((string)$i['invoice_number'])]),'source_id'=>$id,'reference'=>$reference,'date'=>$date,'due'=>$due,'g_cents'=>$g,'snapshot'=>$i],$lines);
      $q=$this->db->prepare("UPDATE office_finance_invoices SET status='booked' WHERE id=? AND status='draft'");$q->execute([$id]);if($q->rowCount()!==1)throw new \DomainException('Concept gewijzigd.');$this->db->prepare('INSERT INTO office_finance_invoice_events(invoice_id,event_type,operator_id,event_data) VALUES(?,?,?,?)')->execute([$id,'finance_booked',$this->actor,json_encode(['posting_id'=>$posting,'source_hash'=>$expectedHash],JSON_THROW_ON_ERROR)]);$this->db->commit();return $posting;
    }catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
  public function manual(array $input,string $today): int{
    $this->authorize(true);$date=Amounts::date($input['date']??null);if($date>$today)throw new \DomainException('Een toekomstige kostenpost hoort in de betaalplanning.');$category=$input['category']??'';if(!in_array($category,['direct','labor','overhead'],true))throw new \InvalidArgumentException('Kies de kostensoort.');
    $project=($input['project_id']??'')===''?null:Amounts::id($input['project_id']);$this->project($project);if($category==='direct'&&$project===null)throw new \InvalidArgumentException('Directe kosten vereisen een project.');
    $net=Amounts::cents($input['net']??null);$vat=Amounts::cents($input['vat']??'0');$cash=($input['cash_due']??'')==='yes';$due=$cash?Amounts::date($input['due_date']??null):null;if($due!==null&&$due<$date)throw new \InvalidArgumentException('Vervaldatum ligt voor de boekingsdatum.');$reference=Amounts::text($input['reference']??null);$description=Amounts::text($input['description']??null,1000);$key=Amounts::text($input['command_key']??null,128);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new \InvalidArgumentException('Ongeldige opdracht.');
    $this->db->beginTransaction();try{$id=$this->insert(['key'=>'manual:'.$key,'kind'=>'manual','reference'=>$reference,'date'=>$date,'due'=>$due,'cash_due'=>$cash?1:0,'snapshot'=>['evidence'=>$reference,'description'=>$description]], [['project'=>$project,'category'=>$category,'net_cents'=>$net,'vat_cents'=>$vat,'description'=>$description]]);$this->db->commit();return $id;}catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
  public function rate(int $person,string $from,string $value,string $reference,string $today): void{
    $this->authorize(true);Amounts::date($from);if($from>$today)throw new \InvalidArgumentException('Voer een ingegane kostprijs in.');$cents=Amounts::cents($value);if($cents<=0||$cents>1000000)throw new \InvalidArgumentException('Controleer de kostprijs per uur.');
    $q=$this->db->prepare("SELECT 1 FROM office_inzet_people p WHERE p.id=? AND EXISTS (SELECT 1 FROM office_inzet_approved_hours h JOIN office_project_memberships m ON m.project_id=h.project_id WHERE h.person_id=p.id AND m.operator_id=? AND m.permission IN ('read','manage'))");$q->execute([$person,$this->actor]);if(!$q->fetchColumn())throw new \DomainException('Medewerker heeft geen uren binnen jouw toegankelijke projecten.');
    $this->db->beginTransaction();try{$this->db->prepare('INSERT INTO office_finance_cost_rates(person_id,effective_from,cents_per_hour,evidence_reference,operator_id,created_at) VALUES(?,?,?,?,?,?)')->execute([$person,$from,$cents,Amounts::text($reference),$this->actor,gmdate('c')]);$this->audit('cost_rate_recorded',null,['person_id'=>$person,'effective_from'=>$from]);$this->db->commit();}catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
  public function hoursChoices(): array{
    $this->authorize();
    // Approved hours are the canonical OnSite source. Finance does not post a second labour cost.
    $sql="SELECT h.id,h.person_id,h.project_id,h.approved_seconds AS reviewed_seconds,
      1 AS revision,h.approved_at,CONCAT(h.work_date,'T12:00:00Z') AS worked_at
      FROM office_inzet_approved_hours h
      WHERE h.approved_seconds>0 AND h.approved_seconds<=86400
      AND EXISTS (SELECT 1 FROM office_project_memberships m
        WHERE m.project_id=h.project_id AND m.operator_id=? AND m.permission IN ('read','manage'))
      AND NOT EXISTS (SELECT 1 FROM office_inzet_actual_labour_costs c WHERE c.approved_hours_id=h.id)
      ORDER BY h.id DESC";
    $q=$this->db->prepare($sql);$q->execute([$this->actor]);return $q->fetchAll(PDO::FETCH_ASSOC);
  }
  public function valueHours(int $id,int $revision,string $today): int{
    $this->authorize(true);
    // Fail closed until the Finance UI is wired to the canonical approved-labour posting service.
    // The former implementation created a duplicate financial posting from a nonexistent review table.
    throw new \\DomainException('Urenwaardering via FinanceCenter is uitgeschakeld. Gebruik de gecontroleerde Inzet-arbeidskostenketen.');
  }
  public function payment(string $type,int $id,int $revision,string $amount,string $date,string $reference,string $bucket,string $today): void{
    $this->authorize(true);Amounts::date($date);if($date>$today||!in_array($bucket,['regular','g'],true)||!in_array($type,['expense','sale'],true))throw new \InvalidArgumentException('Controleer ontvangstdatum en rekeningsoort.');$cents=Amounts::cents($amount);$reference=Amounts::text($reference,160);if($cents<=0)throw new \InvalidArgumentException('Bedrag moet positief zijn.');
    $this->db->beginTransaction();try{
      if($type==='expense'){$p=$this->get($id,true);if($p['status']!=='booked'||(int)$p['revision']!==$revision||(int)$p['cash_due']!==1||$cents>(int)$p['gross_cents']-(int)$p['paid_cents']||$date<$p['booking_date'])throw new \DomainException('Betaling past niet bij de actuele boeking.');
        $q=$this->db->prepare('SELECT COALESCE(SUM(amount_cents),0) FROM office_finance_cash_records WHERE entity_type=? AND entity_id=? AND account_bucket=?');$q->execute([$type,$id,$bucket]);$already=(int)$q->fetchColumn();$limit=$bucket==='g'?(int)$p['g_cents']:(int)$p['gross_cents']-(int)$p['g_cents'];if($cents>$limit-$already)throw new \DomainException('Betaling past niet bij de gewone/G-rekeningverdeling.');
        $this->db->prepare('UPDATE office_finance_postings SET paid_cents=paid_cents+?,revision=revision+1 WHERE id=?')->execute([$cents,$id]);
      }else{
        $sales=$this->sales();$p=$sales->get($id,true);$sales->recordReceipt($id,$revision,$amount,$date,$reference,$today);
        $g=(int)$p['g_cents'];$q=$this->db->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM office_finance_cash_records WHERE entity_type='sale' AND entity_id=? AND account_bucket=?");$q->execute([$id,$bucket]);$limit=$bucket==='g'?$g:(int)$p['gross_cents']-$g;if($cents>$limit-(int)$q->fetchColumn())throw new \DomainException('Ontvangst past niet bij de gewone/G-rekeningverdeling.');
      }
      $q=$this->db->prepare('SELECT 1 FROM office_finance_sales_receipt WHERE evidence_reference=? AND invoice_id<>? LIMIT 1');$q->execute([$reference,$type==='sale'?$id:0]);if($q->fetchColumn())throw new \DomainException('Deze bankreferentie is al gebruikt voor een ontvangst.');
      $this->db->prepare('INSERT INTO office_finance_cash_records(entity_type,entity_id,bank_reference,account_bucket,amount_cents,payment_date,operator_id,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$type,$id,$reference,$bucket,$cents,$date,$this->actor,gmdate('c')]);$this->audit('bank_payment_recorded',$id,['entity_type'=>$type,'reference'=>$reference]);$this->db->commit();
    }catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
  public function sales(): \Brebo\Office\Sales\OfficeSalesService {return new \Brebo\Office\Sales\OfficeSalesService($this->db,$this->actor);}
  public function reverse(int $id,int $revision,string $reason): void{
    $this->authorize(true);$reason=Amounts::text($reason);$this->db->beginTransaction();try{$p=$this->get($id,true);if($p['status']!=='booked'||(int)$p['revision']!==$revision||(int)$p['paid_cents']!==0)throw new \DomainException('Alleen een onbetaalde actuele boeking kan worden tegengeboekt.');
      $this->db->prepare("UPDATE office_finance_postings SET status='reversed',document_key=NULL,source_key=?,revision=revision+1 WHERE id=?")->execute([$p['source_key'].':reversed:'.$id,$id]);if($p['source_kind']==='purchase'){$this->db->prepare("UPDATE office_finance_invoices SET status='draft' WHERE id=? AND status='booked'")->execute([$p['source_id']]);$this->db->prepare('INSERT INTO office_finance_invoice_events(invoice_id,event_type,operator_id,event_data) VALUES(?,?,?,?)')->execute([$p['source_id'],'finance_reversed',$this->actor,json_encode(['posting_id'=>$id,'reason'=>$reason],JSON_THROW_ON_ERROR)]);}$this->audit('cost_reversed',$id,['reason'=>$reason]);$this->db->commit();}catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
}
