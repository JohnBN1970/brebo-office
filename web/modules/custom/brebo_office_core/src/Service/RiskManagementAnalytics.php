<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;

/** Management analytics over cross-domain BREBO escalations. */
final class RiskManagementAnalytics {
  public function __construct(private readonly Connection $database) {}

  /** @return array<string,mixed> */
  public function summary(int $days = 90): array {
    if (!$this->database->schema()->tableExists('brebo_risk_escalation')) return [];
    $since=time()-($days*86400);$rows=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('created',$since,'>=')->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC);
    $total=count($rows);$closed=0;$open=0;$overdue=0;$resolutionSeconds=0;$resolvedCount=0;$exposure=0.0;$domains=[];$causes=[];$today=date('Y-m-d');
    foreach($rows as$row){
      $status=(string)$row['status'];$isClosed=in_array($status,['closed','resolved'],TRUE);if($isClosed)$closed++;else $open++;
      if(!$isClosed&&!empty($row['due_date'])&&(string)$row['due_date']<$today)$overdue++;
      if($isClosed&&!empty($row['closed_at'])){$resolutionSeconds+=max(0,(int)$row['closed_at']-(int)$row['created']);$resolvedCount++;}
      $domain=(string)($row['source_domain']??'onbekend');$domains[$domain]=($domains[$domain]??0)+1;
      $payload=json_decode((string)($row['payload_json']??''),TRUE)?:[];$forecast=$payload['forecast']??[];$exposure+=(float)($forecast['expected_known_exposure']??0);
      $reason=trim((string)($payload['primary_reason']??($payload['impact']['summary']??'')));if($reason!=='')$causes[$reason]=($causes[$reason]??0)+1;
    }
    arsort($domains);arsort($causes);
    return ['period_days'=>$days,'total'=>$total,'open'=>$open,'closed'=>$closed,'overdue'=>$overdue,'closure_rate'=>$total?round(($closed/$total)*100,1):0.0,'avg_resolution_days'=>$resolvedCount?round(($resolutionSeconds/$resolvedCount)/86400,1):NULL,'known_exposure'=>round($exposure,2),'top_domains'=>array_slice($domains,0,5,TRUE),'top_causes'=>array_slice($causes,0,5,TRUE)];
  }
}
