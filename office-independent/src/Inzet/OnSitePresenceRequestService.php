<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;

/**
 * Connects bearer-device verification to automatic presence validation.
 * Project/zone authorization is supplied by the Office assignment provider.
 */
final class OnSitePresenceRequestService {
  public function __construct(
    private OnSiteDeviceTokenRepository $devices,
    private OnSitePresenceApiHandler $handler,
  ) {}

  /**
   * @param callable(string,string,string,string):bool $authorized
   * @return array{status:int,body:array<string,mixed>}
   */
  public function submit(
    array $payload,
    ?string $bearerToken,
    callable $authorized,
    ?DateTimeImmutable $receivedAt = null,
  ): array {
    $employeeId = $bearerToken === null ? null : $this->devices->resolve($bearerToken);
    return $this->handler->handle($payload, $employeeId, $authorized, $receivedAt);
  }
}
