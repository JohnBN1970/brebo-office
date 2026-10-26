<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Read-only preflight for project-bound activation invitations.
 * Never creates/alters tables or infers a project for legacy invitations.
 */
final class OnSiteActivationInvitationReadiness {
  /** @return array<string,bool> */
  public static function inspect(PDO $db): array {
    $q = $db->prepare("SELECT ENGINE FROM information_schema.TABLES
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_activation_invitations'");
    $q->execute();
    $report = ['invitation table InnoDB' => $q->fetchColumn() === 'InnoDB'];
    $q = $db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_activation_invitations'");
    $columns = $q->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    $project = $columns['project_id'] ?? [];
    $report['project_id unsigned bigint not null'] =
      preg_match('/^bigint(?:\\([0-9]+\\))? unsigned$/D', $project['COLUMN_TYPE'] ?? '') === 1
      && ($project['IS_NULLABLE'] ?? '') === 'NO';
    $q = $db->query("SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
      FROM information_schema.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_activation_invitations'
        AND COLUMN_NAME='project_id' AND REFERENCED_TABLE_SCHEMA=DATABASE()");
    $keys = $q->fetchAll(PDO::FETCH_ASSOC);
    $report['project_id references office_projects'] = false;
    foreach ($keys as $key) {
      if ($key['REFERENCED_TABLE_NAME'] === 'office_projects'
        && $key['REFERENCED_COLUMN_NAME'] === 'id') {
        $report['project_id references office_projects'] = true;
      }
    }
    return $report;
  }

  public static function requireReady(PDO $db): void {
    if (in_array(false, self::inspect($db), true)) {
      throw new RuntimeException('onsite_activation_invitation_schema_not_ready');
    }
  }
}
