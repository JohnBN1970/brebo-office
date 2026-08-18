<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Controller;

use Drupal\brebo_glass\Form\GlassPositionFilterForm;
use Drupal\brebo_glass\Service\GlassApprovalPolicy;
use Drupal\brebo_glass\Service\GlassOperationalRiskEvaluator;
use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\brebo_glass\Service\GlassProjectRiskAggregator;
use Drupal\brebo_glass\Service\GlassRiskEscalationService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Displays the risk-prioritized operational glass schedule. */
final class GlassPositionOverviewController extends ControllerBase {
  private const APPLICATION_LABELS=['standard'=>'Standaard','door'=>'Deur','adjacent_door'=>'Naast deur','low_level'=>'Laag bij vloer','wet_area'=>'Natte ruimte','ceiling'=>'Plafond','overhead'=>'Boven personen','fall_protection'=>'Doorvalbeveiliging','fire_separation'=>'Brandscheiding'];
  private const CHECK_LABELS=['pending'=>'Nog niet gecontroleerd','passed'=>'Voorcontrole akkoord','expert_review'=>'Deskundige beoordeling','blocked'=>'Geblokkeerd'];
  private const STATUS_LABELS=['concept'=>'Concept','measured'=>'Ingemeten','approved'=>'Technisch vrijgegeven','ordered'=>'Besteld','delivered'=>'Geleverd','installed'=>'Gemonteerd'];

