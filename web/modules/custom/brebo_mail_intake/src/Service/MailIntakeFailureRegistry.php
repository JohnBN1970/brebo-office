<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\State\StateInterface;

/**
 * Stores a bounded, privacy-safe register of technical Mail Intake exceptions.
 */
final class MailIntakeFailureRegistry {

  private const STATE_KEY = 'brebo_mail_intake.technical_failures';
  private const MAX_ITEMS = 100;

  public function __construct(
    private readonly StateInterface $state,
  ) {}

  public function record(string $sourceReference, string $exceptionType): void {
    $items = $this->all();
    $now = time();
    $existing = $items[$sourceReference] ?? [];

    $items[$sourceReference] = [
      'source_ref' => $sourceReference,
      'exception_type' => $exceptionType,
      'first_seen' => (int) ($existing['first_seen'] ?? $now),
      'last_seen' => $now,
      'attempts' => ((int) ($existing['attempts'] ?? 0)) + 1,
      'status' => 'open',
      'source_preserved' => TRUE,
    ];

    uasort($items, static fn(array $a, array $b): int => ((int) $b['last_seen']) <=> ((int) $a['last_seen']));
    if (count($items) > self::MAX_ITEMS) {
      $items = array_slice($items, 0, self::MAX_ITEMS, TRUE);
    }
    $this->state->set(self::STATE_KEY, $items);
  }

  public function acknowledge(string $sourceReference): bool {
    $items = $this->all();
    if (!isset($items[$sourceReference])) {
      return FALSE;
    }
    $items[$sourceReference]['status'] = 'acknowledged';
    $items[$sourceReference]['acknowledged_at'] = time();
    $this->state->set(self::STATE_KEY, $items);
    return TRUE;
  }

  /** @return array<int, array<string, mixed>> */
  public function openItems(): array {
    $items = array_values(array_filter(
      $this->all(),
      static fn(array $item): bool => ($item['status'] ?? 'open') === 'open',
    ));
    usort($items, static fn(array $a, array $b): int => ((int) $b['last_seen']) <=> ((int) $a['last_seen']));
    return $items;
  }

  /** @return array<string, array<string, mixed>> */
  private function all(): array {
    $items = $this->state->get(self::STATE_KEY, []);
    return is_array($items) ? $items : [];
  }

}
