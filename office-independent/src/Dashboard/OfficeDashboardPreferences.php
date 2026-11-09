<?php
declare(strict_types=1);
namespace Brebo\Office\Dashboard;

use PDO;
use RuntimeException;

/**
 * Stores presentation preferences keyed by the authenticated Office operator.
 * No role grants or business data are persisted here.
 */
final class OfficeDashboardPreferences {
  public function __construct(private PDO $db) {}

  public static function schema(): string {
    return 'CREATE TABLE IF NOT EXISTS office_dashboard_preferences ('
      .'operator_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, '
      .'layout_json TEXT NOT NULL, '
      .'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
      .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
  }

  /** @return list<string> */
  public function load(string $authenticatedOperatorId, array $permitted): array {
    if (!ctype_digit($authenticatedOperatorId) || (int)$authenticatedOperatorId <= 0) throw new RuntimeException('Authenticated operator required');
    $statement = $this->db->prepare('SELECT layout_json FROM office_dashboard_preferences WHERE operator_id = :id');
    $statement->execute([':id' => $authenticatedOperatorId]);
    $json = $statement->fetchColumn();
    if (!is_string($json)) return OfficePersonalDashboard::layout([], $permitted);
    $decoded = json_decode($json, true);
    return OfficePersonalDashboard::layout(is_array($decoded) && array_is_list($decoded) ? $decoded : [], $permitted);
  }

  /** @return list<string> */
  public function save(string $authenticatedOperatorId, array $requested, array $permitted): array {
    if (!ctype_digit($authenticatedOperatorId) || (int)$authenticatedOperatorId <= 0) throw new RuntimeException('Authenticated operator required');
    $layout = OfficePersonalDashboard::layout($requested, $permitted);
    $statement = $this->db->prepare(
      'INSERT INTO office_dashboard_preferences (operator_id, layout_json) VALUES (:id, :layout) '
      .'ON DUPLICATE KEY UPDATE layout_json = VALUES(layout_json)'
    );
    $statement->execute([
      ':id' => $authenticatedOperatorId,
      ':layout' => json_encode($layout, JSON_THROW_ON_ERROR),
    ]);
    return $layout;
  }
}
