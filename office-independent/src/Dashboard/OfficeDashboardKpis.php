<?php
declare(strict_types=1);

namespace Brebo\Office\Dashboard;

use PDO;
use RuntimeException;

/**
 * Read-only KPI provider using the existing Office project-membership ACL.
 * Never aggregates projects that the authenticated operator cannot read.
 */
final class OfficeDashboardKpis {
  public function __construct(private PDO $db) {}

  /** @return array<string,array{label:string,value:string,note:string}> */
  public function forOperator(string $operatorId): array {
    if (!ctype_digit($operatorId) || (int)$operatorId < 1) {
      throw new RuntimeException('Authenticated Office operator required');
    }
    $query = $this->db->prepare(
      "SELECT COUNT(DISTINCT p.id) FROM office_projects p
       INNER JOIN office_project_memberships m ON m.project_id=p.id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id
       WHERE m.operator_id=:operator AND m.permission IN ('read','manage') AND a.active=1"
    );
    $query->execute([':operator' => $operatorId]);
    $raw = $query->fetchColumn();
    if (!is_int($raw) && (!is_string($raw) || !ctype_digit($raw))) {
      throw new RuntimeException('Project count unavailable');
    }
    return ['projects' => [
      'label' => 'Mijn projecten',
      'value' => (string)$raw,
      'note' => 'Projecten met actieve Office-toegang',
    ]];
  }

