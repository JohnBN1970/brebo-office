<?php
declare(strict_types=1);
namespace Brebo\Office\Finance\Center;
use PDO;
require_once __DIR__.'/Ledger.php';
final class Planning {
  private Ledger $ledger;
  public function __construct(private readonly PDO $db,private readonly string $actor){$this->ledger=new Ledger($db,$actor);}
  private function audit(string $event,?int $id,array $detail=[]): void{$this->db->prepare('INSERT INTO office_finance_center_audit(operator_id,event,entity_id,detail_json,created_at) VALUES(?,?,?,?,?)')->execute([$this->actor,$event,$id,json_encode($detail,JSON_THROW_ON_ERROR),gmdate('c')]);}
  public function plans(): array{$this->ledger->authorize();$q=$this->db->prepare('SELECT * FROM office_finance_cash_plan WHERE operator_id=? ORDER BY due_date,id');$q->execute([$this->actor]);return $q->fetchAll(PDO::FETCH_ASSOC);}
  public function add(array $input,string $today): void{
    $this->ledger->authorize(true);$project=($input['project_id']??'')===''?null:Amounts::id($input['project_id']);$this->ledger->project($project);$direction=$input['direction']??'';$bucket=$input['bucket']??'';$amount=Amounts::cents($input['amount']??null);$due=Amounts::date($input['due_date']??null);
    if(!in_array($direction,['in','out'],true)||!in_array($bucket,['regular','g'],true)||$amount<=0||$due<$today)throw new \InvalidArgumentException('Controleer richting, rekeningsoort, bedrag en datum.');
    $description=Amounts::text($input['description']??null);$reference=Amounts::text($input['reference']??null);$this->db->beginTransaction();try{$this->db->prepare('INSERT INTO office_finance_cash_plan(operator_id,office_project_id,direction,account_bucket,amount_cents,due_date,description,evidence_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$this->actor,$project,$direction,$bucket,$amount,$due,$description,$reference,gmdate('c')]);$this->audit('cash_plan_created',(int)$this->db->lastInsertId());$this->db->commit();}catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
  public function cancel(int $id,string $reason): void{$this->ledger->authorize(true);$reason=Amounts::text($reason);$this->db->beginTransaction();try{$q=$this->db->prepare("UPDATE office_finance_cash_plan SET status='cancelled' WHERE id=? AND operator_id=? AND status='planned'");$q->execute([$id,$this->actor]);if($q->rowCount()!==1)throw new \DomainException('Planning niet beschikbaar.');$this->audit('cash_plan_cancelled',$id,['reason'=>$reason]);$this->db->commit();}catch(\Throwable $e){$this->db->rollBack();throw $e;}}
  public function settle(int $id,string $amount,string $date,string $reference,string $today): void {
    $this->ledger->authorize(true);Amounts::date($date);if($date>$today)throw new \InvalidArgumentException('Een betaling kan niet in de toekomst liggen.');$cents=Amounts::cents($amount);$reference=Amounts::text($reference,160);if($cents<=0)throw new \InvalidArgumentException('Bedrag moet positief zijn.');
    $this->db->beginTransaction();try{
      $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$q=$this->db->prepare('SELECT * FROM office_finance_cash_plan WHERE id=? AND operator_id=?'.$lock);$q->execute([$id,$this->actor]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p||$p['status']!=='planned'||$cents>(int)$p['amount_cents']-(int)$p['paid_cents'])throw new \DomainException('Betaling past niet bij de actuele planning.');$this->ledger->project($p['office_project_id']===null?null:(int)$p['office_project_id']);
      $q=$this->db->prepare('SELECT 1 FROM office_finance_sales_receipt WHERE evidence_reference=? LIMIT 1');$q->execute([$reference]);if($q->fetchColumn())throw new \DomainException('Bankreferentie is al gebruikt.');
      $this->db->prepare('INSERT INTO office_finance_cash_records(entity_type,entity_id,bank_reference,account_bucket,amount_cents,payment_date,operator_id,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute(['plan_'.$p['direction'],$id,$reference,$p['account_bucket'],$cents,$date,$this->actor,gmdate('c')]);
      $paid=(int)$p['paid_cents']+$cents;$this->db->prepare('UPDATE office_finance_cash_plan SET paid_cents=?,status=? WHERE id=?')->execute([$paid,$paid===(int)$p['amount_cents']?'settled':'planned',$id]);$this->audit('cash_plan_settled',$id,['reference'=>$reference]);$this->db->commit();
    }catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
  public function opening(string $date,string $reference,array $accounts,string $today): void{
    $this->ledger->authorize(true);if(!$this->ledger->bankAccess())throw new \DomainException('Bankinzage is vereist voor de financiële beginsaldi.');Amounts::date($date);if($date>$today||!$accounts||count($accounts)>50)throw new \InvalidArgumentException('Controleer datum en rekeningen.');$reference=Amounts::text($reference);$clean=[];
    foreach($accounts as $a){$ref=Amounts::text($a['reference']??null,160);if(isset($clean[$ref])||!in_array($a['bucket']??'',['regular','g'],true))throw new \InvalidArgumentException('Rekening dubbel of rekeningsoort ontbreekt.');$clean[$ref]=['bucket'=>$a['bucket'],'cents'=>Amounts::cents($a['balance']??null,true)];}
    $this->db->beginTransaction();try{$this->db->prepare('INSERT INTO office_finance_balance_sets(operator_id,balance_date,evidence_reference,created_at) VALUES(?,?,?,?)')->execute([$this->actor,$date,$reference,gmdate('c')]);$id=(int)$this->db->lastInsertId();$q=$this->db->prepare('INSERT INTO office_finance_balance_accounts(balance_set_id,account_reference,account_bucket,balance_cents) VALUES(?,?,?,?)');foreach($clean as $key=>$a)$q->execute([$id,$key,$a['bucket'],$a['cents']]);$this->audit('opening_balance_confirmed',$id,['date'=>$date,'account_count'=>count($clean)]);$this->db->commit();}catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
  public function latestOpening(): ?array{
    $this->ledger->authorize();if(!$this->ledger->bankAccess())return null;$q=$this->db->prepare('SELECT * FROM office_finance_balance_sets WHERE operator_id=? ORDER BY balance_date DESC,id DESC LIMIT 1');$q->execute([$this->actor]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)return null;$q=$this->db->prepare('SELECT * FROM office_finance_balance_accounts WHERE balance_set_id=? ORDER BY id');$q->execute([$row['id']]);$row['accounts']=$q->fetchAll(PDO::FETCH_ASSOC);return $row;
  }
}
