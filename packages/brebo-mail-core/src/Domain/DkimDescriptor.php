<?php

declare(strict_types=1);

namespace Brebo\Mail\Domain;

use InvalidArgumentException;

final readonly class DkimDescriptor {
  public function __construct(
    public string $selector,
    public string $publicKey,
  ) {
    if (preg_match('/^[A-Za-z0-9._-]+$/', $selector) !== 1) {
      throw new InvalidArgumentException('Ongeldige DKIM-selector.');
    }
    if (trim($publicKey) === '') {
      throw new InvalidArgumentException('DKIM public key ontbreekt.');
    }
  }

  public function recordName(string $domain): string {
    return $this->selector . '._domainkey.' . mb_strtolower(trim($domain));
  }

  public function recordValue(): string {
    return 'v=DKIM1; k=rsa; p=' . preg_replace('/\s+/', '', $this->publicKey);
  }
}
