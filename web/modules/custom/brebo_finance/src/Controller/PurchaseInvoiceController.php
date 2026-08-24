<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Presents organisation-wide purchase invoices in BREBO Finance. */
final class PurchaseInvoiceController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $financeEntityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  public function listing(): array {
    $rows = [];
    foreach ($this->loadInvoices() as $invoice) {
      $project = $this->projectLabel((int) ($invoice['project_nid'] ?? 0));
      $rows[] = [
        Link::fromTextAndUrl((string) ($invoice['invoice_number'] ?: '#' . $invoice['id']), Url::fromRoute('brebo_finance.purchase_invoice_view', ['invoice_id' => $invoice['id']])),
        (string) ($invoice['supplier_name'] ?? ''),
        (string) ($invoice['invoice_date'] ?? ''),
        (string) ($invoice['due_date'] ?? ''),
        $this->money($invoice['amount_inc_vat'] ?? 0),
        (string) ($invoice['status'] ?? ''),
        (string) ($invoice['match_status'] ?? ''),
        $project,
        (string) ($invoice['source_system'] ?? $invoice['source'] ?? ''),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-purchase-invoices']],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE</span><h1>Inkoopfacturen</h1><p>Organisatiebrede werkvoorraad van ontvangen leveranciersfacturen.</p></div></header>',
      ],
      'navigation' => $this->navigation(),
      'summary' => [
        '#markup' => '<p><strong>' . count($rows) . '</strong> inkoopfacturen geregistreerd in BREBO Finance.</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Factuur', 'Leverancier', 'Factuurdatum', 'Vervaldatum', 'Bedrag incl. btw', 'Status', 'Match', 'Project', 'Bron'],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen inkoopfacturen geregistreerd. De Finance-database is schoon en gereed voor de Moneybird-import.'),
      ],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function view(int $invoice_id): array {
    $invoice = $this->loadInvoice($invoice_id);
    if ($invoice === NULL) {
      throw new NotFoundHttpException();
    }

    $projectNid = (int) ($invoice['project_nid'] ?? 0);
    $projectLabel = $this->projectLabel($projectNid);
    $items = [
      'Leverancier' => $invoice['supplier_name'] ?? '',
      'Factuurnummer' => $invoice['invoice_number'] ?? '',
      'Factuurdatum' => $invoice['invoice_date'] ?? '',
      'Vervaldatum' => $invoice['due_date'] ?? '',
      'Status' => $invoice['status'] ?? '',
      'Matchstatus' => $invoice['match_status'] ?? '',
      'Bedrag excl. btw' => $this->money($invoice['amount_ex_vat'] ?? 0),
      'Btw' => $this->money($invoice['vat_amount'] ?? 0),
      'Bedrag incl. btw' => $this->money($invoice['amount_inc_vat'] ?? 0),
      'Bron' => $invoice['source_system'] ?? $invoice['source'] ?? '',
      'Extern ID' => $invoice['external_id'] ?? $invoice['moneybird_id'] ?? $invoice['source_record_id'] ?? '',
      'Project' => $projectLabel,
    ];

    $detailRows = [];
    foreach ($items as $label => $value) {
      if ($value === '' || $value === NULL) {
        continue;
      }
      $detailRows[] = [$label, (string) $value];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-purchase-invoice']],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE · INKOOPFACTUREN</span><h1>' . $this->t('Inkoopfactuur @number', ['@number' => (string) ($invoice['invoice_number'] ?? '#' . $invoice_id)]) . '</h1></div></header>',
      ],
      'navigation' => $this->navigation(),
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('← Terug naar Inkoopfacturen'), Url::fromRoute('brebo_finance.purchase_invoice_list'))->toString() . '</p>',
      ],
      'details' => [
        '#type' => 'table',
        '#header' => [$this->t('Gegeven'), $this->t('Waarde')],
        '#rows' => $detailRows,
      ],
      'project_link' => $projectNid > 0 ? [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('Open gekoppeld project'), Url::fromRoute('entity.node.canonical', ['node' => $projectNid]))->toString() . '</p>',
      ] : [],
      '#attached' => ['library' => ['brebo_finance/command_center']],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function navigation(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bfcc-section']],
      'links' => [
        '#theme' => 'item_list',
        '#items' => [
          Link::fromTextAndUrl($this->t('Dashboard'), Url::fromRoute('brebo_finance.command_center_page')),
          Link::fromTextAndUrl($this->t('Inkoopfacturen'), Url::fromRoute('brebo_finance.purchase_invoice_list')),
        ],
        '#attributes' => ['class' => ['bfcc-finance-nav']],
      ],
    ];
  }

  /** @return array<int, array<string, mixed>> */
  private function loadInvoices(): array {
    $table = 'brebo_finance_purchase_invoice';
    $schema = $this->database->schema();
    if (!$schema->tableExists($table)) {
      return [];
    }

    $wanted = ['id', 'project_nid', 'supplier_name', 'invoice_number', 'invoice_date', 'due_date', 'status', 'match_status', 'amount_ex_vat', 'vat_amount', 'amount_inc_vat', 'source_system', 'source', 'source_record_id', 'external_id', 'moneybird_id', 'created', 'changed'];
    $fields = array_values(array_filter($wanted, static fn(string $field): bool => $schema->fieldExists($table, $field)));
    if (!in_array('id', $fields, TRUE)) {
      return [];
    }

    $query = $this->database->select($table, 'i')->fields('i', $fields);
    if ($schema->fieldExists($table, 'invoice_date')) {
      $query->orderBy('invoice_date', 'DESC');
    }
    elseif ($schema->fieldExists($table, 'changed')) {
      $query->orderBy('changed', 'DESC');
    }
    else {
      $query->orderBy('id', 'DESC');
    }

    return array_map(static fn(object $row): array => (array) $row, $query->execute()->fetchAll());
  }

  /** @return array<string, mixed>|null */
  private function loadInvoice(int $invoiceId): ?array {
    foreach ($this->loadInvoices() as $invoice) {
      if ((int) $invoice['id'] === $invoiceId) {
        return $invoice;
      }
    }
    return NULL;
  }

  private function projectLabel(int $projectNid): string {
    if ($projectNid <= 0) {
      return 'Niet gekoppeld';
    }
    $project = $this->financeEntityTypeManager->getStorage('node')->load($projectNid);
    return $project ? (string) $project->label() : 'Project #' . $projectNid;
  }

  private function money(mixed $value): string {
    return '€ ' . number_format((float) $value, 2, ',', '.');
  }
}