  public function __construct(
    private readonly GlassPositionRepository $repository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
    private readonly GlassApprovalPolicy $approvalPolicy,
    private readonly GlassOperationalRiskEvaluator $riskEvaluator,
    private readonly GlassProjectRiskAggregator $projectRiskAggregator,
    private readonly GlassRiskEscalationService $riskEscalationService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_glass.position_repository'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('brebo_glass.approval_policy'),
      $container->get('brebo_glass.operational_risk_evaluator'),
      $container->get('brebo_glass.project_risk_aggregator'),
      $container->get('brebo_glass.risk_escalation_service'),
    );
  }

  public function overview(): array {
    $request=$this->requestStack->getCurrentRequest();$search=trim((string)$request->query->get('q',''));$status=(string)$request->query->get('status','');if($status!==''&&!isset(self::STATUS_LABELS[$status]))$status='';
    $positions=$this->repository->findAll($search,$status,'changed','desc');$counts=$this->repository->countByStatus();
    $storage=$this->entityTypeManager->getStorage('node');$nodeIds=[];foreach($positions as$p){$nodeIds[]=(int)$p['building_nid'];if(!empty($p['project_nid']))$nodeIds[]=(int)$p['project_nid'];}$nodes=$storage->loadMultiple(array_unique($nodeIds));
    $user=$this->currentUser();$canApprove=$user->hasPermission('approve brebo glass positions');$canExport=$user->hasPermission('export brebo glass to calculation');$canProcure=$user->hasPermission('create brebo procurement requests');$canComplete=$user->hasPermission('complete brebo glass positions');

    $prioritized=[];$critical=0;$attention=0;
    foreach($positions as$p){
      $risk=$this->riskEvaluator->evaluate($p);
      if(in_array($risk['level'],['kritiek','aandacht'],TRUE)){
        try{$this->riskEscalationService->evaluateAndEscalate($p);}catch(\Throwable){}
      }
      $p['_risk']=$risk;$prioritized[]=$p;if($risk['level']==='kritiek')$critical++;elseif($risk['level']==='aandacht')$attention++;
    }
    usort($prioritized,static fn(array$a,array$b):int=>[$b['_risk']['score'],(int)($b['changed']??0)]<=>[$a['_risk']['score'],(int)($a['changed']??0)]);

    $projectRows=[];foreach($this->projectRiskAggregator->aggregate($positions) as$project){$projectId=(int)$project['project_nid'];$projectRows[]=[
      'project'=>isset($nodes[$projectId])?Link::createFromRoute($nodes[$projectId]->label(),'entity.node.canonical',['node'=>$projectId]):$this->t('Project #@id',['@id'=>$projectId]),
      'level'=>strtoupper((string)$project['level']),'summary'=>$project['summary'],
      'financial'=>$project['priced_risk_positions']>0?'€ '.number_format((float)$project['financial_value_at_risk'],2,',','.'):$this->t('Nog niet geprijsd'),
      'labour_value'=>$project['labour_value_at_risk']>0?'€ '.number_format((float)$project['labour_value_at_risk'],2,',','.'):'-',
      'unpriced'=>(int)$project['unpriced_risk_positions'],'delay'=>$project['max_delay_days']>0?$this->formatPlural($project['max_delay_days'],'1 dag','@count dagen'):'-','reason'=>$project['top_reason']?:'-',
    ];}

    $rows=[];foreach($prioritized as$p){$id=(int)$p['id'];$buildingId=(int)$p['building_nid'];$projectId=(int)($p['project_nid']??0);$state=(string)$p['technical_status'];$policy=$this->approvalPolicy->evaluate($p);$risk=$p['_risk'];
      $approval=$canApprove&&$policy['allowed']?Link::createFromRoute($this->t('Vrijgeven'),'brebo_glass.position_approve',['position_id'=>$id]):'-';
      $calculation=$canExport&&$state==='approved'?Link::createFromRoute($this->t('Calculatie'),'brebo_glass.position_to_calculation',['position_id'=>$id]):'-';
      $procurement=$canProcure&&$state==='approved'?Link::createFromRoute($this->t('Inkoop'),'brebo_glass.position_to_procurement',['position_id'=>$id]):($state==='ordered'?$this->t('Besteld'):($state==='delivered'?$this->t('Geleverd'):'-'));
      $mounting=$canComplete&&$state==='delivered'?Link::createFromRoute($this->t('Monteren / gereed'),'brebo_glass.position_complete',['position_id'=>$id]):($state==='installed'?$this->t('Gereed'):'-');
      $signal=match($state){'concept'=>$this->t('Opname afronden'),'measured'=>$this->t('Technisch controleren'),'approved'=>$this->t('Inkoop gereedzetten'),'ordered'=>$this->t('Levering bewaken'),'delivered'=>$this->t('Klaar voor montage'),'installed'=>$this->t('Afgerond'),default=>'-'};
      $riskText=$risk['score']>0?strtoupper($risk['level']).' · '.implode('; ',$risk['reasons']).' · '.($risk['impact']['summary']??''):$this->t('Normaal');
      $rows[]=['risk'=>$riskText,'building'=>isset($nodes[$buildingId])?Link::createFromRoute($nodes[$buildingId]->label(),'entity.node.canonical',['node'=>$buildingId]):$this->t('Gebouw #@id',['@id'=>$buildingId]),'project'=>$projectId&&isset($nodes[$projectId])?Link::createFromRoute($nodes[$projectId]->label(),'entity.node.canonical',['node'=>$projectId]):'-','position'=>$p['position_code'],'location'=>$p['location'],'application'=>$this->t(self::APPLICATION_LABELS[$p['application_type']]??$p['application_type']),'specification'=>$p['composition'],'dimensions'=>$p['width_mm'].' × '.$p['height_mm'].' mm','quantity'=>$p['quantity'],'technical_check'=>$this->t(self::CHECK_LABELS[$p['technical_check_state']]??$p['technical_check_state']),'status'=>$this->t(self::STATUS_LABELS[$state]??$state),'signal'=>$signal,'approval'=>$approval,'calculation'=>$calculation,'procurement'=>$procurement,'mounting'=>$mounting];
    }

    $build['alerts']=['#theme'=>'item_list','#title'=>$this->t('Vandaag aandacht nodig'),'#items'=>[$this->t('Kritiek: @n',['@n'=>$critical]),$this->t('Aandacht: @n',['@n'=>$attention]),$this->t('Posities totaal: @n',['@n'=>$counts['all']??0])]];
    $build['projects']=['#type'=>'table','#caption'=>$this->t('Projectimpact glas'),'#header'=>[$this->t('Project'),$this->t('Niveau'),$this->t('Impact'),$this->t('€ calculatiewaarde risico'),$this->t('€ arbeidswaarde risico'),$this->t('Ongeprijsd'),$this->t('Max. vertraging'),$this->t('Belangrijkste oorzaak')],'#rows'=>$projectRows,'#empty'=>$this->t('Geen projectgebonden glasrisico’s gevonden.')];
    $build['summary']=['#theme'=>'item_list','#title'=>$this->t('Operationele glasstatus'),'#items'=>[$this->t('Vrijgegeven: @n',['@n'=>$counts['approved']??0]),$this->t('Besteld: @n',['@n'=>$counts['ordered']??0]),$this->t('Geleverd: @n',['@n'=>$counts['delivered']??0]),$this->t('Gemonteerd: @n',['@n'=>$counts['installed']??0])]];
    $build['filters']=$this->formBuilder()->getForm(GlassPositionFilterForm::class);$build['actions']=['#type'=>'actions'];$build['actions']['new']=['#type'=>'link','#title'=>$this->t('Nieuwe glasopname'),'#url'=>Url::fromRoute('brebo_glass.position_add'),'#attributes'=>['class'=>['button','button--primary']]];
    if($canProcure)$build['actions']['bundle']=['#type'=>'link','#title'=>$this->t('Glas bundelen voor inkoop'),'#url'=>Url::fromRoute('brebo_glass.procurement_bundle'),'#attributes'=>['class'=>['button']]];if($user->hasPermission('view brebo procurement'))$build['actions']['orders']=['#type'=>'link','#title'=>$this->t('Leveringen bewaken'),'#url'=>Url::fromRoute('brebo_procurement.order_overview'),'#attributes'=>['class'=>['button']]];
    $build['table']=['#type'=>'table','#header'=>[$this->t('Prioriteit'),$this->t('Gebouw'),$this->t('Project'),$this->t('Positie'),$this->t('Locatie'),$this->t('Toepassing'),$this->t('Opbouw'),$this->t('Bestelmaat'),$this->t('Aantal'),$this->t('Technische controle'),$this->t('Status'),$this->t('Volgende stap'),$this->t('Vrijgave'),$this->t('Calculatie'),$this->t('Inkoop'),$this->t('Montage')],'#rows'=>$rows,'#empty'=>$this->t('Geen glasposities gevonden.'),'#sticky'=>TRUE];
    $build['notice']=['#markup'=>count($positions)>=250?'<p>'.$this->t('De eerste 250 resultaten worden getoond. Verfijn de filters voor een kleinere glasstaat.').'</p>':''];return$build;
  }
}
