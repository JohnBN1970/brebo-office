<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Service\RiskEscalationActionBridge;
use Drupal\brebo_office_core\Service\RiskImprovementAdvisor;
use Drupal\brebo_office_core\Service\RiskManagementAnalytics;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Cross-domain operational escalation cockpit. */
final class RiskControlRoomController extends ControllerBase {
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
    private readonly RiskEscalationActionBridge $actionBridge,
    private readonly RiskManagementAnalytics $analytics,
    private readonly RiskImprovementAdvisor $improvementAdvisor,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('brebo_office_core.risk_escalation_action_bridge'),
      $container->get('brebo_office_core.risk_management_analytics'),
      $container->get('brebo_office_core.risk_improvement_advisor'),
    );
  }

  public function overview(): array {
    if(!$this->database->schema()->tableExists('brebo_risk_escalation')) return ['#markup'=>'<p>De escalatielaag wordt automatisch aangemaakt zodra het eerste risico wordt geëscaleerd.</p>'];
    $reconciled=$this->actionBridge->reconcileCompletedActions();
    if($reconciled>0) $this->messenger()->addStatus($this->formatPlural($reconciled,'1 escalatie automatisch gesloten vanuit een gereedgemelde BREBO Actie.','@count escalaties automatisch gesloten vanuit gereedgemelde BREBO Acties.'));

    $periodRows=[];
    foreach($this->analytics->periods() as$days=>$stats){
      $periodRows[]=[
        $days.' dagen',$stats['total'],$stats['open'],$stats['closed'],$stats['overdue'],$stats['closure_rate'].'%',
        $stats['avg_resolution_days']!==NULL?$stats['avg_resolution_days'].' d':'-',
        '€ '.number_format((float)$stats['open_exposure'],2,',','.'),
        '€ '.number_format((float)$stats['prevented_exposure'],2,',','.'),
      ];
    }
    $management90=$this->analytics->summary(90);
    $causeItems=[];foreach(($management90['top_causes']??[])as$cause=>$count)$causeItems[]=$cause.' ('.$count.')';
    $domainItems=[];foreach(($management90['top_domains']??[])as$domain=>$count)$domainItems[]=$domain.' ('.$count.')';

    $improvementRows=[];
    foreach($this->improvementAdvisor->suggestions(90,3) as$suggestion){
      $improvementRows[]=[
        $suggestion['priority'],
        $suggestion['domain'],
        $suggestion['cause'],
        $suggestion['occurrences'],
        $suggestion['project_count'],
        $suggestion['open'],
        $suggestion['known_exposure']>0?'€ '.number_format((float)$suggestion['known_exposure'],2,',','.'):'-',
        implode(', ',$suggestion['audiences'])?:'-',
        $suggestion['proposal'],
      ];
    }

    $audience=trim((string)$this->requestStack->getCurrentRequest()->query->get('audience',''));
    $q=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('status',['open','assigned','in_progress'],'IN')->orderBy('created','DESC')->range(0,250);
    if($audience!=='') $q->condition('audiences_json','%"'.$this->database->escapeLike($audience).'"%','LIKE');
    $items=$q->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC);$projectIds=[];$ownerIds=[];
    foreach($items as$item){$payload=json_decode((string)$item['payload_json'],TRUE)?:[];if(!empty($payload['project_nid']))$projectIds[]=(int)$payload['project_nid'];if(!empty($item['owner_uid']))$ownerIds[]=(int)$item['owner_uid'];}
    $projects=$this->entityTypeManager->getStorage('node')->loadMultiple(array_unique($projectIds));$owners=$this->entityTypeManager->getStorage('user')->loadMultiple(array_unique($ownerIds));
    $rows=[];$counts=['kritiek'=>0,'aandacht'=>0,'actie'=>0];$overdue=0;$today=date('Y-m-d');
    foreach($items as$item){
      $payload=json_decode((string)$item['payload_json'],TRUE)?:[];$audiences=json_decode((string)$item['audiences_json'],TRUE)?:[];$level=(string)$item['level'];if(isset($counts[$level]))$counts[$level]++;
      $pid=(int)($payload['project_nid']??0);$impact=$payload['impact']??[];$forecast=$payload['forecast']??[];$ownerId=(int)($item['owner_uid']??0);$due=(string)($item['due_date']??'');$isOverdue=$due!==''&&$due<$today;if($isOverdue)$overdue++;
      $rows[]=[
        'level'=>strtoupper($level),'title'=>$item['title'],'project'=>$pid&&isset($projects[$pid])?$projects[$pid]->label():($pid?'Project #'.$pid:'-'),
        'audiences'=>implode(', ',$audiences),'owner'=>$ownerId&&isset($owners[$ownerId])?$owners[$ownerId]->getDisplayName():'Nog niet toegewezen',
        'due'=>$due!==''?($isOverdue?'TE LAAT · '.$due:$due):'-','status'=>(string)$item['status'],'impact'=>$impact['summary']??'-',
        'exposure'=>isset($forecast['expected_known_exposure'])?'€ '.number_format((float)$forecast['expected_known_exposure'],2,',','.'):'-',
        'action'=>Link::fromTextAndUrl($this->t('Afhandelen'),Url::fromRoute('brebo_glass.control_room_handle',['escalation_id'=>(int)$item['id']])),
      ];
    }
    return[
      'management'=>['#type'=>'table','#caption'=>$this->t('Managementtrend Control Room'),'#header'=>[$this->t('Periode'),$this->t('Ontstaan'),$this->t('Open'),$this->t('Opgelost'),$this->t('Te laat'),$this->t('Oplosgraad'),$this->t('Gem. oplostijd'),$this->t('Open € blootstelling'),$this->t('Afgewende € impact')],'#rows'=>$periodRows],
      'management_causes'=>['#theme'=>'item_list','#title'=>$this->t('Top oorzaken - 90 dagen'),'#items'=>$causeItems?:[$this->t('Nog onvoldoende data.')]],
      'management_domains'=>['#theme'=>'item_list','#title'=>$this->t('Bronnen van escalaties - 90 dagen'),'#items'=>$domainItems?:[$this->t('Nog onvoldoende data.')]],
      'improvements'=>['#type'=>'table','#caption'=>$this->t('Voorgestelde structurele verbeteringen - 90 dagen'),'#header'=>[$this->t('Prioriteit'),$this->t('Domein'),$this->t('Terugkerende oorzaak'),$this->t('Herhalingen'),$this->t('Projecten'),$this->t('Nog open'),$this->t('Bekende € blootstelling'),$this->t('Betrokken disciplines'),$this->t('Preventief voorstel')],'#rows'=>$improvementRows,'#empty'=>$this->t('Nog geen patroon met minimaal drie vergelijkbare escalaties. Fijn.')],
      'summary'=>['#theme'=>'item_list','#title'=>$this->t('Open operationele escalaties'),'#items'=>[$this->t('Kritiek: @n',['@n'=>$counts['kritiek']]),$this->t('Aandacht: @n',['@n'=>$counts['aandacht']]),$this->t('Actie: @n',['@n'=>$counts['actie']]),$this->t('Deadline overschreden: @n',['@n'=>$overdue])]],
      'filters'=>['#type'=>'container','links'=>['#markup'=>'<p><a href="?">Alles</a> · <a href="?audience=projectleiding">Projectleiding</a> · <a href="?audience=planning">Planning</a> · <a href="?audience=inkoop">Inkoop</a></p>']],
      'table'=>['#type'=>'table','#header'=>[$this->t('Niveau'),$this->t('Signaal'),$this->t('Project'),$this->t('Voor'),$this->t('Eigenaar'),$this->t('Deadline'),$this->t('Status'),$this->t('Impact'),$this->t('Bekende blootstelling'),$this->t('Actie')],'#rows'=>$rows,'#empty'=>$this->t('Geen open escalaties. Goed teken.')],
    ];
  }
}
