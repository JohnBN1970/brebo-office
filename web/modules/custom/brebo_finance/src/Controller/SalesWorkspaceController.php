<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Organisation-wide sales and receivables workspace. */
final class SalesWorkspaceController extends ControllerBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function page(): array {
    $rows = [];
    $total = '0.00';
    $paid = '0.00';
    $open = '0.00';

    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      $query = $this->database->select('brebo_finance_sales_invoice', 'i')
        ->fields('i', ['invoice_number', 'project_nid', 'invoice_date', 'due_date', 'status', 'amount_inc_vat', 'paid_amount_inc_vat'])
        ->orderBy('due_date', 'ASC')
        ->range(0, 100);

      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
        $amount = (string) ($invoice['amount_inc_vat'] ?? '0');
        $paidAmount = (string) ($invoice['paid_amount_inc_vat'] ?? '0');
        $outstanding = bcsub($amount, $paidAmount, 2);
        $total = bcadd($total, $amount, 2);
        $paid = bcadd($paid, $paidAmount, 2);
        $open = bcadd($open, $outstanding, 2);
        $rows[] = [
          $invoice['invoice_number'],
          (string) $invoice['project_nid'],
          $invoice['invoice_date'],
          $invoice['due_date'],
          $invoice['status'],
          '€ ' . number_format((float) $amount, 2, ',', '.'),
          '€ ' . number_format((float) $outstanding, 2, ',', '.'),
        ];
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-sales-workspace']],
      'header' => ['#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Verkoop</h1><p>Verkoopfacturen, debiteuren en ontvangsten vanuit de operationele BREBO-administratie.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>'],
      'summary' => [
        '#markup' => '<div class="bfcc-grid"><article class="bfcc-card"><span>Gefactureerd</span><strong>€ ' . number_format((float) $total, 2, ',', '.') . '</strong></article><article class="bfcc-card"><span>Ontvangen</span><strong>€ ' . number_format((float) $paid, 2, ',', '.') . '</strong></article><article class="bfcc-card"><span>Openstaand</span><strong>€ ' . number_format((float) $open, 2, ',', '.') . '</strong></article></div>',
      ],
      'invoices' => [
        '#type' => 'table',
        '#header' => [$this->t('Factuur'), $this->t('Project'), $this->t('Factuurdatum'), $this->t('Vervaldatum'), $this->t('Status'), $this->t('Bedrag'), $this->t('Openstaand')],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen verkoopfacturen beschikbaar.'),
      ],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['max-age' => 0],
    ];
  }

}
