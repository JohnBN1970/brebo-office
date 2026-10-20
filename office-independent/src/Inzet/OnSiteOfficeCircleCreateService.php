<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Office-native circle creation; no HTTP route is enabled by this service.
 * Callers supply a verified session context and a server-resolved Office project.
 * The future POST adapter must enforce CSRF and duplicate-request protection.
 */
final class OnSiteOfficeCircleCreateService {
  public function __construct(private PDO $db) {}

  /** @param array<string,mixed> $input */
  public function create(WebsiteCrmOperatorContext $operator, string $projectId, array $input): string {
    // Do not silently accept browser identities, revisions, activation or building bindings.
    $allowed = ['project_id', 'zone_name', 'latitude', 'longitude', 'radius_metres'];
    if (array_diff(array_keys($input), $allowed) !== []) {
      throw new InvalidArgumentException('office_circle_unknown_fields');
    }
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $operator->operatorId)) {
      throw new RuntimeException('office_project_map_access_denied');
    }
    $zone = OnSiteOfficeCircleInput::forAuthorizedProject($input, $projectId);
    $authorize = function (string $selectedProject) use ($operator): bool {
      // Lock the authoritative records in the allocator's transaction: account
      // deactivation or membership revocation cannot race a successful insert.
      $query = $this->db->prepare("SELECT p.id, p.latitude, p.longitude
        FROM office_projects p
        INNER JOIN office_project_memberships m ON m.project_id = p.id
        INNER JOIN office_operator_accounts a ON a.id = m.operator_id
        WHERE p.id = ? AND m.operator_id = ? AND m.permission = 'manage'
          AND a.active = 1 LIMIT 1 FOR UPDATE");
      $query->execute([$selectedProject, $operator->operatorId]);
      $project = $query->fetch(PDO::FETCH_ASSOC);
      if (!is_array($project)) return false;
      OnSiteOfficeProjectMapSeed::fromAuthorizedProject($project);
      return true;
    };
    $creator = new OnSiteOfficeZoneCreator($this->db, new OnSiteCanonicalZoneSync($this->db), $authorize);
    return $creator->create($zone);
  }
}
