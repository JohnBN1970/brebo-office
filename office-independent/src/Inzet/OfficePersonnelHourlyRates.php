<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/** Manager-scoped financial rates; separate opt-in schema and write path. */
final class OfficePersonnelHourlyRates {
  public function __construct(private PDO $db) {}

  public static function schema(): string {
    return "CREATE TABLE IF NOT EXISTS office_inzet_personnel_hourly_rates (
      person_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      purchase_rate DECIMAL(12,2) NULL,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_inzet_personnel_hourly_rates_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  }

  private function allowed(string $operatorId, int $projectId, int $personId): bool {
    if ($operatorId === '' || $projectId < 1 || $personId < 1) return false;
    $query = $this->db->prepare("SELECT 1 FROM office_inzet_people p
      JOIN office_inzet_project_team t ON t.person_id=p.id AND t.project_id=?
      JOIN office_project_memberships m ON m.project_id=t.project_id
      JOIN office_operator_accounts a ON a.id=m.operator_id
      WHERE p.id=? AND m.operator_id=? AND m.permission='manage' AND a.active=1 LIMIT 1");
    $query->execute([$projectId,$personId,$operatorId]);
    return $query->fetchColumn() !== false;
  }

  public function find(string $operatorId, int $projectId, int $personId): ?array {
    if (!$this->allowed($operatorId,$projectId,$personId)) return null;
    $query = $this->db->prepare('SELECT purchase_rate FROM office_inzet_personnel_hourly_rates WHERE person_id=?');
    $query->execute([$personId]);
    return $query->fetch(PDO::FETCH_ASSOC) ?: ['purchase_rate'=>null];
  }

  public function save(string $operatorId, int $projectId, int $personId, mixed $purchase): bool {
    if (!$this->allowed($operatorId,$projectId,$personId)) return false;
    $parse = static function (mixed $raw): ?string {
      if (!is_string($raw)) throw new \InvalidArgumentException('Invalid rate');
      $raw = trim($raw);
      if ($raw === '') return null;
      $raw = str_replace(',', '.', $raw);
      if (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D', $raw)) throw new \InvalidArgumentException('Invalid hourly rate');
      return number_format((float)$raw, 2, '.', '');
    };
    $purchaseRate = $parse($purchase);
    $query = $this->db->prepare('INSERT INTO office_inzet_personnel_hourly_rates (person_id,purchase_rate) VALUES (?,?)
      ON DUPLICATE KEY UPDATE purchase_rate=VALUES(purchase_rate),sales_rate=VALUES(sales_rate)');
    $query->execute([$personId,$purchaseRate]);
    return true;
  }

  public static function form(int $personId, int $projectId, string $csrf, array $rates): string {
    $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<section><h3>Financieel · inkooptarief</h3><p>Inkooptarief per medewerker, exclusief btw. Verkooptarief wordt bepaald door rol en calculatie.</p>'
      . '<form method="post" action="/office-test/personeel/medewerkers">'
      . '<input type="hidden" name="personnel_action" value="save_hourly_rates">'
      . '<input type="hidden" name="person_id" value="' . $personId . '">'
      . '<input type="hidden" name="project_id" value="' . $projectId . '">'
      . '<input type="hidden" name="csrf_token" value="' . $e($csrf) . '">'
      . '<label>Inkooptarief (€ / uur) <input name="purchase_rate" inputmode="decimal" placeholder="0,00" value="' . $e($rates['purchase_rate'] ?? '') . '"></label> '
      . '<button type="submit">Tarieven opslaan</button></form></section>';
  }
}
