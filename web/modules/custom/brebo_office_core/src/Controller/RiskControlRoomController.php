<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Cross-domain operational escalation cockpit. */
final class RiskControlRoomController extends ControllerBase {
  public function __construct(private readonly Connection $database,private readonly EntityTypeManagerInterface $entityTypeManager,private readonly RequestStack $requestStack) {}
  public static function create(ContainerInterface $container): static{return new static($container->get('database'),$container->get('entity_type.manager'),$container->get('request_stack'));}

  public function overview():array{
    if(!$this->database->schema()->tableExists('brebo_risk_escalation'))return['#markup'=>'<p>De escalatielaag wordt automatisch aangemaakt zodra het eerste risico wordt geëscaleerd.</p>'];
    $audience=trim((string)$this->requestStack->getCurrentRequest()->query->get('audience',''));
    $q=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('status',['open','assigned','in_progress'],'IN')->orderBy('created','DESC')->range(0,250);
    if($audience!=='')$q->condition('audiences_json','%"'.$this->database->escapeLike($audience).'"%','LIKE');
    $items=$q->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC);$projectIds=[];$ownerIds=[];
    foreach($items as$item){$payload=json_decode((string)$item['payload_json'],TRUE)?:[];if(!empty($payload['project_nid']))$projectIds[]=(int)$payload['project_nid'];if(!empty($item['owner_uid']))$ownerIds[]=(int)$item['owner_uid'];}
    $projects=$this->entityTypeManager->getStorage('node')->loadMultiple(array_unique($projectIds));$owners=$this->entityTypeManager->getStorage('user')->loadMultiple(array_unique($ownerIds));
    $rows=[];$counts=['kritiek'=>0,'aandacht'=>0,'actie'=>0];$overdue=0;$today=date('Y-m-d');
    foreach($items as$item){
      $payload=json_decode((string)$item['payload_json'],TRUE)?:[];$audiences=json_decode((string)$item['audiences_json'],TRUE)?:[];$level=(string)$item['level'];if(isset($counts[$level]))$counts[$level]++;
      $pid=(int)($payload['project_nid']??0);$impact=$payload['impact']??[];$forecast=$payload['forecast']??[];$ownerId=(int)($item['owner_uid']??0);$due=(string)($item['due_date']??'');$isOverdue=$due!==''&&$due<$today;if($isOverdue)$overdue++;
      $rows[]=[
        'level'=>strtoupper($level),
        'title'=>$item['title'],
        'project'=>$pid&&isset($projects[$pid])?$projects[$pid]->label():($pid?'Project #'.$pid:'-'),
        'audiences'=>implode(', ',$audiences),
        'owner'=>$ownerId&&isset($owners[$ownerId])?$owners[$ownerId]->getDisplayName():'Nog niet toegewezen',
        'due'=>$due!==''?($isOverdue?'TE LAAT · '.$due:$due):'-',
        'status'=>(string)$item['status'],
        'impact'=>$impact['summary']??'-',
        'exposure'=>isset($forecast['expected_known_exposure'])?'€ '.number_format((float)$forecast['expected_known_exposure'],2,',','.'):'-',
        'action'=>Link::fromTextAndUrl($this->t('Afhandelen'),Url::fromRoute('brebo_glass.control_room_handle',['escalation_id'=>(int)$item['id']])),
      ];
    }
    return[
      'summary'=>['#theme'=>'item_list','#title'=>$this->t('Open operationele escalaties'),'#items'=>[$this->t('Kritiek: @n',['@n'=>$counts['kritiek']]),$this->t('Aandacht: @n',['@n'=>$counts['aandacht']]),$this->t('Actie: @n',['@n'=>$counts['actie']]),$this->t('Deadline overschreden: @n',['@n'=>$overdue])]],
      'filters'=>['#type'=>'container','links'=>['#markup'=>'<p><a href="?">Alles</a> · <a href="?audience=projectleiding">Projectleiding</a> · <a href="?audience=planning">Planning</a> · <a href="?audience=inkoop">Inkoop</a></p>']],
      'table'=>['#type'=>'table','#header'=>[$this->t('Niveau'),$this->t('Signaal'),$this->t('Project'),$this->t('Voor'),$this->t('Eigenaar'),$this->t('Deadline'),$this->t('Status'),$this->t('Impact'),$this->t('Bekende blootstelling'),$this->t('Actie')],'#rows'=>$rows,'#empty'=>$this->t('Geen open escalaties. Goed teken.')],
    ];
  }
}
