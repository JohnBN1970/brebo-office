<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Shows the project contract and its obligations. */
final class ProjectContractsController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $this->t('Contracten — @project', ['@project' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();

    $contract = $this->loadContract($projectId);
    $obligations = $this->loadObligations($projectId);
    $active = array_values(array_filter($obligations, static fn(array $row): bool => !in_array((string) ($row['status'] ?? ''), ['verified', 'waived'], TRUE)));
    $today = new \DateTimeImmutable('today');
    $limit = $today->modify('+30 days');
    $upcoming = array_values(array_filter($active, static function (array $row) use ($today, $limit): bool {
      $due = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($row['due_date'] ?? ''));
      return $due !== FALSE && $due >= $today && $due <= $limit;
    }));
    $financialRisk = array_sum(array_map(static fn(array $row): float => (float) ($row['financial_exposure_ex_vat'] ?? 0), $active));

    $contractRows = [];
    if ($contract !== []) {
      $contractRows[] = [
        (string) ($contract['contract_number'] ?? '—'),
        (string) ($contract['client_ref'] ?? '—'),
        (string) ($contract['status'] ?? '—'),
        (string) ($contract['contract_date'] ?? '—'),
        isset($contract['payment_term_days']) ? ((string) $contract['payment_term_days'] . ' dagen') : '—',
        !empty($contract['g_account_applicable']) ? $this->t('Ja') : $this->t('Nee'),
        $this->money($contract['amount_ex_vat'] ?? NULL),
      ];
    }

    $obligationRows = [];
    foreach ($obligations as $row) {
      $obligationRows[] = [
        (string) ($row['obligation_number'] ?? '—'),
        (string) ($row['title'] ?? '—'),
        $this->typeLabel((string) ($row['obligation_type'] ?? '')),
        (string) ($row['responsible_side'] ?? '—'),
        (string) ($row['due_date'] ?? '—'),
        (string) ($row['status'] ?? '—'),
        $this->money($row['financial_exposure_ex_vat'] ?? NULL),
      ];
    }

    return [
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-procurement-kpis']],
        'sum' => ['#markup' => $this->kpi('Contractsom', $contract['amount_ex_vat'] ?? NULL, 'excl. btw')],
        'open' => ['#markup' => $this->countKpi(count($active), 'openstaande contractverplichtingen')],
        'upcoming' => ['#markup' => $this->countKpi(count($upcoming), 'termijnen binnen 30 dagen')],
        'risk' => ['#markup' => $this->kpi('Financieel risico', $financialRisk, 'openstaande verplichtingen')],
      ],
      'contract' => [
        '#type' => 'details',
        '#title' => $this->t('Projectcontract'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Contractnummer'), $this->t('Referentie opdrachtgever'), $this->t('Status'), $this->t('Contractdatum'), $this->t('Betaaltermijn'), $this->t('G-rekening'), $this->t('Contractsom excl. btw')],
          '#rows' => $contractRows,
          '#empty' => $this->t('Voor dit project is nog geen projectcontract geregistreerd.'),
        ],
      ],
      'obligations' => [
        '#type' => 'details',
        '#title' => $this->t('Contractverplichtingen (@count)', ['@count' => count($obligationRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Nr.'), $this->t('Verplichting'), $this->t('Type'), $this->t('Verantwoordelijke partij'), $this->t('Vervaldatum'), $this->t('Status'), $this->t('Financieel risico excl. btw')],
          '#rows' => $obligationRows,
          '#empty' => $this->t('Voor dit project zijn nog geen contractverplichtingen geregistreerd.'),
        ],
      ],
      'explanation' => [
        '#type' => 'details',
        '#title' => $this->t('Toelichting'),
        '#open' => FALSE,
        'text' => ['#markup' => '<p>' . $this->t('Hier staan het projectcontract en de contractuele verplichtingen die voor dit project bewaakt worden. Uitgebreid financieel beheer blijft in Finance.') . '</p>'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node:' . $projectId],
        'max-age' => 0,
      ],
    ];
  }

  private function loadContract(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_project_contract')) {
      return [];
    }
    $row = $this->database->select('brebo_finance_project_contract', 'c')
      ->fields('c')
      ->condition('project_nid', $projectId)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : [];
  }

  private function loadObligations(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_contract_obligation')) {
      return [];
    }
    $query = $this->database->select('brebo_finance_contract_obligation', 'o')
      ->fields('o')
      ->condition('project_nid', $projectId)
      ->orderBy('due_date', 'ASC');
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  private function kpi(string $label, mixed $value, string $basis): string {
    return '<div class="brebo-procurement-kpi"><strong>' . $this->money($value) . '</strong><span>' . $label . ' · ' . $basis . '</span></div>';
  }

  private function countKpi(int $value, string $label): string {
    return '<div class="brebo-procurement-kpi"><strong>' . $value . '</strong><span>' . $label . '</span></div>';
  }

  private function money(mixed $value): string {
    return !is_numeric($value) ? '—' : '€ ' . number_format((float) $value, 2, ',', '.');
  }

  private function typeLabel(string $type): string {
    return match ($type) {
      'payment_term' => 'Betaaltermijn',
      'notice_period' => 'Meld-/opzegtermijn',
      'claim_deadline' => 'Claimtermijn',
      'guarantee' => 'Garantie',
      'retention' => 'Inhouding',
      'bank_guarantee' => 'Bankgarantie',
      'insurance' => 'Verzekering',
      'indexation' => 'Indexatie',
      'penalty' => 'Boete',
      'delivery_condition' => 'Leveringsvoorwaarde',
      'document' => 'Document',
      default => $type !== '' ? $type : '—',
    };
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
  }

}
