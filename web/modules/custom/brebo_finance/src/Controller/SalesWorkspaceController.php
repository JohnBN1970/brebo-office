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
    if ($this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      $query = $this->database->select('brebo_finance_sales_invoice', 'i')
        ->fields('i', ['invoice_number', 'project_nid', 'invoice_date', 'due_date', 'status', 'amount_inc_vat', 'paid_amount_inc_vat'])
        ->orderBy('due_date', 'ASC')
        ->range(0, 100);
      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $invoice) {
        $rows[] = [
          $invoice['invoice_number'], (string) $invoice['project_nid'], $invoice['invoice_date'], $invoice['due_date'], $invoice['status'],
          '€ ' . number_format((float) $invoice['amount_inc_vat'], 2, ',', '.'),
          '€ ' . number_format(max(0.0, (float) $invoice['amount_inc_vat'] - (float) $invoice['paid_amount_inc_vat']), 2, ',', '.'),
        ];
      }
    }
    return [
      '#type' => 'container',
      'header' => ['#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Verkoop</h1><p>Verkoopfacturen, debiteuren en ontvangsten.</p></div><div class="bfcc-live">LIVE CONTROL</div></header>'],
      'invoices' => ['#type' => 'table', '#header' => [$this->t('Factuur'), $this->t('Project'), $this->t('Factuurdatum'), $this->t('Vervaldatum'), $this->t('Status'), $this->t('Bedrag'), $this->t('Openstaand')], '#rows' => $rows, '#empty' => $this->t('Nog geen verkoopfacturen beschikbaar.')],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['max-age' => 0],
    ];
  }

}
