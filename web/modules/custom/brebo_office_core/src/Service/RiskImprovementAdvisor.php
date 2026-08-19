<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;

/** Detects recurring risk patterns and proposes preventive improvements. */
final class RiskImprovementAdvisor {
  public function __construct(private readonly Connection $database) {}

  /** @return array<int,array<string,mixed>> */
  public function suggestions(int $days = 90, int $minimumOccurrences = 3): array {
    if (!$this->database->schema()->tableExists('brebo_risk_escalation')) return [];
    $since=time()-($days*86400);$rows=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('created',$since,'>=')->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC);
    $patterns=[];
    foreach($rows as$row){
      $payload=json_decode((string)($row['payload_json']??''),TRUE)?:[];
      $cause=trim((string)($payload['primary_reason']??($payload['impact']['summary']??'')));
      if($cause==='')continue;
      $domain=(string)($row['domain']??'onbekend');$key=hash('sha256',$domain.'|'.$cause);
      if(!isset($patterns[$key]))$patterns[$key]=['domain'=>$domain,'cause'=>$cause,'count'=>0,'open'=>0,'exposure'=>0.0,'projects'=>[],'audiences'=>[]];
      $patterns[$key]['count']++;
      if(!in_array((string)$row['status'],['closed','resolved'],TRUE))$patterns[$key]['open']++;
      $forecast=$payload['forecast']??[];$patterns[$key]['exposure']+=(float)($forecast['expected_known_exposure']??0);
      if(!empty($payload['project_nid']))$patterns[$key]['projects'][(int)$payload['project_nid']]=TRUE;
      foreach((json_decode((string)($row['audiences_json']??''),TRUE)?:[])as$audience)$patterns[$key]['audiences'][(string)$audience]=TRUE;
    }
    $suggestions=[];
    foreach($patterns as$pattern){
      if($pattern['count']<$minimumOccurrences)continue;
      $suggestions[]=[
        'domain'=>$pattern['domain'],'cause'=>$pattern['cause'],'occurrences'=>$pattern['count'],'open'=>$pattern['open'],
        'project_count'=>count($pattern['projects']),'known_exposure'=>round((float)$pattern['exposure'],2),
        'audiences'=>array_keys($pattern['audiences']),'priority'=>$this->priority($pattern),
        'proposal'=>$this->proposal((string)$pattern['domain'],(string)$pattern['cause'],(int)$pattern['count']),
      ];
    }
    usort($suggestions,static fn(array$a,array$b):int=>[$b['priority'],$b['occurrences'],$b['known_exposure']]<=>[$a['priority'],$a['occurrences'],$a['known_exposure']]);
    return $suggestions;
  }

  /** @param array<string,mixed> $pattern */
  private function priority(array $pattern): int {
    return min(100,($pattern['count']*10)+($pattern['open']*8)+(count($pattern['projects'])*5)+((float)$pattern['exposure']>=5000?20:((float)$pattern['exposure']>=1000?10:0)));
  }

  private function proposal(string $domain,string $cause,int $count): string {
    return sprintf('Onderzoek structurele oorzaak in %s, pas de standaardwerkwijze/controlepoort aan en toets de maatregel op de eerstvolgende drie vergelijkbare gevallen. Aanleiding: %d herhalingen van "%s".', $domain, $count, $cause);
  }
}
