<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Controller;

use Drupal\brebo_glass\Form\GlassPositionFilterForm;
use Drupal\brebo_glass\Service\GlassApprovalPolicy;
use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Displays the operational glass schedule. */
final class GlassPositionOverviewController extends ControllerBase {
  private const APPLICATION_LABELS=['standard'=>'Standaard','door'=>'Deur','adjacent_door'=>'Naast deur','low_level'=>'Laag bij vloer','wet_area'=>'Natte ruimte','ceiling'=>'Plafond','overhead'=>'Boven personen','fall_protection'=>'Doorvalbeveiliging','fire_separation'=>'Brandscheiding'];
  private const CHECK_LABELS=['pending'=>'Nog niet gecontroleerd','passed'=>'Voorcontrole akkoord','expert_review'=>'Deskundige beoordeling','blocked'=>'Geblokkeerd'];
  private const STATUS_LABELS=['concept'=>'Concept','measured'=>'Ingemeten','approved'=>'Technisch vrijgegeven','ordered'=>'Besteld','delivered'=>'Geleverd','installed'=>'Gemonteerd'];

  public function __construct(private readonly GlassPositionRepository $repository,private readonly EntityTypeManagerInterface $entityTypeManager,private readonly RequestStack $requestStack,private readonly GlassApprovalPolicy $approvalPolicy) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('brebo_glass.position_repository'),$container->get('entity_type.manager'),$container->get('request_stack'),$container->get('brebo_glass.approval_policy')); }

  public function overview(): array {
    $request=$this->requestStack->getCurrentRequest(); $search=trim((string)$request->query->get('q','')); $status=(string)$request->query->get('status','');
    if ($status!==''&&!isset(self::STATUS_LABELS[$status])) $status='';
    $sort=(string)$request->query->get('sort','changed'); $direction=strtolower((string)$request->query->get('direction','desc'))==='asc'?'asc':'desc';
    $positions=$this->repository->findAll($search,$status,$sort,$direction); $counts=$this->repository->countByStatus();
    $storage=$this->entityTypeManager->getStorage('node'); $nodeIds=[]; foreach($positions as $p){$nodeIds[]=(int)$p['building_nid'];if(!empty($p['project_nid']))$nodeIds[]=(int)$p['project_nid'];} $nodes=$storage->loadMultiple(array_unique($nodeIds));
    $user=$this->currentUser(); $canApprove=$user->hasPermission('approve brebo glass positions'); $canExport=$user->hasPermission('export brebo glass to calculation'); $canProcure=$user->hasPermission('create brebo procurement requests'); $canComplete=$user->hasPermission('complete brebo glass positions');
    $rows=[];
    foreach($positions as $p){
      $id=(int)$p['id'];$buildingId=(int)$p['building_nid'];$projectId=(int)($p['project_nid']??0);$state=(string)$p['technical_status'];$policy=$this->approvalPolicy->evaluate($p);
      $approval=$canApprove&&$policy['allowed']?Link::createFromRoute($this->t('Vrijgeven'),'brebo_glass.position_approve',['position_id'=>$id]):'-';
      $calculation=$canExport&&$state==='approved'?Link::createFromRoute($this->t('Calculatie'),'brebo_glass.position_to_calculation',['position_id'=>$id]):'-';
      $procurement=$canProcure&&$state==='approved'?Link::createFromRoute($this->t('Inkoop'),'brebo_glass.position_to_procurement',['position_id'=>$id]):($state==='ordered'?$this->t('Besteld'):($state==='delivered'?$this->t('Geleverd'):'-'));
      $mounting=$canComplete&&$state==='delivered'?Link::createFromRoute($this->t('Monteren / gereed'),'brebo_glass.position_complete',['position_id'=>$id]):($state==='installed'?$this->t('Gereed'):'-');
      $signal=match($state){'concept'=>$this->t('Opname afronden'),'measured'=>$this->t('Technisch controleren'),'approved'=>$this->t('Inkoop gereedzetten'),'ordered'=>$this->t('Levering bewaken'),'delivered'=>$this->t('Klaar voor montage'),'installed'=>$this->t('Afgerond'),default=>'-'};
      $rows[]=[
        'building'=>isset($nodes[$buildingId])?Link::createFromRoute($nodes[$buildingId]->label(),'entity.node.canonical',['node'=>$buildingId]):$this->t('Gebouw #@id',['@id'=>$buildingId]),
        'project'=>$projectId&&isset($nodes[$projectId])?Link::createFromRoute($nodes[$projectId]->label(),'entity.node.canonical',['node'=>$projectId]):'-','position'=>$p['position_code'],'location'=>$p['location'],
        'application'=>$this->t(self::APPLICATION_LABELS[$p['application_type']]??$p['application_type']),'specification'=>$p['composition'],'dimensions'=>$p['width_mm'].' × '.$p['height_mm'].' mm','quantity'=>$p['quantity'],
        'area'=>number_format((float)$p['area_m2'],3,',','.').' m²','technical_check'=>$this->t(self::CHECK_LABELS[$p['technical_check_state']]??$p['technical_check_state']),'status'=>$this->t(self::STATUS_LABELS[$state]??$state),
        'signal'=>$signal,'approval'=>$approval,'calculation'=>$calculation,'procurement'=>$procurement,'mounting'=>$mounting,
      ];
    }
    $build['summary']=['#theme'=>'item_list','#title'=>$this->t('Operationele glasstatus'),'#items'=>[
      $this->t('Totaal: @n',['@n'=>$counts['all']??0]),$this->t('Ingemeten: @n',['@n'=>$counts['measured']??0]),$this->t('Vrijgegeven: @n',['@n'=>$counts['approved']??0]),$this->t('Besteld: @n',['@n'=>$counts['ordered']??0]),$this->t('Geleverd: @n',['@n'=>$counts['delivered']??0]),$this->t('Gemonteerd: @n',['@n'=>$counts['installed']??0]),
    ]];
    $build['filters']=$this->formBuilder()->getForm(GlassPositionFilterForm::class);
    $build['actions']=['#type'=>'actions'];
    $build['actions']['new']=['#type'=>'link','#title'=>$this->t('Nieuwe glasopname'),'#url'=>Url::fromRoute('brebo_glass.position_add'),'#attributes'=>['class'=>['button','button--primary']]];
    if($canProcure)$build['actions']['bundle']=['#type'=>'link','#title'=>$this->t('Glas bundelen voor inkoop'),'#url'=>Url::fromRoute('brebo_glass.procurement_bundle'),'#attributes'=>['class'=>['button']]];
    if($user->hasPermission('view brebo procurement'))$build['actions']['orders']=['#type'=>'link','#title'=>$this->t('Leveringen bewaken'),'#url'=>Url::fromRoute('brebo_procurement.order_overview'),'#attributes'=>['class'=>['button']]];
    $build['table']=['#type'=>'table','#header'=>[$this->t('Gebouw'),$this->t('Project'),$this->t('Positie'),$this->t('Locatie'),$this->t('Toepassing'),$this->t('Opbouw'),$this->t('Bestelmaat'),$this->t('Aantal'),$this->t('Oppervlak'),$this->t('Technische controle'),$this->t('Status'),$this->t('Volgende stap'),$this->t('Vrijgave'),$this->t('Calculatie'),$this->t('Inkoop'),$this->t('Montage')],'#rows'=>$rows,'#empty'=>$this->t('Geen glasposities gevonden.'),'#sticky'=>TRUE];
    $build['notice']=['#markup'=>count($positions)>=250?'<p>'.$this->t('De eerste 250 resultaten worden getoond. Verfijn de filters voor een kleinere glasstaat.').'</p>':'']; return $build;
  }
}
