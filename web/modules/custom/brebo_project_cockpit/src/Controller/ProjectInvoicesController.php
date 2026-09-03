<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Shows project billing instalments, changes, provisional sums and invoices. */
final class ProjectInvoicesController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
  ) {}

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

    $contract = $this->loadOne('brebo_finance_project_contract', $projectId);
    $instalments = $this->loadMany('brebo_finance_billing_instalment', $projectId, 'planned_invoice_date');
    $changes = $this->loadMany('brebo_finance_change_order', $projectId, 'changed', 'DESC');
    $provisionalSums = $this->loadMany('brebo_finance_provisional_sum', $projectId, 'changed', 'DESC');
    $drafts = $this->loadMany('brebo_finance_sales_invoice_draft', $projectId, 'changed', 'DESC');
    $outbox = $this->loadMany('brebo_finance_sales_invoice_outbox', $projectId, 'created', 'DESC');
    $invoices = $this->loadMany('brebo_finance_sales_invoice', $projectId, 'invoice_date', 'DESC');

    $contractValue = (float) ($contract['amount_ex_vat'] ?? 0);
    $approvedChanges = 0.0;
    foreach ($changes as $row) {
      if (($row['status'] ?? '') === 'client_approved' || in_array((string) ($row['status'] ?? ''), ['executed', 'invoiced', 'paid'], TRUE)) {
        $amount = (float) ($row['sales_amount_ex_vat'] ?? 0);
        $approvedChanges += (($row['change_type'] ?? '') === 'omission') ? -$amount : $amount;
      }
    }

    $approvedProvisionalSettlement = 0.0;
    $forecastProvisionalSettlement = 0.0;
    foreach ($provisionalSums as $row) {
      $forecastProvisionalSettlement += (float) ($row['settlement_amount_ex_vat'] ?? 0);
      $approvedProvisionalSettlement += (float) ($row['approved_settlement_ex_vat'] ?? 0);
    }
    $currentOrderValue = $contractValue + $approvedChanges + $approvedProvisionalSettlement;

    $invoiced = 0.0;
    $received = 0.0;
    $open = 0.0;
    $overdueOpen = 0.0;
    $disputedOpen = 0.0;
    $today = date('Y-m-d');
    foreach ($invoices as $row) {
      if (!in_array((string) ($row['status'] ?? ''), ['credited', 'cancelled'], TRUE)) {
        $gross = (float) ($row['amount_inc_vat'] ?? 0);
        $paid = (float) ($row['paid_amount_inc_vat'] ?? 0);
        $openAmount = max(0.0, $gross - $paid);
        $invoiced += (float) ($row['amount_ex_vat'] ?? 0);
        $received += $paid;
        $open += $openAmount;
        if ($openAmount > 0 && (string) ($row['due_date'] ?? '') !== '' && (string) $row['due_date'] < $today) {
          $overdueOpen += $openAmount;
        }
        if ($openAmount > 0 && ((string) ($row['status'] ?? '') === 'disputed' || trim((string) ($row['dispute_reason'] ?? '')) !== '')) {
          $disputedOpen += $openAmount;
        }
      }
    }

    $remaining = max(0.0, $currentOrderValue - $invoiced);
    $canApproveBillable = $this->currentUser()->hasPermission('approve brebo finance');

    $instalmentRows = [];
    foreach ($instalments as $row) {
      $status = (string) ($row['status'] ?? '—');
      $action = '—';
      if ($status === 'planned' && $canApproveBillable && !empty($row['id'])) {
        $action = [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Factureerbaar goedkeuren'),
            '#url' => Url::fromRoute('brebo_project_cockpit.instalment_billable_approve', [
              'node' => $projectId,
              'instalment' => (int) $row['id'],
            ]),
            '#attributes' => ['class' => ['button', 'button--small']],
          ],
        ];
      }
      $instalmentRows[] = [
        (string) ($row['instalment_number'] ?? '—'),
        (string) ($row['description'] ?? '—'),
        (string) ($row['planned_invoice_date'] ?? '—'),
        $this->triggerLabel((string) ($row['trigger_type'] ?? '')),
        $status,
        $this->money($row['amount_ex_vat'] ?? NULL),
        $this->instalmentVatLabel($row),
        $action,
      ];
    }

    $changeRows = [];
    foreach ($changes as $row) {
      $changeRows[] = [
        (string) ($row['change_number'] ?? '—'),
        (string) ($row['title'] ?? '—'),
        ($row['change_type'] ?? '') === 'omission' ? $this->t('Minderwerk') : $this->t('Meerwerk'),
        (string) ($row['status'] ?? '—'),
        $this->money($row['sales_amount_ex_vat'] ?? NULL),
        (string) ($row['client_approval_ref'] ?? '—'),
      ];
    }

    $provisionalRows = [];
    foreach ($provisionalSums as $row) {
      $provisionalRows[] = [
        (string) ($row['provisional_sum_number'] ?? '—'),
        (string) ($row['title'] ?? '—'),
        (string) ($row['status'] ?? '—'),
        $this->money($row['contract_amount_ex_vat'] ?? NULL),
        $this->money($row['forecast_amount_ex_vat'] ?? NULL),
        $this->money($row['actual_amount_ex_vat'] ?? NULL),
        $this->money($row['settlement_amount_ex_vat'] ?? NULL),
        $this->money($row['approved_settlement_ex_vat'] ?? NULL),
        $this->money($row['invoiced_settlement_ex_vat'] ?? NULL),
        (string) ($row['client_approval_ref'] ?? '—'),
      ];
    }

    $outboxByDraft = [];
    foreach ($outbox as $row) {
      $draftId = (int) ($row['draft_id'] ?? 0);
      if ($draftId > 0 && !isset($outboxByDraft[$draftId])) {
        $outboxByDraft[$draftId] = $row;
      }
    }

    $draftRows = [];
    foreach ($drafts as $row) {
      $draftId = (int) ($row['id'] ?? 0);
      $queue = $outboxByDraft[$draftId] ?? [];
      $draftStatus = (string) ($row['status'] ?? 'draft');
      $integrationStatus = $queue !== [] ? (string) ($queue['status'] ?? 'queued') : ($draftStatus === 'draft' ? (string) $this->t('Nog niet vrijgegeven') : '—');
      $action = $draftStatus === 'draft'
        ? [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Vrijgeven & verzenden'),
            '#url' => Url::fromRoute('brebo_project_cockpit.sales_invoice_release', ['node' => $projectId, 'draft' => $draftId]),
            '#attributes' => ['class' => ['button', 'button--small']],
          ],
        ]
        : '—';
      $draftRows[] = [
        (string) ($row['draft_number'] ?? '—'),
        (string) ($row['invoice_date'] ?? '—'),
        (string) ($row['due_date'] ?? '—'),
        $draftStatus,
        $integrationStatus,
        $this->money($row['amount_ex_vat'] ?? NULL),
        $this->money($row['vat_amount'] ?? NULL),
        $this->money($row['amount_inc_vat'] ?? NULL),
        $action,
      ];
    }

    $invoiceRows = [];
    foreach ($invoices as $row) {
      $gross = (float) ($row['amount_inc_vat'] ?? 0);
      $paid = (float) ($row['paid_amount_inc_vat'] ?? 0);
      $openAmount = max(0.0, $gross - $paid);
      $invoiceRows[] = [
        (string) ($row['invoice_number'] ?? '—'),
        (string) ($row['invoice_date'] ?? '—'),
        (string) ($row['due_date'] ?? '—'),
        $this->receivableState($row, $today),
        $this->money($row['amount_inc_vat'] ?? NULL),
        $this->money($row['paid_amount_inc_vat'] ?? NULL),
        $this->money($openAmount),
        trim((string) ($row['dispute_reason'] ?? '')) !== '' ? (string) $row['dispute_reason'] : '—',
      ];
    }

    return [
      'principle' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-invoices-principle']],
        'title' => ['#markup' => '<h2>' . $this->t('Opbrengsten en verkoopfacturen') . '</h2>'],
        'text' => ['#markup' => '<p>' . $this->t('Moneybird blijft de boekhoudkundige bron voor verkoopfactuur- en betaalstatus. BREBO Office toont de operationele opvolging, projectimpact en de gecontroleerde volgende actie.') . '</p>'],
      ],
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-procurement-kpis']],
        'order' => ['#markup' => $this->kpi('Actuele opdrachtwaarde', $currentOrderValue, 'excl. btw')],
        'invoiced' => ['#markup' => $this->kpi('Gefactureerd', $invoiced, 'excl. btw')],
        'received' => ['#markup' => $this->kpi('Ontvangen', $received, 'incl. btw')],
        'open' => ['#markup' => $this->kpi('Openstaand', $open, 'incl. btw')],
        'overdue' => ['#markup' => $this->kpi('Vervallen openstaand', $overdueOpen, 'incl. btw')],
        'disputed' => ['#markup' => $this->kpi('In geschil', $disputedOpen, 'open saldo')],
        'remaining' => ['#markup' => $this->kpi('Nog te factureren', $remaining, 'excl. btw')],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'new_invoice' => [
          '#type' => 'link',
          '#title' => $this->t('Nieuwe verkoopfactuur'),
          '#url' => Url::fromRoute('brebo_project_cockpit.sales_invoice_draft_add', ['node' => $projectId]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'add_provisional' => [
          '#type' => 'link',
          '#title' => $this->t('Stelpost toevoegen'),
          '#url' => Url::fromRoute('brebo_project_cockpit.provisional_sum_add', ['node' => $projectId]),
          '#attributes' => ['class' => ['button']],
        ],
        'finance' => [
          '#type' => 'link',
          '#title' => $this->t('Projectfinanciën'),
          '#url' => Url::fromRoute('brebo_finance.project_finance_page', ['project_nid' => $projectId]),
          '#attributes' => ['class' => ['button']],
        ],
        'note' => ['#markup' => '<p><strong>' . $this->t('Bewaking:') . '</strong> ' . $this->t('Een termijn wordt eerst gecontroleerd factureerbaar. Een concept wordt pas financieel definitief nadat Moneybird de officiële factuur heeft aangemaakt en die status terug in Office is gespiegeld.') . '</p>'],
      ],
      'instalments' => [
        '#type' => 'details',
        '#title' => $this->t('Termijnschema (@count)', ['@count' => count($instalmentRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Termijn'), $this->t('Omschrijving'), $this->t('Gepland'), $this->t('Trigger'), $this->t('Status'), $this->t('Excl. btw'), $this->t('BTW'), $this->t('Actie')],
          '#rows' => $instalmentRows,
          '#empty' => $this->t('Voor dit project is nog geen termijnschema geregistreerd.'),
        ],
      ],
      'provisional_sums' => [
        '#type' => 'details',
        '#title' => $this->t('Stelpostbewaking (@count)', ['@count' => count($provisionalRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Nr.'), $this->t('Stelpost'), $this->t('Status'), $this->t('Contract'), $this->t('Prognose'), $this->t('Werkelijk'), $this->t('Verschil'), $this->t('Goedgekeurd'), $this->t('Gefactureerd'), $this->t('Akkoordref.')],
          '#rows' => $provisionalRows,
          '#empty' => $this->t('Nog geen stelposten geregistreerd.'),
        ],
      ],
      'changes' => [
        '#type' => 'details',
        '#title' => $this->t('Meer-/minderwerk (@count)', ['@count' => count($changeRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Nr.'), $this->t('Omschrijving'), $this->t('Soort'), $this->t('Status'), $this->t('Verkoop excl. btw'), $this->t('Akkoordref.')],
          '#rows' => $changeRows,
          '#empty' => $this->t('Nog geen meer-/minderwerk geregistreerd.'),
        ],
      ],
      'drafts' => [
        '#type' => 'details',
        '#title' => $this->t('Factuurconcepten (@count)', ['@count' => count($draftRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Concept'), $this->t('Factuurdatum'), $this->t('Vervaldatum'), $this->t('Conceptstatus'), $this->t('Integratie'), $this->t('Excl. btw'), $this->t('BTW'), $this->t('Incl. btw'), $this->t('Actie')],
          '#rows' => $draftRows,
          '#empty' => $this->t('Voor dit project zijn nog geen verkoopfactuurconcepten aangemaakt.'),
        ],
      ],
      'invoices' => [
        '#type' => 'details',
        '#title' => $this->t('Debiteuren / officiële Moneybird-facturen (@count)', ['@count' => count($invoiceRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Factuur'), $this->t('Datum'), $this->t('Vervaldatum'), $this->t('Debiteurenstatus'), $this->t('Incl. btw'), $this->t('Ontvangen'), $this->t('Open'), $this->t('Geschil')],
          '#rows' => $invoiceRows,
          '#empty' => $this->t('Voor dit project zijn nog geen officiële Moneybird-verkoopfacturen geregistreerd.'),
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node:' . $projectId],
        'max-age' => 0,
      ],
    ];
  }

  private function loadOne(string $table, int $projectId): array {
    if (!$this->database->schema()->tableExists($table)) {
      return [];
    }
    $row = $this->database->select($table, 't')->fields('t')->condition('project_nid', $projectId)->execute()->fetchAssoc();
    return is_array($row) ? $row : [];
  }

  private function loadMany(string $table, int $projectId, string $orderField, string $direction = 'ASC'): array {
    if (!$this->database->schema()->tableExists($table)) {
      return [];
    }
    $query = $this->database->select($table, 't')->fields('t')->condition('project_nid', $projectId);
    if ($this->database->schema()->fieldExists($table, $orderField)) {
      $query->orderBy($orderField, $direction);
    }
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  private function kpi(string $label, mixed $value, string $basis): string {
    return '<div class="brebo-procurement-kpi"><strong>' . $this->money($value) . '</strong><span>' . $label . ' · ' . $basis . '</span></div>';
  }

  private function money(mixed $value): string {
    return !is_numeric($value) ? '—' : '€ ' . number_format((float) $value, 2, ',', '.');
  }

  private function receivableState(array $row, string $today): string {
    $status = (string) ($row['status'] ?? '—');
    if (in_array($status, ['credited', 'cancelled'], TRUE)) {
      return $status;
    }
    if ($status === 'disputed' || trim((string) ($row['dispute_reason'] ?? '')) !== '') {
      return (string) $this->t('Geschil');
    }
    $gross = (float) ($row['amount_inc_vat'] ?? 0);
    $paid = (float) ($row['paid_amount_inc_vat'] ?? 0);
    if ($gross > 0 && $paid >= $gross) {
      return (string) $this->t('Betaald');
    }
    $dueDate = (string) ($row['due_date'] ?? '');
    if ($gross > $paid && $dueDate !== '' && $dueDate < $today) {
      return $paid > 0 ? (string) $this->t('Deels betaald · vervallen') : (string) $this->t('Vervallen');
    }
    if ($paid > 0 && $paid < $gross) {
      return (string) $this->t('Deels betaald');
    }
    return $status !== '' ? $status : (string) $this->t('Open');
  }

  private function triggerLabel(string $trigger): string {
    return match ($trigger) {
      'contract_date' => 'Contractdatum',
      'calendar_date' => 'Kalenderdatum',
      'verified_progress' => 'Geverifieerde voortgang',
      'milestone' => 'Mijlpaal',
      'change_order' => 'Meer-/minderwerk',
      default => $trigger !== '' ? $trigger : '—',
    };
  }

  private function instalmentVatLabel(array $row): string {
    if (($row['vat_code'] ?? '') === 'MIXED' && !empty($row['id']) && $this->database->schema()->tableExists('brebo_finance_billing_instalment_line')) {
      $query = $this->database->select('brebo_finance_billing_instalment_line', 'l');
      $query->addField('l', 'vat_rate');
      $query->condition('instalment_id', (int) $row['id']);
      $query->distinct();
      $rates = array_map('floatval', $query->execute()->fetchCol());
      sort($rates, SORT_NUMERIC);
      if ($rates !== []) {
        return implode(' + ', array_map(static fn(float $rate): string => number_format($rate, 1, ',', '.') . '%', $rates));
      }
      return (string) $this->t('Gemengd');
    }
    if (!isset($row['vat_rate']) || !is_numeric($row['vat_rate'])) {
      return (string) ($row['vat_code'] ?? '—');
    }
    return number_format((float) $row['vat_rate'], 1, ',', '.') . '%';
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
  }

}
