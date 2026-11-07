<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;
use PDO;
/** Office-native sales only. No provider calls, journal writes or bank balance inference. */
final class OfficeFinanceSalesOverview {
  private static function scope(string $alias): string {
    return "(($alias.office_project_id IS NULL AND $alias.operator_id=?) OR EXISTS (SELECT 1 FROM office_project_memberships m WHERE m.project_id=$alias.office_project_id AND m.operator_id=? AND m.permission IN ('read','manage')))";
  }
  public static function read(PDO $db,string $operatorId,?int $projectId,string $today): array {
    if(!(new OfficeFinanceAccess($db,$operatorId))->allows())throw new \DomainException('Finance access required');
    $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$today,new \DateTimeZone('UTC'));
    if(!$date||$date->format('Y-m-d')!==$today)throw new \InvalidArgumentException('Invalid reporting date');
    if($projectId!==null){$q=$db->prepare("SELECT 1 FROM office_project_memberships WHERE project_id=? AND operator_id=? AND permission IN ('read','manage') LIMIT 1");$q->execute([$projectId,$operatorId]);if(!$q->fetchColumn())throw new \DomainException('Project unavailable');}
    $where=self::scope('i');$args=[$operatorId,$operatorId];if($projectId!==null){$where.=' AND i.office_project_id=?';$args[]=$projectId;}
    // Aggregate every accessible record, rather than the 200-item sales list.
    $issued="i.invoice_number IS NOT NULL AND i.invoice_number<>'' AND i.status IN ('sent','reminder_sending','reconciliation_required')";
    $q=$db->prepare("SELECT i.invoice_date,i.due_date,i.net_cents,i.gross_cents,i.paid_cents,i.status,i.invoice_number FROM office_finance_sales_invoice i WHERE $where");$q->execute($args);
    $result=['receipts_by_days'=>[30=>0,60=>0,90=>0],'available'=>true,'as_of'=>$today,'year'=>substr($today,0,4),'ytd_net_cents'=>0,'outstanding_gross_cents'=>0,'overdue_gross_cents'=>0,'paid_gross_cents'=>0,'draft_gross_cents'=>0,'draft_count'=>0,'outstanding_count'=>0,'overdue_count'=>0,'unbilled_net_cents'=>null,'contracts_complete'=>false,'ageing'=>['1–30'=>0,'31–60'=>0,'61–90'=>0,'90+'=>0]];
    while($i=$q->fetch(PDO::FETCH_ASSOC)){
      if($i['status']==='draft'){$result['draft_gross_cents']+=(int)$i['gross_cents'];$result['draft_count']++;continue;}
      if(($i['invoice_number']??'')===''||!in_array($i['status'],['sent','reminder_sending','reconciliation_required'],true))continue;
      if(substr($i['invoice_date'],0,4)===$result['year']&&$i['invoice_date']<=$today)$result['ytd_net_cents']+=(int)$i['net_cents'];
      $result['paid_gross_cents']+=(int)$i['paid_cents'];$open=max(0,(int)$i['gross_cents']-(int)$i['paid_cents']);
      if($open===0)continue;$result['outstanding_gross_cents']+=$open;$result['outstanding_count']++;
      foreach([30,60,90] as $days)if($i['due_date']<=$date->modify('+'.$days.' days')->format('Y-m-d'))$result['receipts_by_days'][$days]+=$open;
      if($i['due_date']<$today){$result['overdue_gross_cents']+=$open;$result['overdue_count']++;$age=(int)$date->diff(new \DateTimeImmutable($i['due_date'],new \DateTimeZone('UTC')))->days;$bucket=$age<=30?'1–30':($age<=60?'31–60':($age<=90?'61–90':'90+'));$result['ageing'][$bucket]+=$open;}
    }
    try{
      $where="c.currency='EUR' AND c.status IN ('signed','accepted','approved','active','confirmed') AND EXISTS (SELECT 1 FROM office_project_memberships m WHERE m.project_id=c.office_project_id AND m.operator_id=? AND m.permission IN ('read','manage'))";$args=[$operatorId];if($projectId!==null){$where.=' AND c.office_project_id=?';$args[]=$projectId;}
      $q=$db->prepare("SELECT c.id,c.contract_amount,(SELECT COALESCE(SUM(i.net_cents),0) FROM office_finance_sales_invoice i JOIN office_finance_sales_context x ON x.invoice_id=i.id WHERE x.source_contract_id=c.id AND i.office_project_id=c.office_project_id AND $issued) AS issued_net,(SELECT COUNT(*) FROM office_project_terms t WHERE t.contract_id=c.id AND t.status IN ('invoiced','paid') AND NOT EXISTS (SELECT 1 FROM office_finance_sales_context x JOIN office_finance_sales_invoice i ON i.id=x.invoice_id WHERE x.source_term_id=t.id AND x.source_contract_id=c.id AND i.office_project_id=c.office_project_id AND $issued)) AS missing FROM office_project_contracts c WHERE $where");$q->execute($args);
      $total=0;$complete=true;
      while($c=$q->fetch(PDO::FETCH_ASSOC)){
        if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',(string)$c['contract_amount'])){$complete=false;continue;}
        [$a,$b]=array_pad(explode('.',(string)$c['contract_amount']),2,'');$contract=(int)$a*100+(int)str_pad($b,2,'0');
        if((int)$c['missing']>0||(int)$c['issued_net']>$contract)$complete=false;
        $total+=max(0,$contract-(int)$c['issued_net']);
      }
      $result['contracts_complete']=$complete;if($complete)$result['unbilled_net_cents']=$total;
    }catch(\PDOException){/* Native invoices stay readable when the contract source is absent. */}
    require_once __DIR__.'/OfficeFinanceCostReadiness.php';
    $result['costs']=OfficeFinanceCostReadiness::read($db,$operatorId,$projectId,$today);
    return $result;
  }
  private static function money(int $cents): string {return '€ '.number_format(intdiv($cents,100),0,',','.').','.sprintf('%02d',$cents%100);}
  public static function cards(array $sales,string $salesUrl): string {
    $e=static fn(mixed $v): string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$ok=($sales['available']??false)===true;
    $definitions=[
      ['Omzet YTD','ytd_net_cents','Verzonden Office-verkoopfacturen met factuurdatum in dit jaar, exclusief btw. Concepten tellen niet mee.'],
      ['Openstaande verkoopfacturen','outstanding_gross_cents','Verzonden facturen minus geregistreerde ontvangsten, inclusief btw.'],
      ['Vervallen verkoopfacturen','overdue_gross_cents','Openstaande bedragen waarvan de vervaldatum is verstreken, inclusief btw.'],
      ['Conceptfacturen','draft_gross_cents','Nog niet verzonden. Inclusief btw; geen omzet of ontvangen betaling.'],
      ['Te factureren','unbilled_net_cents','Resterende aanneemsom van vrijgegeven EUR-contracten, exclusief btw. Concepten blijven hierin opgenomen. Meerwerk buiten deze contracten is niet inbegrepen.'],
      ['Brutomarge',null,'Voor een volledige brutomarge ontbreken nog gewaardeerde arbeidskosten en complete geboekte directe kosten.'],
      ['Bedrijfsresultaat YTD',null,'Voor bedrijfsresultaat ontbreken nog complete geboekte arbeidskosten en bedrijfskosten.'],
    ];$html='';
    foreach($definitions as [$label,$key,$reason]){
      $value=$ok&&$key!==null&&isset($sales[$key])?self::money((int)$sales[$key]):'Niet beschikbaar';
      if(!$ok&&$key!==null)$reason='Office-verkoopadministratie is nog niet beschikbaar.';
      if($ok&&$key==='unbilled_net_cents'&&($sales['contracts_complete']??false)!==true)$reason='Contractbron ontbreekt of eerdere termijnfacturen zijn nog niet volledig gekoppeld.';
      $html.='<div class="card"><span>'.$e($label).'</span><strong>'.$e($value).'</strong><small>'.$e($reason).'</small>'.($key!==null?'<p><a href="'.$e($salesUrl.($key==='unbilled_net_cents'?'?terms=1':'')).'">'.($key==='unbilled_net_cents'?'Naar termijnfacturen':'Naar verkoopfacturen').'</a></p>':'').'</div>';
    }
    require_once __DIR__.'/OfficeFinanceCostReadiness.php';
    $html.=OfficeFinanceCostReadiness::cards($sales['costs']??[]);
    if($ok){$html.='<div class="card"><span>Ouderdom vervallen facturen</span>';foreach($sales['ageing'] as $bucket=>$cents)$html.='<p>'.$e($bucket).' dagen: '.$e(self::money((int)$cents)).'</p>';$html.='<small>Inclusief btw; stand '.$e($sales['as_of']).'. Geregistreerd betaald over alle factuurjaren: '.$e(self::money((int)$sales['paid_gross_cents'])).'.</small></div>';}
    return $html;
  }
}
