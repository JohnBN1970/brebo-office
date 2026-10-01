<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\brebo_finance\Contract\ProjectReferenceGatewayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Renders the BREBO Office financial project detail shell. */
final class FinancialProjectPageController extends ControllerBase {
  public function __construct(private readonly ProjectReferenceGatewayInterface $projects) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('brebo_finance.project_reference_gateway')); }
  public function page(int $project_nid): array {
    if(!$this->projects->exists($project_nid)) throw new NotFoundHttpException('BREBO project does not exist.'); if(!$this->projects->canView($project_nid)) throw new AccessDeniedHttpException('No access to this BREBO project.'); $projectLabel=$this->projects->label($project_nid) ?? ('Project #'.$project_nid);
    return ['#type'=>'container','#attributes'=>['id'=>'brebo-finance-project-detail','class'=>['brebo-finance-project-detail'],'data-project-nid'=>(string)$project_nid,'data-api-url'=>'/brebo-office/api/finance/projects/'.$project_nid.'/cockpit','data-gates-url'=>'/brebo-office/api/finance/projects/'.$project_nid.'/phase-gates','data-decisions-url'=>'/brebo-office/api/finance/decision-inbox?project_nid='.$project_nid,'data-ledger-url'=>'/brebo-office/api/finance/projects/'.$project_nid.'/ledger','data-building-scope-url'=>'/brebo-office/api/projects/'.$project_nid.'/buildings','data-performance-url'=>'/brebo-office/api/finance/performance-receipts','data-closure-url'=>'/brebo-office/api/finance/projects/'.$project_nid.'/closure','data-can-submit-performance'=>$this->currentUser()->hasPermission('manage brebo procurement')?'1':'0','data-can-verify-performance'=>$this->currentUser()->hasPermission('approve brebo finance')?'1':'0','data-can-close-project'=>$this->currentUser()->hasPermission('approve brebo finance')?'1':'0'],'header'=>['#markup'=>'<header class="bfpd-header"><div><span class="bfpd-kicker">BREBO OFFICE · FINANCE · PROJECT</span><h1>'.htmlspecialchars($projectLabel,ENT_QUOTES,'UTF-8').'</h1><p>Project #'.$project_nid.' · financiële positie, risico en vrijgave.</p></div><a href="/brebo-office/finance">← Command Center</a></header>'],'closure'=>['#markup'=>'<div data-financial-closure><div class="bfpd-loading">Afsluitstatus laden…</div></div>'],'content'=>['#markup'=>'<div data-bfpd-content><div class="bfpd-loading">Projectfinanciën laden…</div></div>'],'#attached'=>['library'=>['brebo_finance/project_finance_detail']],'#cache'=>['max-age'=>0]];
  }
}
