<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;

/**
 * Office application boundary. The HTTP adapter must resolve employee identity
 * from a verified device credential; never trust an employee id from JSON.
 * AssignmentStore is a projection populated by the canonical Project/Inzet modules.
 */
final class OnSiteOfficeGateway {
  public function __construct(
    private OnSiteAssignmentStore $assignments,
    private OnSiteAutomaticIngest $ingest,
    private OnSiteEventInbox $inbox,
  ) {}

  /** @return array{employee:array{id:string,display_name:string,language:string},projects:array} */
  public function bootstrap(string $verifiedEmployeeId, string $displayName, string $language, string $date): array {
    if ($verifiedEmployeeId === '') {
      throw new \InvalidArgumentException('Verified device identity required');
    }
    return [
      'employee' => [
        'id' => $verifiedEmployeeId,
        'display_name' => $displayName,
        'language' => $language,
      ],
      'projects' => $this->assignments->projectsForEmployee($verifiedEmployeeId, $date),
    ];
  }

  /** @return array{ok:bool,accepted:bool,event_id:string} */
  public function presence(array $payload, string $verifiedEmployeeId, ?DateTimeImmutable $receivedAt = null): array {
    if (($payload['trigger'] ?? null) !== 'automatic_geofence') {
      throw new \InvalidArgumentException('Automatic geofence trigger required');
    }
    $result = $this->ingest->accept(
      $payload,
      $verifiedEmployeeId,
      $this->assignments->isAssigned(...),
      $receivedAt,
    );
    return ['ok' => true] + $result;
  }

  /** Presence evidence is never automatically approved as payable hours. */
  public function review(string $verifiedEmployeeId): array {
    if ($verifiedEmployeeId === '') {
      throw new \InvalidArgumentException('Verified identity required');
    }
    return OnSitePresenceReconciler::reconcile(
      $this->inbox->eventsForEmployee($verifiedEmployeeId),
    );
  }
}
