<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Read-only communication block projections for module views.
 *
 * One canonical communication may be visible in several module contexts.
 * Visibility is NOT authorization: callers must check access to both the
 * communication and the destination before rendering a projection.
 */
final class MailCommunicationBlockProjector {

  /**
   * @param array<int, array{module:string,object_type:string,object_id:int}> $links
   * @return array<int, array{communication_id:int,module:string,object_type:string,object_id:int}>
   */
  public function project(int $communicationId, array $links): array {
    if ($communicationId <= 0) {
      throw new \InvalidArgumentException('A persisted communication id is required.');
    }

    $result = [];
    foreach ($links as $link) {
      $module = trim((string) ($link['module'] ?? ''));
      $type = trim((string) ($link['object_type'] ?? ''));
      $id = (int) ($link['object_id'] ?? 0);
      if ($module === '' || $type === '' || $id <= 0) {
        continue;
      }
      $key = $module . ':' . $type . ':' . $id;
      $result[$key] = [
        'communication_id' => $communicationId,
        'module' => $module,
        'object_type' => $type,
        'object_id' => $id,
      ];
    }

    return array_values($result);
  }

}
