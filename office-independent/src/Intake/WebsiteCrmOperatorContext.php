<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Verified operator context produced by an authenticated Office session adapter.
 * Never construct from untrusted request fields or browser-supplied headers.
 */
final class WebsiteCrmOperatorContext {

  public function __construct(
    public readonly string $operatorId,
    public readonly string $sessionId,
    public readonly bool $canEditCrm,
  ) {
    if ($operatorId === '' || strlen($operatorId) > 128 || $sessionId === '' || strlen($sessionId) > 256) {
      throw new \InvalidArgumentException('Invalid authenticated operator context.');
    }
  }

  public function requireCrmEdit(): void {
    if (!$this->canEditCrm) {
      throw new \DomainException('CRM edit permission required.');
    }
  }
}
