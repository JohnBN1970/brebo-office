<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * One invoice register for incoming and outgoing invoices.
 * The direction is independent of project association and payment status.
 */
final class OfficeInvoiceDirection {
  public const INCOMING = 'incoming';
  public const OUTGOING = 'outgoing';
  public const ALL = 'all';

  public static function filter(?string $direction): string {
    $direction = strtolower(trim($direction ?? ''));
    return in_array($direction, [self::INCOMING, self::OUTGOING], true)
      ? $direction
      : self::ALL;
  }

  public static function label(string $direction): string {
    return match ($direction) {
      self::INCOMING => 'Inkomend',
      self::OUTGOING => 'Uitgaand',
      self::ALL => 'Alle',
      default => throw new \InvalidArgumentException('Unknown invoice direction'),
    };
  }

  public static function isValidInvoiceDirection(string $direction): bool {
    return in_array($direction, [self::INCOMING, self::OUTGOING], true);
  }
}
