<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use DateInterval;
use DateTimeImmutable;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Builds a 30/60/90 day liquidity view from explicit bank roles and cash events.
 *
 * No future cash movement is inferred. Only confirmed/expected cash events
 * already recorded by BREBO Finance are included.
 */
final class PortfolioLiquidityProjection {

  private const array HORIZONS = [30, 60, 90];
  private const array ACTIVE_STATUSES = ['confirmed', 'expected'];
  private const array ACCOUNT_BUCKETS = ['regular', 'g_account'];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * @param array<string, mixed> $businessHealth Normalized BusinessHealthBuilder payload.
   * @return array<string, mixed>
   */
  public function build(AccountInterface $account, array $businessHealth): array {
    $accounts = is_array($businessHealth['liquidity']['accounts'] ?? NULL)
      ? array_values($businessHealth['liquidity']['accounts'])
      : [];
    $roles = $this->configFactory->get('brebo_finance.business_health')->get('bank_account_roles');
    $roles = is_array($roles) ? $roles : [];

    $opening = ['regular' => '0.0000', 'g_account' => '0.0000'];
    $classified = [];
    $unclassified = [];
    $reasons = [];
    $regularCount = 0;

    foreach ($accounts as $sourceAccount) {
      if (!is_array($sourceAccount)) {
        continue;
      }
      $id = trim((string) ($sourceAccount['id'] ?? ''));
      if ($id === '') {
        continue;
      }
      $role = trim((string) ($roles[$id] ?? ''));
      $currency = strtoupper(trim((string) ($sourceAccount['currency'] ?? 'EUR')));
      $accountSummary = [
        'id' => $id,
        'name' => (string) ($sourceAccount['name'] ?? 'Bankrekening'),
        'identifier' => (string) ($sourceAccount['identifier'] ?? ''),
        'currency' => $currency,
        'closing_balance' => $this->money((string) ($sourceAccount['closing_balance'] ?? '0')),
        'role' => $role !== '' ? $role : NULL,
      ];

      if (!in_array($role, ['regular', 'g_account', 'excluded'], TRUE)) {
        $unclassified[] = $accountSummary;
        continue;
      }
      $classified[] = $accountSummary;
      if ($role === 'excluded') {
        continue;
      }
      if ($currency !== 'EUR') {
        $reasons[] = sprintf('Bankrekening %s heeft valuta %s; zonder expliciete FX-bron wordt dit saldo niet omgerekend.', $id, $currency);
        continue;
      }
      $opening[$role] = $this->decimal->add($opening[$role], (string) ($sourceAccount['closing_balance'] ?? '0'));
      if ($role === 'regular') {
        $regularCount++;
      }
    }

    if ($accounts === []) {
      $reasons[] = 'Moneybird leverde geen bankrekeningen voor het actuele liquiditeitsbeeld.';
    }
    if ($unclassified !== []) {
      $reasons[] = 'Niet alle Moneybird-bankrekeningen hebben een expliciete Finance-rol.';
    }
    if ($regularCount === 0) {
      $reasons[] = 'Er is geen EUR-bankrekening als reguliere liquiditeitsrekening geclassificeerd.';
    }

    $projectIds = $this->viewableProjectIds($account);
    $eventsAvailable = $this->cashEventSchemaAvailable();
    if (!$eventsAvailable) {
      $reasons[] = 'De brongebonden cash-eventtabel is niet beschikbaar.';
    }
    $events = $eventsAvailable ? $this->events($projectIds) : [];

    $horizons = [];
    $today = new DateTimeImmutable('today');
    foreach (self::HORIZONS as $days) {
      $end = $today->add(new DateInterval('P' . $days . 'D'));
      $committed = $this->project($opening, $events, $end, ['confirmed']);
      $expected = $this->project($opening, $events, $end, self::ACTIVE_STATUSES);
      $horizons[(string) $days] = [
        'days' => $days,
        'through_date' => $end->format('Y-m-d'),
        'committed' => $committed,
        'expected' => $expected,
      ];
    }

    return [
      'status' => $reasons === [] ? 'ok' : 'incomplete',
      'complete' => $reasons === [],
      'basis' => 'Current Moneybird bank closing balances by explicit Office bank role plus source-backed BREBO cash events; no future cash movement is inferred.',
      'generated_at' => time(),
      'opening' => [
        'regular' => $this->money($opening['regular']),
        'g_account' => $this->money($opening['g_account']),
      ],
      'bank_accounts' => $classified,
      'unclassified_bank_accounts' => $unclassified,
      'viewable_project_count' => count($projectIds),
      'source_event_count' => count($events),
      'reasons' => array_values(array_unique($reasons)),
      'horizons' => $horizons,
    ];
  }

