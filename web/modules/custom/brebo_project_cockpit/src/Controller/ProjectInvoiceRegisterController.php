<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\brebo_project_cockpit\Form\ProjectInvoiceFilterForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Unified project invoice register with filters and sorting. */
final class ProjectInvoiceRegisterController extends ControllerBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $this->t('Facturen — @project', ['@project' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $request = \Drupal::request();
    $direction = (string) $request->query->get('direction', 'all');
    $state = (string) $request->query->get('state', 'all');
    $sort = (string) $request->query->get('sort', 'date_desc');
    if (!in_array($direction, ['all', 'incoming', 'outgoing'], TRUE)) $direction = 'all';
    if (!in_array($state, ['all', 'open', 'overdue', 'paid', 'partial', 'disputed'], TRUE)) $state = 'all';
    if (!in_array($sort, ['date_desc', 'date_asc', 'due_asc', 'amount_desc', 'relation_asc'], TRUE)) $sort = 'date_desc';

    $today = date('Y-m-d');
    $rows = [];
    $counts = ['incoming' => 0, 'outgoing' => 0, 'open' => 0, 'overdue' => 0, 'paid' => 0, 'partial' => 0, 'disputed' => 0];

    foreach ($this->purchaseInvoices($projectId) as $invoice) {
      $invoiceState = $this->purchaseState($invoice, $today);
      $counts['incoming']++;
      $counts[$invoiceState] = ($counts[$invoiceState] ?? 0) + 1;
      $rows[] = [
        'direction' => 'incoming',
        'direction_label' => 'Inkoopfactuur',
        'relation' => (string) ($invoice['supplier_name'] ?? '—'),
        'number' => (string) ($invoice['invoice_number'] ?? '—'),
        'date' => (string) ($invoice['invoice_date'] ?? ''),
        'due' => (string) ($invoice['due_date'] ?? ''),
        'state' => $invoiceState,
        'state_label' => $this->stateLabel($invoiceState),
        'gross' => (float) ($invoice['amount_inc_vat'] ?? 0),
        'paid' => NULL,
        'open' => NULL,
        'control' => trim((string) ($invoice['match_status'] ?? '')) ?: '—',
      ];
    }

    foreach ($this->salesInvoices($projectId) as $invoice) {
      $gross = (float) ($invoice['amount_inc_vat'] ?? 0);
      $paid = (float) ($invoice['paid_amount_inc_vat'] ?? 0);
      $open = max(0.0, $gross - $paid);
      $invoiceState = $this->salesState($invoice, $today, $gross, $paid, $open);
      $counts['outgoing']++;
      $counts[$invoiceState] = ($counts[$invoiceState] ?? 0) + 1;
      $rows[] = [
        'direction' => 'outgoing',
        'direction_label' => 'Verkoopfactuur',
        'relation' => (string) ($invoice['customer_name'] ?? $invoice['customer_ref'] ?? 'Opdrachtgever'),
        'number' => (string) ($invoice['invoice_number'] ?? '—'),
        'date' => (string) ($invoice['invoice_date'] ?? ''),
        'due' => (string) ($invoice['due_date'] ?? ''),
        'state' => $invoiceState,
        'state_label' => $this->stateLabel($invoiceState),
        'gross' => $gross,
        'paid' => $paid,
        'open' => $open,
        'control' => trim((string) ($invoice['dispute_reason'] ?? '')) !== '' ? 'In geschil' : '—',
      ];
    }

    $rows = array_values(array_filter($rows, static function (array $row) use ($direction, $state): bool {
      if ($direction !== 'all' && $row['direction'] !== $direction) return FALSE;
      if ($state !== 'all' && $row['state'] !== $state) return FALSE;
      return TRUE;
    }));
    $this->sortRows($rows, $sort);

    $tableRows = array_map(function (array $row): array {
      return [
        $row['direction_label'],
        $row['relation'],
        $row['number'],
        $row['date'] !== '' ? $row['date'] : '—',
        $row['due'] !== '' ? $row['due'] : '—',
        $row['state_label'],
        $this->money($row['gross']),
        $row['paid'] === NULL ? '—' : $this->money($row['paid']),
        $row['open'] === NULL ? '—' : $this->money($row['open']),
        $row['control'],
      ];
    }, $rows);

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'new_sales' => [
          '#type' => 'link',
          '#title' => $this->t('Verkoopfactuur aanmaken'),
          '#url' => Url::fromRoute('brebo_project_cockpit.sales_invoice_draft_add', ['node' => $projectId]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'sales_management' => [
          '#type' => 'link',
          '#title' => $this->t('Termijnfacturatie'),
          '#url' => Url::fromRoute('brebo_project_cockpit.sales_invoices', ['node' => $projectId]),
          '#attributes' => ['class' => ['button']],
        ],
        'finance' => [
          '#type' => 'link',
          '#title' => $this->t('Projectadministratie'),
          '#url' => Url::fromRoute('brebo_finance.project_finance_page', ['project_nid' => $projectId]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-procurement-kpis']],
        'incoming' => ['#markup' => $this->countKpi('Inkoopfacturen', $counts['incoming'])],
        'outgoing' => ['#markup' => $this->countKpi('Verkoopfacturen', $counts['outgoing'])],
        'open' => ['#markup' => $this->countKpi('Openstaand', $counts['open'])],
        'overdue' => ['#markup' => $this->countKpi('Vervallen', $counts['overdue'])],
        'paid' => ['#markup' => $this->countKpi('Betaald', $counts['paid'])],
      ],
      'filters' => $this->formBuilder()->getForm(ProjectInvoiceFilterForm::class, $node, $direction, $state, $sort),
      'register' => [
        '#type' => 'table',
        '#header' => [$this->t('Soort'), $this->t('Relatie'), $this->t('Factuurnummer'), $this->t('Factuurdatum'), $this->t('Vervaldatum'), $this->t('Status'), $this->t('Bedrag incl. btw'), $this->t('Betaald'), $this->t('Openstaand'), $this->t('Controle')],
        '#rows' => $tableRows,
        '#empty' => $this->t('Geen facturen gevonden voor de gekozen filters.'),
        '#sticky' => TRUE,
      ],
      'explanation' => [
        '#type' => 'details',
        '#title' => $this->t('Toelichting'),
        '#open' => FALSE,
        'text' => ['#markup' => '<p>' . $this->t('Dit register toont alle inkoop- en verkoopfacturen van het project. Een factuur kan ook zonder gekoppelde order of contract in Finance worden verwerkt en gefiatteerd.') . '</p>'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args:direction', 'url.query_args:state', 'url.query_args:sort'],
        'tags' => ['node:' . $projectId],
        'max-age' => 0,
      ],
    ];
  }

  /** @return array<int, array<string, mixed>> */
  private function purchaseInvoices(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_purchase_invoice')) return [];
    return $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i')->condition('project_nid', $projectId)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  private function salesInvoices(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice')) return [];
    return $this->database->select('brebo_finance_sales_invoice', 'i')->fields('i')->condition('project_nid', $projectId)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function purchaseState(array $row, string $today): string {
    $status = mb_strtolower(trim((string) ($row['status'] ?? '')));
    if (in_array($status, ['paid', 'betaald', 'completed', 'closed'], TRUE)) return 'paid';
    if (in_array($status, ['disputed', 'geschil'], TRUE)) return 'disputed';
    $due = (string) ($row['due_date'] ?? '');
    return $due !== '' && $due < $today ? 'overdue' : 'open';
  }

  private function salesState(array $row, string $today, float $gross, float $paid, float $open): string {
    $status = mb_strtolower(trim((string) ($row['status'] ?? '')));
    if ($open <= 0.005 && $gross > 0) return 'paid';
    if (trim((string) ($row['dispute_reason'] ?? '')) !== '' || $status === 'disputed') return 'disputed';
    if ($paid > 0 && $open > 0) return 'partial';
    $due = (string) ($row['due_date'] ?? '');
    return $due !== '' && $due < $today ? 'overdue' : 'open';
  }

  private function stateLabel(string $state): string {
    return match ($state) {
      'paid' => (string) $this->t('Betaald'),
      'partial' => (string) $this->t('Deels betaald'),
      'overdue' => (string) $this->t('Vervallen'),
      'disputed' => (string) $this->t('In geschil'),
      default => (string) $this->t('Openstaand'),
    };
  }

  /** @param array<int, array<string, mixed>> $rows */
  private function sortRows(array &$rows, string $sort): void {
    usort($rows, static function (array $a, array $b) use ($sort): int {
      return match ($sort) {
        'date_asc' => strcmp((string) $a['date'], (string) $b['date']),
        'due_asc' => strcmp((string) $a['due'], (string) $b['due']),
        'amount_desc' => ((float) $b['gross']) <=> ((float) $a['gross']),
        'relation_asc' => strcasecmp((string) $a['relation'], (string) $b['relation']),
        default => strcmp((string) $b['date'], (string) $a['date']),
      };
    });
  }

  private function countKpi(string $label, int $value): string {
    return '<div class="brebo-procurement-kpi"><strong>' . $value . '</strong><span>' . $label . '</span></div>';
  }

  private function money(float $value): string {
    return '€ ' . number_format($value, 2, ',', '.');
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') throw new NotFoundHttpException('BREBO project does not exist.');
  }

}
