<?php

declare(strict_types=1);

namespace Brebo\Mail\Domain;

final readonly class DnsPolicyResult {
  /** @param array<string,string> $checks */
  public function __construct(
    public bool $verified,
    public array $checks,
  ) {}
}
