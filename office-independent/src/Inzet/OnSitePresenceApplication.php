<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Office presence composition root. Requires an independently verified zone
 * source; without one, fail closed rather than guessing a zone assignment.
 */
final class OnSitePresenceApplication {
  public function __construct(
    private PDO $db,
    private ?\Closure $verifiedZoneMembership = null,
  ) {}

  /** @return array{status:int,body:array<string,mixed>} */
  public function submit(array $payload, string $deviceToken): array {
    if ($this->verifiedZoneMembership === null) {
      throw new RuntimeException('OnSite zone membership source not configured');
    }
    $assignments = new OnSiteOfficeTeamAssignmentRepository(
      $this->db, $this->verifiedZoneMembership
    );
    $handler = new OnSitePresenceApiHandler(
      new OnSitePresenceRequestService(),
      new OnSiteAuthenticatedEventService(
        new OnSiteDeviceTokenRepository($this->db),
        new OnSiteAutomaticIngest(
          new OnSiteAutomaticEventValidator(),
          new OnSiteAssignmentGate($assignments),
          new OnSiteEventInbox($this->db),
        )
      )
    );
    return $handler->submit($payload, $deviceToken);
  }
}
