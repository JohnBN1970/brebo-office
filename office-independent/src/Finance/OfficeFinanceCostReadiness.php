<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;
use PDO;
/** Native invoice evidence only. Drafts, imported headers and raw hours are not booked costs. */
final class OfficeFinanceCostReadiness {
  private static function cents(string $value): int {
    if(!preg_match('/^-?\d{1,10}(?:\.\d{1,4})?$/D',$value))throw new \UnexpectedValueException('Invalid native cost amount');
    $negative=str_starts_with($value,'-');[$a,$b]=array_pad(explode('.',ltrim($value,'-')),2,'');$scaled=(int)$a*10000+(int)str_pad($b,4,'0');$cents=intdiv($scaled+50,100);return $negative?-$cents:$cents;
  }
  public static function read(PDO $db,string $operatorId,?int $projectId,string $today): array {
    if(!(new OfficeFinanceAccess($db,$operatorId))->allows())throw new \DomainException('Finance access required');
    if($projectId!==null){$q=$db->prepare("SELECT 1 FROM office_project_memberships WHERE project_id=? AND operator_id=? AND permission IN ('read','manage') LIMIT 1");$q->execute([$projectId,$operatorId]);if(!$q->fetchColumn())throw new \DomainException('Project unavailable');}
    $result=['available'=>false,'draft_net_cents'=>0,'booked_net_cents'=>0,'draft_count'=>0,'booked_count'=>0,'unusable_count'=>0,'labour_valued'=>false,'overhead_booked'=>false];
    try{
      $sql="SELECT i.id,i.invoice_date,i.currency,i.subtotal,i.status FROM office_finance_invoices i WHERE i.invoice_date>=? AND i.invoice_date<=?
        AND EXISTS (SELECT 1 FROM office_finance_invoice_lines l WHERE l.invoice_id=i.id)
        AND NOT EXISTS (SELECT 1 FROM office_finance_invoice_lines l WHERE l.invoice_id=i.id AND (l.office_project_id IS NULL OR NOT EXISTS (SELECT 1 FROM office_project_memberships m WHERE m.project_id=l.office_project_id AND m.operator_id=? AND m.permission IN ('read','manage'))))";
      $args=[substr($today,0,4).'-01-01',$today,$operatorId];if($projectId!==null){$sql.=' AND EXISTS (SELECT 1 FROM office_finance_invoice_lines f WHERE f.invoice_id=i.id AND f.office_project_id=?)';$args[]=$projectId;}
      $q=$db->prepare($sql);$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
      $lines=$db->prepare('SELECT net_amount,office_project_id FROM office_finance_invoice_lines WHERE invoice_id=?');
      foreach($rows as $i){
        if($i['status']==='cancelled')continue;
        try{
          if($i['currency']!=='EUR'||!in_array($i['status'],['draft','booked'],true))throw new \UnexpectedValueException('Unbooked status or currency');
          $lines->execute([$i['id']]);$all=0;$scope=0;foreach($lines->fetchAll(PDO::FETCH_ASSOC) as $line){$net=self::cents((string)$line['net_amount']);$all+=$net;if($projectId===null||(int)$line['office_project_id']===$projectId)$scope+=$net;}
          if($all!==self::cents((string)$i['subtotal']))throw new \UnexpectedValueException('Header and lines disagree');
          $kind=$i['status']==='booked'?'booked':'draft';$result[$kind.'_net_cents']+=$scope;$result[$kind.'_count']++;
        }catch(\UnexpectedValueException){$result['unusable_count']++;}
      }
      $result['available']=true;
    }catch(\PDOException){/* An absent native cost source is not a zero-cost business. */}
    return $result;
  }
  private static function money(int $cents): string{$sign=$cents<0?'-':'';$cents=abs($cents);return $sign.'€ '.number_format(intdiv($cents,100),0,',','.').','.sprintf('%02d',$cents%100);}
  public static function cards(array $cost): string {
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$ok=($cost['available']??false)===true;
    $h='';foreach(['Geboekte inkoopkosten YTD'=>'booked','Inkoopconcepten YTD'=>'draft'] as $label=>$key){$h.='<div class="card"><span>'.$label.'</span><strong>'.($ok?$e(self::money((int)$cost[$key.'_net_cents'])):'Niet beschikbaar').'</strong><small>'.($key==='draft'?'Nog te beoordelen; niet meegenomen als geboekte kosten.':'Eigen Office-facturen met status geboekt; exclusief btw.').'</small></div>';}
    if($ok&&(int)$cost['unusable_count']>0)$h.='<div class="card"><span>Kostencontrole</span><strong>'.(int)$cost['unusable_count'].' niet meegenomen</strong><small>Controleer status, valuta en aansluiting tussen factuurkop en regels.</small></div>';
    if(($cost['complete']??false)===true)return $h.'<div class="card"><span>Kostenbasis voor resultaat</span><strong>Bevestigd</strong><small>Gebaseerd op de gecontroleerde boekingen en gewaardeerde uren.</small></div>';
    return $h.'<div class="card"><span>Kostenbasis voor resultaat</span><strong>Onvolledig</strong><small>Gewaardeerde arbeidskosten en geboekte bedrijfskosten ontbreken nog. Inkoopconcepten en oude factuurkoppen tellen niet als geboekte kosten.</small></div>';
  }
  public static function cashCards(array $sales): string {
    $h='';$ok=($sales['available']??false)===true;
    foreach([30,60,90] as $days){$value=$ok&&isset($sales['receipts_by_days'][$days])?self::money((int)$sales['receipts_by_days'][$days]):'Niet beschikbaar';$h.='<div class="card"><span>'.$days.' dagen</span><strong>'.htmlspecialchars($value,ENT_QUOTES,'UTF-8').'</strong><small>Openstaande verkoopfacturen met vervaldatum tot deze horizon, inclusief btw en achterstanden. Cumulatief; geen garantie van ontvangst.</small></div>';}
    if(!empty($sales['center']['forecast']))return $h.'<p class="muted">Dit is de ontvangstenplanning. De liquiditeitsprognose hieronder bevat ook uitgaande betalingen en bevestigde beginsaldi.</p>';
    return $h.'<p class="muted">Dit is een ontvangstenplanning. Voor een volledig liquiditeitssaldo ontbreken nog een gedateerd beginsaldo per gewone/G-rekening en de uitgaande betaalplanning. Conceptfacturen en ongedateerde contractbedragen zijn niet meegerekend.</p>';
  }
}
