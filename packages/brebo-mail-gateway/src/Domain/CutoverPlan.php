<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Domain;

use InvalidArgumentException;

final readonly class CutoverPlan {

  public function __construct(
    public string $domain,
    public bool $testDomain,
    public bool $cutoverAllowed,
    public CutoverStatus $status,
  ) {
    if ($domain === '' || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === FALSE) {
      throw new InvalidArgumentException('Ongeldig cutoverdomein.');
    }
    if (!$testDomain && !$cutoverAllowed) {
      return;
    }
  }

  public function mayActivate(): bool {
    return $this->testDomain && $this->cutoverAllowed && $this->status === CutoverStatus::Ready;
  }
}
