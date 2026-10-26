<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Pause registration policy independent of Drupal. */
final class PausePolicy {
  public const OFF = 'off';
  public const OPTIONAL = 'optional';
  public const REQUIRED = 'required';

  public function normalize(?string $mode): string {
    return in_array($mode, [self::OFF, self::OPTIONAL, self::REQUIRED], true) ? $mode : self::OFF;
  }

  public function showsPauseControls(?string $mode): bool {
    return $this->normalize($mode) !== self::OFF;
  }

  public function requiresPauseRegistration(?string $mode): bool {
    return $this->normalize($mode) === self::REQUIRED;
  }
}
