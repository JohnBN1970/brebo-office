<?php

declare(strict_types=1);

namespace Brebo\Mail\Contract;

interface GatewayHttpClientInterface {
  /** @param array<string,mixed> $payload
   *  @return array<string,mixed>
   */
  public function post(string $path, array $payload): array;

  /** @return array<string,mixed> */
  public function get(string $path): array;
}
