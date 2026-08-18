<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Cross-domain operational escalation cockpit. */
final class RiskControlRoomController extends ControllerBase {
  public function __construct(private readonly Connection $database,private readonly EntityTypeManagerInterface $entityTypeManager,private readonly RequestStack $requestStack) {}
  public static function create(ContainerInterface $container): static {return new static($container->get('database'),$container->get('entity_type.manager'),$container->get('request_stack'));}

  public function overview(): array {
    if(!$this->database->schema()->tableExists('brebo_risk_escalation')) return ['#markup'=>'<p>De escalatielaag moet nog via database-updates worden geïnstalleerd.</p>'];
    $audience=trim((string)$this->requestStack->getCurrentRequest()->query->get('audience',''));
    $q=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('status','open')->orderBy('level','ASC')->orderBy('created','DESC')->range(0,250);
    if($audience!=='')$q->condition('audiences_json','%"'.$this->database->escapeLike($audience).'"%','LIKE');
    $items=$q->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC);$projectIds=[];
    foreach($items as$item){$payload=json_decode((string)$item['payload_json'],TRUE)?:[];if(!empty($payload['project_nid']))$projectIds[]=(int)$payload['project_nid'];}
    $projects=$this->entityTypeManager->getStorage('node')->loadMultiple(array_unique($projectIds));$rows=[];$counts=['kritiek'=>0,'aandacht'=>0,'actie'=>0];
    foreach($items as$item){$payload=json_decode((string)$item['payload_json'],TRUE)?:[];$audiences=json_decode((string)$item['audiences_json'],TRUE)?:[];$level=(string)$item['level'];if(isset($counts[$level]))$counts[$level]++;
      $pid=(int)($payload['project_nid']??0);$impact=$payload['impact']??[];$forecast=$payload['forecast']??[];
      $rows[]=[strtoupper($level),$item['title'],$pid&&isset($projects[$pid])?$projects[$pid]->label():($pid?'Project #'.$pid:'-'),implode(', ',$audiences),$impact['summary']??'-',isset($forecast['expected_known_exposure'])?'€ '.number_format((float)$forecast['expected_known_exposure'],2,',','.'):'-',date('d-m-Y H:i',(int)$item['created'])];
    }
    return [
      'summary'=>['#theme'=>'item_list','#title'=>$this->t('Open operationele escalaties'),'#items'=>[$this->t('Kritiek: @n',['@n'=>$counts['kritiek']]),$this->t('Aandacht: @n',['@n'=>$counts['aandacht']]),$this->t('Actie: @n',['@n'=>$counts['actie']])]],
      'filters'=>['#type'=>'container','links'=>['#markup'=>'<p><a href="?">Alles</a> · <a href="?audience=projectleiding">Projectleiding</a> · <a href="?audience=planning">Planning</a> · <a href="?audience=inkoop">Inkoop</a></p>']],
      'table'=>['#type'=>'table','#header'=>[$this->t('Niveau'),$this->t('Signaal'),$this->t('Project'),$this->t('Voor'),$this->t('Impact'),$this->t('Bekende blootstelling'),$this->t('Ontstaan')],'#rows'=>$rows,'#empty'=>$this->t('Geen open escalaties. Goed teken.')],
    ];
  }
}
