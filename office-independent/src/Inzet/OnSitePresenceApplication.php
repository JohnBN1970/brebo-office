<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use PDO;

/**
 * Composes the independent Office OnSite application services.
 * Schema provisioning and device enrollment are privileged operations,
 * deliberately NOT performed by mobile requests.
 */
final class OnSitePresenceApplication {
  private OnSitePresenceRequestService $requests;
  private OnSiteAssignmentRepository $assignments;

  public function __construct(PDO $db) {
    $this->assignments = new OnSiteAssignmentRepository($db);
    $this->requests = new OnSitePresenceRequestService(
      new OnSiteDeviceTokenRepository($db),
      new OnSitePresenceApiHandler(
        new OnSiteAutomaticIngest(new OnSiteEventInbox($db))
      )
    );
  }

  /** @return array{status:int,body:array<string,mixed>} */
  public function submit(array $payload, ?string $bearerToken, ?DateTimeImmutable $receivedAt = null): array {
    return $this->requests->submit(
      $payload,
      $bearerToken,
      fn (string $employeeId, string $projectId, string $zoneId, string $occurredAt): bool =>
        $this->assignments->allows($employeeId, $projectId, $zoneId, $occurredAt),
      $receivedAt
    );
  }
}
