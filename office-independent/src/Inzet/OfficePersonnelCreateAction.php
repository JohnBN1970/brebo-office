<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/** Creates an Office person without changing the OnSite activation or identity mapping. */
final class OfficePersonnelCreateAction {
  public function __construct(private PDO $db) {}

  public function create(array $post, string $operatorId, string $sessionId, string $secret): ?int {
    if (!OfficeInzetPersonnelProfileCsrf::verify($post['csrf_token'] ?? null, $sessionId, $secret)) {
      throw new \DomainException('Invalid personnel creation token');
    }
    $projectId = filter_var($post['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $name = $post['display_name'] ?? null;
    $number = $post['personnel_number'] ?? '';
    if (!is_int($projectId) || !is_string($name) || !is_string($number)
      || $operatorId === '' || strlen($operatorId) > 128) throw new \InvalidArgumentException('Invalid personnel creation');
    $name = trim($name);
    $number = trim($number);
    if ($name === '' || mb_strlen($name) > 200 || mb_strlen($number) > 64) {
      throw new \InvalidArgumentException('Invalid personnel identity');
    }
    $this->db->beginTransaction();
    try {
      $q = $this->db->prepare("SELECT 1 FROM office_project_memberships m
        INNER JOIN office_operator_accounts a ON a.id=m.operator_id
        INNER JOIN office_projects p ON p.id=m.project_id
        WHERE m.operator_id=? AND m.project_id=? AND m.permission='manage' AND a.active=1 LIMIT 1");
      $q->execute([$operatorId,$projectId]);
      if ($q->fetchColumn() === false) {
        $this->db->rollBack();
        return null;
      }
      $insert = $this->db->prepare('INSERT INTO office_inzet_people (display_name,personnel_number,active) VALUES (?,?,1)');
      $insert->execute([$name,$number === '' ? null : $number]);
      $personId = (int)$this->db->lastInsertId();
      if ($personId < 1) throw new \RuntimeException('Employee ID unavailable');
      $team = $this->db->prepare('INSERT INTO office_inzet_project_team (project_id,person_id) VALUES (?,?)');
      $team->execute([$projectId,$personId]);
      $this->db->commit();
      return $personId;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
