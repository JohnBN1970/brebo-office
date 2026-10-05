<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Domain;

use InvalidArgumentException;

final readonly class GatewayRequest {

  /** @param array<string,string> $headers */
  public function __construct(
    public string $method,
    public string $path,
    public string $body,
    public array $headers,
  ) {
    if ($path === '' || !str_starts_with($path, '/')) {
      throw new InvalidArgumentException('Ongeldig gatewaypad.');
    }
  }

  public function header(string $name): string {
    foreach ($this->headers as $key => $value) {
      if (strcasecmp($key, $name) === 0) {
        return trim($value);
      }
    }
    return '';
  }
}
