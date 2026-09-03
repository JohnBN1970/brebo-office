<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\brebo_finance\Service\PurchaseInvoiceControlViewBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Presents organisation-wide purchase invoices in BREBO Finance. */
final class PurchaseInvoiceController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $financeEntityTypeManager,
    private readonly PurchaseInvoiceControlViewBuilder $controlViewBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('brebo_finance.purchase_invoice_control_view_builder'),
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

    $control = $this->controlViewBuilder->build($invoice_id);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-finance-purchase-invoice']],
      'header' => [
        '#markup' => '<header class="bfcc-header"><div><span class="bfcc-kicker">BREBO OFFICE · FINANCE · INKOOPFACTUREN</span><h1>' . $this->t('Inkoopfactuur @number', ['@number' => (string) ($invoice['invoice_number'] ?? '#' . $invoice_id)]) . '</h1><p>Intake, codering en volledige financiële controlestatus in één werkblad.</p></div></header>',
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
      'control_view' => $this->controlView($control, $invoice_id),
      'coding_workbench' => [
        '#type' => 'container',
        '#attributes' => [
          'data-brebo-invoice-coding' => '',
          'data-invoice-id' => (string) $invoice_id,
          'data-api-url' => '/brebo-office/api/finance/purchase-invoices/' . $invoice_id . '/coding',
          'data-can-manage' => $this->currentUser()->hasPermission('manage brebo procurement') ? '1' : '0',
          'class' => ['brebo-finance-purchase-invoice-coding'],
        ],
        'loading' => ['#markup' => '<p>Codeerwerkbank laden…</p>'],
      ],
      '#attached' => ['library' => ['brebo_finance/command_center', 'brebo_finance/purchase_invoice_coding']],
      '#cache' => ['max-age' => 0],
    ];
  }

  /** @return array<string,mixed> */
  private function controlView(array $control, int $invoiceId): array {
    if (!($control['available'] ?? FALSE)) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['bfcc-section']],
        'title' => ['#markup' => '<h2>Controlestatus</h2>'],
        'message' => ['#markup' => '<p>Controlestatus is tijdelijk niet beschikbaar tijdens deze deployment-overgang.</p>'],
      ];
    }

    $summary = $control['summary'] ?? [];
    $lineRows = [];
    foreach ($control['lines'] ?? [] as $line) {
      $blocker = $line['blocker'] ?? [];
      $priority = $blocker['priority']['level'] ?? '';
      $verified = $blocker['verified_performance_ex_vat'] ?? 0;
      $shortfall = $blocker['verified_shortfall_ex_vat'] ?? 0;
      $commitment = trim((string) ($line['commitment_header_commitment_number'] ?? ''));
      if ($commitment === '') {
        $commitment = 'Niet gekoppeld';
      }
      $lineRows[] = [
        (string) ($line['line_number'] ?? ''),
        (string) ($line['description'] ?? ''),
        $this->money($line['amount_ex_vat'] ?? 0),
        $commitment,
        (string) ($line['match_status'] ?? 'unmatched'),
        (string) ($line['variance_code'] ?? ''),
        $this->money($verified),
        $this->money($shortfall),
        (bool) ($blocker['blocked'] ?? FALSE) ? ('Geblokkeerd' . ($priority !== '' ? ' · ' . $priority : '')) : 'Vrij',
      ];
    }

    $g = $control['g_account'] ?? NULL;
    $gRows = $g ? [
      ['Status', (string) ($g['status'] ?? '')],
      ['Tegenpartij', (string) ($g['counterparty_name'] ?? '')],
      ['Reguliere rekening', $this->money($g['regular_account_amount'] ?? 0)],
      ['G-rekening', $this->money($g['g_account_amount'] ?? 0)],
      ['Afspraak', (string) ($g['agreement_ref'] ?? '')],
    ] : [['Status', 'Geen G-rekeningsinstructie gekoppeld']];

    $release = $control['payment_release'] ?? NULL;
    $releaseRows = $release ? [
      ['Vrijgavenummer', (string) ($release['release_number'] ?? '')],
      ['Status', (string) ($release['status'] ?? '')],
      ['Reguliere rekening', $this->money($release['regular_account_amount'] ?? 0)],
      ['G-rekening', $this->money($release['g_account_amount'] ?? 0)],
      ['Totaal', $this->money($release['total_amount'] ?? 0)],
      ['Aangevraagd door', (string) ($release['requested_by'] ?? '')],
      ['Goedgekeurd door', (string) ($release['approved_by'] ?? '')],
      ['Uitgevoerd door', (string) ($release['executed_by'] ?? '')],
    ] : [['Status', 'Nog geen betaalvrijgave aangemaakt']];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bfcc-section', 'brebo-finance-invoice-control']],
      'title' => ['#markup' => '<h2>Controlestatus</h2><p>Read-only beeld van order, prestatie, matching, G-rekening en betaalvrijgave.</p>'],
      'summary' => [
        '#type' => 'table',
        '#header' => ['Controle', 'Waarde'],
        '#rows' => [
          ['Factuurregels', (string) ($summary['line_count'] ?? 0)],
          ['Niet gematchte regels', (string) ($summary['unmatched_lines'] ?? 0)],
          ['Geblokkeerde regels', (string) ($summary['blocked_lines'] ?? 0)],
          ['Regeltotaal excl. btw', $this->money($summary['line_amount_ex_vat'] ?? 0)],
          ['Factuurkop excl. btw', $this->money($summary['header_amount_ex_vat'] ?? 0)],
          ['Verschil regels/kop', $this->money($summary['line_header_difference_ex_vat'] ?? 0)],
        ],
      ],
      'lines_title' => ['#markup' => '<h3>Factuurregels · order · prestatie · match</h3>'],
      'lines' => [
        '#type' => 'table',
        '#header' => ['Regel', 'Omschrijving', 'Factuur excl. btw', 'Commitment', 'Match', 'Afwijking', 'Verified performance', 'Tekort', 'Blokkade'],
        '#rows' => $lineRows,
        '#empty' => 'Nog geen factuurregels vastgelegd.',
      ],
      'g_title' => ['#markup' => '<h3>G-rekening</h3>'],
      'g_account' => ['#type' => 'table', '#header' => ['Gegeven', 'Waarde'], '#rows' => $gRows],
      'release_title' => ['#markup' => '<h3>Betaalvrijgave</h3>'],
      'payment_release' => ['#type' => 'table', '#header' => ['Gegeven', 'Waarde'], '#rows' => $releaseRows],
      'trace' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('Open financiële eurotrace'), Url::fromRoute('brebo_finance.euro_trace', ['entity_type' => 'purchase_invoice', 'entity_id' => $invoiceId]))->toString() . '</p>',
      ],
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