  /** Reuse module readers after their live Office grants; never query external systems. */
  public function suite(string $operatorId): array {
    $base=$this->forOperator($operatorId);
    require_once __DIR__.'/OfficeDashboardCatalog.php';
    $widgets=['projects'=>['value'=>(int)$base['projects']['value'],'note'=>'Volledige toegankelijke portefeuille','tone'=>'neutral']];
    $q=$this->db->prepare("SELECT DISTINCT p.id,p.title,p.status FROM office_projects p JOIN office_project_memberships m ON m.project_id=p.id JOIN office_operator_accounts a ON a.id=m.operator_id WHERE m.operator_id=? AND m.permission IN ('read','manage') AND a.active=1 ORDER BY p.id");
    $q->execute([$operatorId]);$projects=$q->fetchAll(PDO::FETCH_ASSOC);
    $read=static function(callable $provider):mixed {try{return $provider();}catch(\Throwable){return null;}};
    $put=static function(string $id,?int $value,string $note,string $tone='neutral')use(&$widgets):void {$widgets[$id]=compact('value','note','tone');};
    require_once __DIR__.'/../Project/OfficeProjectPlanReader.php';
    require_once __DIR__.'/../Project/OfficeProjectTrafficLight.php';
    $enriched=$read(fn()=>\Brebo\Office\Project\OfficeProjectPlanReader::enrich($this->db,$projects));
    $critical=null;$waiting=null;
    if($enriched!==null){
      $projects=$enriched;
      // A malformed project status must not break the whole dashboard or yield partial totals.
      $traffic=$read(static function()use($projects):array {
        $criticalCount=0;$waitingCount=0;
        foreach($projects as $p){
          $color=\Brebo\Office\Project\OfficeProjectTrafficLight::assess($p)['color'];
          $criticalCount+=(int)($color==='red');$waitingCount+=(int)($color==='orange');
        }
        return ['critical'=>$criticalCount,'waiting'=>$waitingCount];
      });
      if($traffic!==null){$critical=$traffic['critical'];$waiting=$traffic['waiting'];}
    }
    $put('critical',$critical,'Volgens bestaande projectbewaking',$critical>0?'red':'neutral');
    $put('waiting',$waiting,'Volgens bestaande projectbewaking',$waiting>0?'yellow':'neutral');
    require_once __DIR__.'/../Project/OfficeProjectPortfolioPlannedHours.php';
    require_once __DIR__.'/../Inzet/OfficeApprovedHoursWeekRepository.php';
    $now=new \DateTimeImmutable('now',new \DateTimeZone('Europe/Amsterdam'));
    $put('planned',$read(fn()=>\Brebo\Office\Project\OfficeProjectPortfolioPlannedHours::forWeek($this->db,$operatorId,(int)$now->format('o'),(int)$now->format('W'))),'Week '.$now->format('W · o'));
    $put('approved',$read(fn()=>(new \Brebo\Office\Inzet\OfficeApprovedHoursWeekRepository($this->db))->forOperatorAllTime($operatorId)),'Alleen goedgekeurde uren; volledige portefeuille');
    require_once __DIR__.'/../Finance/OfficeFinanceAccess.php';
    $financeData=null;$budgetRows=[];
    $finance=$read(fn()=>(new \Brebo\Office\Finance\OfficeFinanceAccess($this->db,$operatorId))->allows())===true;
    if($finance){
      require_once __DIR__.'/../Project/OfficeProjectPortfolioTotals.php';
      foreach(['budget'=>'lockedProcurementBudgets','contract'=>'acceptedContractValues'] as $id=>$method){
        $total=$read(fn()=>\Brebo\Office\Project\OfficeProjectPortfolioTotals::$method($this->db,$projects));
        $put($id,$total['cents']??null,'Alleen beschikbaar bij een complete grondslag');
      }
      foreach(['LockedBaselineLines','WorkingBudgetKpiProjection','ProjectBudgetReadModel'] as $class)require_once __DIR__.'/../WorkingBudget/'.$class.'.php';
      foreach($projects as $project){$budgetRows[]=['id'=>$project['id'],'title'=>$project['title'],'data'=>$read(fn()=>(new \Brebo\Office\WorkingBudget\ProjectBudgetReadModel($this->db))->forAuthorizedProject((int)$project['id'],null,null,null))];}
      require_once __DIR__.'/../Finance/OfficeFinanceSalesOverview.php';
      $sales=$read(fn()=>\Brebo\Office\Finance\OfficeFinanceSalesOverview::read($this->db,$operatorId,null,$now->format('Y-m-d')));
      require_once __DIR__.'/../Finance/OfficeFinanceDashboard.php';
      $bank=$read(fn()=>(new \Brebo\Office\Finance\OfficeFinanceAccess($this->db,$operatorId))->allowsBankRead())===true;
      $creditors=null;
      if($salesAvailableForCreditors=is_array($sales)&&($sales['available']??false)===true&&isset($sales['center']['postings'])&&is_array($sales['center']['postings'])){
        $creditors=0;
        foreach($sales['center']['postings'] as $posting){
          if(!is_array($posting)||!isset($posting['source_kind'],$posting['status'],$posting['cash_due'])){$creditors=null;break;}
          if($posting['source_kind']!=='purchase'||$posting['status']!=='booked')continue;
          if(!in_array((string)$posting['cash_due'],['0','1'],true)){$creditors=null;break;}
          if((string)$posting['cash_due']!=='1')continue;
          if(!isset($posting['gross_cents'],$posting['paid_cents'])||!preg_match('/^\\d+$/D',(string)$posting['gross_cents'])||!preg_match('/^\\d+$/D',(string)$posting['paid_cents'])||((float)$posting['gross_cents'])>PHP_INT_MAX||((float)$posting['paid_cents'])>PHP_INT_MAX){$creditors=null;break;}
          $creditors+=max(0,(int)$posting['gross_cents']-(int)$posting['paid_cents']);
        }
      }
      $put('creditors',$creditors,'Geboekte leveranciersfacturen minus geregistreerde betalingen · incl. btw');
      $financeData=['sales_overview'=>$sales??['available'=>false],'bank_read_allowed'=>$bank,'finance_unlinked_project'=>true];
      $salesAvailable=is_array($sales)&&($sales['available']??false)===true;
      foreach(['revenue'=>'ytd_net_cents','debtors'=>'outstanding_gross_cents','overdue'=>'overdue_gross_cents','drafts'=>'draft_count'] as $id=>$key){
        $value=$salesAvailable&&isset($sales[$key])&&is_int($sales[$key])&&$sales[$key]>=0?$sales[$key]:null;
        $put($id,$value,$id==='revenue'?'Verzonden facturen · exclusief btw':'Office-verkoopadministratie',$id==='overdue'&&$value!==null&&$value>0?'yellow':'neutral');
      }
    }
    require_once __DIR__.'/../Governance/bootstrap.php';
    $store=new \Brebo\Office\Governance\Infrastructure\PdoOfficeGovernanceStore($this->db);
    foreach(['quality','risk'] as $module){
      $contexts=$read(fn()=>$store->contexts($operatorId,$module));
      if(!$contexts)continue;
      $rows=$read(function()use($store,$operatorId,$module,$contexts){$rows=[];foreach($contexts as $c)foreach($store->listing($operatorId,$module,(int)$c['project_id'],(int)$c['building_id']) as $r)$rows[$r['id']]=$r;return array_values($rows);});
      $open=$rows===null?null:count(array_filter($rows,static fn($r)=>!in_array($r['status'],['accepted','closed'],true)));
      $put($module==='risk'?'risks':'quality',$open,'Alleen toegankelijke project- en gebouwdossiers',$open>0?'yellow':'neutral');
      if($module==='quality'){$count=$rows===null?null:count(array_filter($rows,static fn($r)=>$r['status']==='deviation'));$put('deviations',$count,'Geregistreerde afwijkingen',$count>0?'red':'neutral');}
    }
    $documents=$read(function()use($operatorId){
      $q=$this->db->prepare("SELECT COUNT(DISTINCT d.id) FROM office_documents d JOIN office_document_originals o ON o.id=d.original_id JOIN office_document_contexts c ON c.document_id=d.id WHERE d.lifecycle_status='active' AND o.storage_provider='office_private' AND c.context_type='project' AND EXISTS (SELECT 1 FROM office_project_memberships m JOIN office_operator_accounts a ON a.id=m.operator_id WHERE m.operator_id=? AND a.active=1 AND m.permission IN ('read','manage') AND CAST(m.project_id AS CHAR)=c.context_id) AND NOT EXISTS (SELECT 1 FROM office_document_contexts extra WHERE extra.document_id=d.id AND NOT (extra.context_type=c.context_type AND extra.context_id=c.context_id))");$q->execute([$operatorId]);return (int)$q->fetchColumn();
    });
    $put('documents',$documents,'Actieve bestanden met uitsluitend één toegankelijke projectcontext');
    return ['widgets'=>$widgets,'projects'=>$projects,'finance'=>$finance,'finance_data'=>$financeData,'budget_rows'=>$budgetRows];
  }
}
