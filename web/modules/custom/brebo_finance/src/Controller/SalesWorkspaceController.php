<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\ReceivablesDunningManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Organisation-wide sales and receivables workspace. */
final class SalesWorkspaceController extends ControllerBase {
  private readonly ReceivablesDunningManager $dunningManager;
  public function __construct(private readonly Connection $database, KeyValueFactoryInterface $keyValueFactory, ConfigFactoryInterface $configFactory) { $this->dunningManager = new ReceivablesDunningManager($database, $keyValueFactory, $configFactory); }
  public static function create(ContainerInterface $container): static { return new static($container->get('database'), $container->get('keyvalue'), $container->get('config.factory')); }

  public function page(): array {
    $rows = [];
    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice_draft')) {
      $drafts = $this->database->select('brebo_finance_sales_invoice_draft', 'd')->fields('d', ['id','draft_number','project_nid','invoice_date','due_date','status','amount_inc_vat'])->orderBy('created','DESC')->range(0,50)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($drafts as $draft) {
        $draftId=(int)$draft['id']; $projectId=(int)$draft['project_nid'];
        $projectLink=$projectId>0?['data'=>['#type'=>'link','#title'=>(string)$projectId,'#url'=>Url::fromRoute('brebo_project_cockpit.invoices',['node'=>$projectId])]]:$this->t('Los');
        $editable=$projectId===0&&(string)$draft['status']==='draft';
        $draftCell=$editable?['data'=>['#type'=>'link','#title'=>(string)$draft['draft_number'],'#url'=>Url::fromRoute('brebo_finance.sales_standalone_edit',['draft'=>$draftId])]]:(string)$draft['draft_number'];
        $action='—'; if($editable)$action=['data'=>['#type'=>'container','review'=>['#type'=>'link','#title'=>$this->t('Ter beoordeling'),'#url'=>Url::fromRoute('brebo_finance.sales_standalone_review',['draft'=>$draftId])],'separator'=>['#markup'=>' · '],'release'=>['#type'=>'link','#title'=>$this->t('Vrijgeven & verzenden'),'#url'=>Url::fromRoute('brebo_finance.sales_standalone_release',['draft'=>$draftId])]]];
        $rows[]=[$draftCell,$projectLink,$draft['invoice_date'],$draft['due_date'],$draft['status']==='draft'?$this->t('Concept'):$draft['status'],'€ '.number_format((float)$draft['amount_inc_vat'],2,',','.'),'—',$action];
      }
    }
    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      $invoices=$this->database->select('brebo_finance_sales_invoice','i')->fields('i',['id','invoice_number','project_nid','invoice_date','due_date','status','amount_inc_vat','paid_amount_inc_vat'])->orderBy('due_date','ASC')->range(0,50)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach($invoices as $invoice){$id=(int)$invoice['id'];$projectId=(int)$invoice['project_nid'];$state=$this->dunningManager->state($id);$next=$this->dunningManager->nextStep($id);$project=$projectId>0?['data'=>['#type'=>'link','#title'=>(string)$projectId,'#url'=>Url::fromRoute('brebo_project_cockpit.invoices',['node'=>$projectId])]]:$this->t('Los');$rows[]=[(string)$invoice['invoice_number'],$project,$invoice['invoice_date'],$invoice['due_date'],$this->statusLabel((string)$state['status']),'€ '.number_format((float)$invoice['amount_inc_vat'],2,',','.'),'€ '.number_format((float)$state['outstanding_amount_inc_vat'],2,',','.'),['data'=>['#type'=>'link','#title'=>$this->actionLabel($state,$next),'#url'=>Url::fromRoute('brebo_finance.receivables_action',['invoice'=>$id])]]];}
    }
    return ['#type'=>'container','#attributes'=>['class'=>['brebo-finance-sales-workspace']],
      'header'=>['#markup'=>'<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Verkoop</h1><p>Conceptfacturen, verzending, openstaande posten en debiteurenbewaking.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>'],
      'actions'=>['#type'=>'container','#attributes'=>['class'=>['bfcc-actions']],
        'standalone'=>['#type'=>'link','#title'=>$this->t('+ Nieuwe losse factuur'),'#url'=>Url::fromRoute('brebo_finance.sales_standalone_start'),'#attributes'=>['class'=>['button','button--primary']]],
        'bulk'=>['#type'=>'link','#title'=>$this->t('Debiteuren bulkwerkbak'),'#url'=>Url::fromRoute('brebo_finance.receivables_bulk'),'#attributes'=>['class'=>['button']],'#access'=>$this->currentUser()->hasPermission('manage brebo finance')],
        'cashflow'=>['#type'=>'link','#title'=>$this->t('Cashflow & management'),'#url'=>Url::fromRoute('brebo_finance.cashflow_management'),'#attributes'=>['class'=>['button']],'#access'=>$this->currentUser()->hasPermission('approve brebo finance')],
        'settings'=>['#type'=>'link','#title'=>$this->t('Verkoopinstellingen'),'#url'=>Url::fromRoute('brebo_finance.sales_settings'),'#attributes'=>['class'=>['button']],'#access'=>$this->currentUser()->hasPermission('approve brebo finance')]],
      'explanation'=>['#markup'=>'<p><strong>Office bewaakt én bedient de debiteurenstatus.</strong> Individueel én via de bulkwerkbak. Iedere factuur wordt vlak vóór uitvoering opnieuw gecontroleerd; betaald, betwist, regeling of hold blokkeert escalatie.</p>'],
      'invoices'=>['#type'=>'table','#header'=>[$this->t('Factuur'),$this->t('Project'),$this->t('Factuurdatum'),$this->t('Vervaldatum'),$this->t('Debiteurenstatus'),$this->t('Bedrag'),$this->t('Openstaand'),$this->t('Actie')],'#rows'=>$rows,'#empty'=>$this->t('Nog geen verkoopfacturen beschikbaar.')],
      '#attached'=>['library'=>['brebo_finance/command_center']],'#cache'=>['contexts'=>['user.permissions'],'max-age'=>0]];
  }
  private function statusLabel(string $s):string{return match($s){'open'=>'Open','vervallen'=>'Vervallen','herinnerd'=>'Herinnerd','aangemaand'=>'Aangemaand','laatste_sommatie'=>'Laatste sommatie','gereed_voor_incasso'=>'Gereed voor incasso','regeling'=>'Betalingsregeling','betwist'=>'Betwist','hold'=>'Hold','betaald'=>'Betaald','gecrediteerd'=>'Gecrediteerd','gesloten'=>'Gesloten',default=>$s};}
  private function actionLabel(array $s,?string $n):string{if($s['blocked_reason']!==NULL)return match($s['blocked_reason']){'disputed'=>'Bekijken · betwist','payment_arrangement'=>'Regeling beheren','manual_hold'=>'Hold beheren',default=>'Debiteurenactie'};return match($n){'reminder'=>'Herinnering verzenden','demand'=>'Aanmaning verzenden','final_notice'=>'Laatste sommatie verzenden','collection_ready'=>'Gereed voor incasso',default=>'Debiteurenactie'};}
}
