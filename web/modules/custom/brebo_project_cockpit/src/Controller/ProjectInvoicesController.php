<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Shows project billing instalments, changes and client invoices. */
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
    $invoices = $this->loadMany('brebo_finance_sales_invoice', $projectId, 'invoice_date', 'DESC');

    $contractValue = (float) ($contract['amount_ex_vat'] ?? 0);
    $approvedChanges = 0.0;
    foreach ($changes as $row) {
      if (($row['status'] ?? '') === 'client_approved' || in_array((string) ($row['status'] ?? ''), ['executed', 'invoiced', 'paid'], TRUE)) {
        $amount = (float) ($row['sales_amount_ex_vat'] ?? 0);
        $approvedChanges += (($row['change_type'] ?? '') === 'omission') ? -$amount : $amount;
      }
    }
    $currentOrderValue = $contractValue + $approvedChanges;

    $invoiced = 0.0;
    $received = 0.0;
    $open = 0.0;
    foreach ($invoices as $row) {
      if (!in_array((string) ($row['status'] ?? ''), ['credited', 'cancelled'], TRUE)) {
        $gross = (float) ($row['amount_inc_vat'] ?? 0);
        $paid = (float) ($row['paid_amount_inc_vat'] ?? 0);
        $invoiced += (float) ($row['amount_ex_vat'] ?? 0);
        $received += $paid;
        $open += max(0.0, $gross - $paid);
      }
    }

    $planned = 0.0;
    foreach ($instalments as $row) {
      if (!in_array((string) ($row['status'] ?? ''), ['cancelled'], TRUE)) {
        $planned += (float) ($row['amount_ex_vat'] ?? 0);
      }
    }
    $remaining = max(0.0, $currentOrderValue - $invoiced);

    $instalmentRows = [];
    foreach ($instalments as $row) {
      $instalmentRows[] = [
        (string) ($row['instalment_number'] ?? '—'),
        (string) ($row['description'] ?? '—'),
        (string) ($row['planned_invoice_date'] ?? '—'),
        $this->triggerLabel((string) ($row['trigger_type'] ?? '')),
        (string) ($row['status'] ?? '—'),
        $this->money($row['amount_ex_vat'] ?? NULL),
        $this->instalmentVatLabel($row),
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

    $invoiceRows = [];
    foreach ($invoices as $row) {
      $invoiceRows[] = [
        (string) ($row['invoice_number'] ?? '—'),
        (string) ($row['invoice_date'] ?? '—'),
        (string) ($row['due_date'] ?? '—'),
        (string) ($row['status'] ?? '—'),
        $this->money($row['amount_ex_vat'] ?? NULL),
        $this->money($row['vat_amount'] ?? NULL),
        $this->money($row['amount_inc_vat'] ?? NULL),
        $this->money($row['paid_amount_inc_vat'] ?? NULL),
      ];
    }

    return [
      'principle' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-invoices-principle']],
        'title' => ['#markup' => '<h2>' . $this->t('Opbrengsten en verkoopfacturen') . '</h2>'],
        'text' => ['#markup' => '<p>' . $this->t('Deze projecttab toont uitsluitend de opbrengstenkant: termijnschema, meer-/minderwerk en verkoopfacturen. Inkoopfacturen blijven onder Inkoop. Moneybird blijft de boekhoudkundige bron voor de verkoopfactuurspiegel.') . '</p>'],
      ],
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-procurement-kpis']],
        'order' => ['#markup' => $this->kpi('Actuele opdrachtwaarde', $currentOrderValue, 'excl. btw')],
        'planned' => ['#markup' => $this->kpi('Termijnen gepland', $planned, 'excl. btw')],
        'invoiced' => ['#markup' => $this->kpi('Gefactureerd', $invoiced, 'excl. btw')],
        'received' => ['#markup' => $this->kpi('Ontvangen', $received, 'incl. btw')],
        'open' => ['#markup' => $this->kpi('Openstaand', $open, 'incl. btw')],
        'remaining' => ['#markup' => $this->kpi('Nog te factureren', $remaining, 'excl. btw')],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'note' => ['#markup' => '<p><strong>' . $this->t('BTW-bewaking:') . '</strong> ' . $this->t('Termijnen kunnen onderliggend meerdere btw-regels bevatten. De cockpit toont bij gemengde termijnen de gebruikte tarieven samen; de termijnkop blijft alleen de financiële samenvatting.') . '</p>'],
      ],
      'instalments' => [
        '#type' => 'details',
        '#title' => $this->t('Termijnschema (@count)', ['@count' => count($instalmentRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Termijn'), $this->t('Omschrijving'), $this->t('Gepland'), $this->t('Trigger'), $this->t('Status'), $this->t('Excl. btw'), $this->t('BTW')],
          '#rows' => $instalmentRows,
          '#empty' => $this->t('Voor dit project is nog geen termijnschema geregistreerd.'),
        ],
      ],
      'changes' => [
        '#type' => 'details',
        '#title' => $this->t('Meer-/minderwerk & stelpostverrekening (@count)', ['@count' => count($changeRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Nr.'), $this->t('Omschrijving'), $this->t('Soort'), $this->t('Status'), $this->t('Verkoop excl. btw'), $this->t('Akkoordref.')],
          '#rows' => $changeRows,
          '#empty' => $this->t('Nog geen meer-/minderwerk of stelpostverrekening geregistreerd.'),
        ],
      ],
      'invoices' => [
        '#type' => 'details',
        '#title' => $this->t('Verkoopfacturen (@count)', ['@count' => count($invoiceRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Factuur'), $this->t('Datum'), $this->t('Vervaldatum'), $this->t('Status'), $this->t('Excl. btw'), $this->t('BTW'), $this->t('Incl. btw'), $this->t('Ontvangen')],
          '#rows' => $invoiceRows,
          '#empty' => $this->t('Voor dit project zijn nog geen verkoopfacturen geregistreerd.'),
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