  /**
   * @param array{regular:string,g_account:string} $opening
   * @param list<array<string, mixed>> $events
   * @param list<string> $statuses
   * @return array<string, mixed>
   */
  private function project(array $opening, array $events, DateTimeImmutable $through, array $statuses): array {
    $totals = [
      'regular' => ['incoming' => '0.0000', 'outgoing' => '0.0000'],
      'g_account' => ['incoming' => '0.0000', 'outgoing' => '0.0000'],
    ];
    $included = 0;

    foreach ($events as $event) {
      $status = (string) ($event['status'] ?? '');
      $bucket = (string) ($event['account_bucket'] ?? '');
      $direction = (string) ($event['direction'] ?? '');
      $dueDate = (string) ($event['due_date'] ?? '');
      if (!in_array($status, $statuses, TRUE)
        || !in_array($bucket, self::ACCOUNT_BUCKETS, TRUE)
        || !in_array($direction, ['incoming', 'outgoing'], TRUE)
        || $dueDate === ''
        || $dueDate > $through->format('Y-m-d')
      ) {
        continue;
      }
      $totals[$bucket][$direction] = $this->decimal->add(
        $totals[$bucket][$direction],
        (string) ($event['amount_inc_vat'] ?? '0'),
      );
      $included++;
    }

    $result = ['event_count' => $included];
    foreach (self::ACCOUNT_BUCKETS as $bucket) {
      $net = $this->decimal->subtract($totals[$bucket]['incoming'], $totals[$bucket]['outgoing']);
      $closing = $this->decimal->add($opening[$bucket], $net);
      $result[$bucket] = [
        'opening_balance' => $this->money($opening[$bucket]),
        'incoming' => $this->money($totals[$bucket]['incoming']),
        'outgoing' => $this->money($totals[$bucket]['outgoing']),
        'net_movement' => $this->money($net),
        'projected_balance' => $this->money($closing),
        'shortfall' => $this->decimal->compare($closing, '0') < 0,
      ];
    }
    return $result;
  }

  /** @return list<array<string, mixed>> */
  private function events(array $projectIds): array {
    if ($projectIds === []) {
      return [];
    }
    $end = (new DateTimeImmutable('today'))->add(new DateInterval('P90D'))->format('Y-m-d');
    $query = $this->database->select('brebo_finance_cash_event', 'e');
    $query->fields('e', ['project_nid', 'direction', 'account_bucket', 'amount_inc_vat', 'due_date', 'status', 'confidence', 'source_system', 'source_type', 'source_id']);
    $query->condition('project_nid', $projectIds, 'IN');
    $query->condition('status', self::ACTIVE_STATUSES, 'IN');
    $query->condition('due_date', $end, '<=');
    $query->orderBy('due_date', 'ASC');
    $query->orderBy('id', 'ASC');
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  /** @return list<int> */
  private function viewableProjectIds(AccountInterface $account): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'brebo_project')->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $project) {
      if ($project instanceof NodeInterface && $project->access('view', $account)) {
        $result[] = (int) $project->id();
      }
    }
    return $result;
  }

  private function cashEventSchemaAvailable(): bool {
    $schema = $this->database->schema();
    if (!$schema->tableExists('brebo_finance_cash_event')) {
      return FALSE;
    }
    foreach (['project_nid', 'direction', 'account_bucket', 'amount_inc_vat', 'due_date', 'status'] as $field) {
      if (!$schema->fieldExists('brebo_finance_cash_event', $field)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function money(string $value): string {
    $normalized = $this->decimal->add('0', $value === '' ? '0' : $value);
    [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0000');
    return $whole . '.' . substr(str_pad($fraction, 4, '0'), 0, 2);
  }

}
