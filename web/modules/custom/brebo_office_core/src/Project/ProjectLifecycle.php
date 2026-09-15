<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Project;

/**
 * Canonical BREBO project lifecycle.
 *
 * Machine values are deliberately stable and language-neutral. UI labels stay
 * Dutch and legacy free-text values are normalized at read/migration edges.
 */
final class ProjectLifecycle {

  public const CONCEPT = 'concept';
  public const PREPARATION = 'preparation';
  public const EXECUTION = 'execution';
  public const CLOSEOUT = 'closeout';
  public const CLOSED = 'closed';

  /** @return array<string, string> */
  public static function options(): array {
    return [
      self::CONCEPT => 'Concept',
      self::PREPARATION => 'Voorbereiding',
      self::EXECUTION => 'Uitvoering',
      self::CLOSEOUT => 'Oplevering',
      self::CLOSED => 'Afgesloten',
    ];
  }

  public static function label(string $value): string {
    $canonical = self::normalize($value);
    return self::options()[$canonical] ?? $value;
  }

  public static function normalize(string $value): string {
    $value = mb_strtolower(trim($value));
    return match ($value) {
      '', 'concept' => self::CONCEPT,
      'actief', 'active', 'voorbereiding', 'werkvoorbereiding', 'preparation' => self::PREPARATION,
      'uitvoering', 'in uitvoering', 'execution' => self::EXECUTION,
      'oplevering', 'opgeleverd', 'closeout', 'delivery' => self::CLOSEOUT,
      'afgesloten', 'gesloten', 'gereed', 'closed' => self::CLOSED,
      default => $value,
    };
  }

  public static function isKnown(string $value): bool {
    return array_key_exists(self::normalize($value), self::options());
  }

  public static function isActive(string $value): bool {
    return self::normalize($value) !== self::CLOSED;
  }

  public static function isExecution(string $value): bool {
    return self::normalize($value) === self::EXECUTION;
  }

}
