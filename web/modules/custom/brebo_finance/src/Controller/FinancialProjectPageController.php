<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Renders the BREBO Office financial project detail shell. */
final class FinancialProjectPageController extends ControllerBase {
  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('entity_type.manager')); }
  public function page(int $project_nid): array {
    $project=$this->entityTypeManager->getStorage('node')->load($project_nid); if($project===NULL||$project->bundle()!=='brebo_project') throw new NotFoundHttpException('BREBO project does not exist.'); if(!$project->access('view',$this->currentUser())) throw new AccessDeniedHttpException('No access to this BREBO project.');
    return ['#type'=>'container','#attributes'=>['id'=>'brebo-finance-project-detail','class'=>['brebo-finance-project-detail'],'data-project-nid'=>(string)$project_nid,'data-api-url'=>'/brebo-office/api/finance/projects/'.$project_nid.'/cockpit','data-gates-url'=>'/brebo-office/api/finance/projects/'.$project_nid.'/phase-gates','data-decisions-url'=>'/brebo-office/api/finance/decision-inbox?project_nid='.$project_nid,'data-ledger-url'=>'/brebo-office/api/finance/projects/'.$project_nid.'/ledger','data-building-scope-url'=>'/brebo-office/api/projects/'.$project_nid.'/buildings','data-performance-url'=>'/brebo-office/api/finance/performance-receipts','data-can-submit-performance'=>$this->currentUser()->hasPermission('manage brebo procurement')?'1':'0','data-can-verify-performance'=>$this->currentUser()->hasPermission('approve brebo finance')?'1':'0'],'header'=>['#markup'=>'<header class="bfpd-header"><div><span class="bfpd-kicker">BREBO OFFICE · FINANCE · PROJECT</span><h1>'.htmlspecialchars((string)$project->label(),ENT_QUOTES,'UTF-8').'</h1><p>Project #'.$project_nid.' · financiële positie, risico en vrijgave.</p></div><a href="/brebo-office/finance">← Command Center</a></header>'],'content'=>['#markup'=>'<div data-bfpd-content><div class="bfpd-loading">Projectfinanciën laden…</div></div>'],'#attached'=>['library'=>['brebo_finance/project_finance_detail']],'#cache'=>['max-age'=>0]];
  }
}
