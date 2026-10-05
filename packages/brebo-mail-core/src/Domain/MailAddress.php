<?php

declare(strict_types=1);

namespace Brebo\Mail\Domain;

use InvalidArgumentException;

final readonly class MailAddress {
  public function __construct(public string $value) {
    $normalized = mb_strtolower(trim($value));
    if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldig e-mailadres.');
    }
    if ($normalized !== $value) {
      throw new InvalidArgumentException('E-mailadres moet genormaliseerd worden aangeleverd.');
    }
  }

  public static function fromParts(string $localPart, string $domain): self {
    $localPart = mb_strtolower(trim($localPart));
    $domain = mb_strtolower(trim($domain));
    if (!preg_match('/^[a-z0-9._+-]+$/', $localPart)) {
      throw new InvalidArgumentException('Ongeldig lokaal deel van e-mailadres.');
    }
    return new self($localPart . '@' . $domain);
  }
}
