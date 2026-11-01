<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Personnel details belong to the existing person identity.
 * Every read/write requires verified project-scoped manage access.
 */
final class OfficePersonnelDetailsRepository {
  public function __construct(private PDO $db) {}

  private function authorized(string $operatorId, int $projectId, int $personId): bool {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId < 1 || $personId < 1) {
      throw new \InvalidArgumentException('Verified operator, project and employee required');
    }
    $q = $this->db->prepare("SELECT 1 FROM office_inzet_people p
      INNER JOIN office_inzet_project_team t ON t.person_id = p.id AND t.project_id = ?
      INNER JOIN office_project_memberships m ON m.project_id = t.project_id
      INNER JOIN office_operator_accounts a ON a.id = m.operator_id
      WHERE p.id = ? AND m.operator_id = ? AND m.permission = 'manage' AND a.active = 1 LIMIT 1");
    $q->execute([$projectId, $personId, $operatorId]);
    return $q->fetchColumn() !== false;
  }

  /** @return array<string,mixed>|null */
  public function find(string $operatorId, int $projectId, int $personId): ?array {
    if (!$this->authorized($operatorId, $projectId, $personId)) return null;
    $q = $this->db->prepare('SELECT phone_country, phone_e164, nationality, preferred_language, vca_expiry FROM office_inzet_personnel_details WHERE person_id = ?');
    $q->execute([$personId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    $skills = $this->db->prepare('SELECT skill_code FROM office_inzet_personnel_skills WHERE person_id = ? ORDER BY skill_code');
    $skills->execute([$personId]);
    return ($row ?: ['phone_country'=>null,'phone_e164'=>null,'nationality'=>null,'preferred_language'=>null,'vca_expiry'=>null])
      + ['skills'=>$skills->fetchAll(PDO::FETCH_COLUMN)];
  }

  /** @param array<string,mixed> $data */
  public function save(string $operatorId, int $projectId, int $personId, array $data): bool {
    if (!$this->authorized($operatorId, $projectId, $personId)) return false;
    $countries = ['NL'=>'+31','BE'=>'+32','DE'=>'+49','PL'=>'+48','RO'=>'+40','TR'=>'+90'];
    $country = $data['phone_country'] ?? null;
    $phone = $data['phone_number'] ?? '';
    if (!is_string($phone) || !is_string($country) || !isset($countries[$country])) throw new \InvalidArgumentException('Invalid phone country or number');
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (!is_string($digits)) throw new \InvalidArgumentException('Invalid phone number');
    $e164 = null;
    if ($digits !== '') {
      $dial = substr($countries[$country], 1);
      if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
      if (str_starts_with($digits, $dial)) $digits = substr($digits, strlen($dial));
      elseif (str_starts_with($phone, '+') || str_starts_with($phone, '00')) throw new \InvalidArgumentException('Phone country does not match country code');
      $digits = ltrim($digits, '0');
      if (!preg_match('/^[0-9]{6,12}$/D', $digits)) throw new \InvalidArgumentException('Invalid international phone number');
      $e164 = $countries[$country] . $digits;
      if (strlen($e164) > 16) throw new \InvalidArgumentException('Phone number too long');
    }
    $nationality = $data['nationality'] ?? '';
    $language = $data['preferred_language'] ?? '';
    $expiry = $data['vca_expiry'] ?? '';
    $skills = $data['skills'] ?? [];
    if (!is_string($nationality) || ($nationality !== '' && !isset($countries[$nationality]))
      || !is_string($language) || !in_array($language, ['nl','en','pl','ro','tr','de'], true)
      || !is_string($expiry) || !is_array($skills)) throw new \InvalidArgumentException('Invalid personnel details');
    if ($expiry !== '') {
      $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $expiry);
      if (!$date || $date->format('Y-m-d') !== $expiry) throw new \InvalidArgumentException('Invalid VCA expiry');
    }
    $allowed = ['Glaszetten','Kozijnmontage','Timmeren','Schilderen','Kitten','Projectleiding'];
    if (count($skills) > count($allowed)) throw new \InvalidArgumentException('Too many skills');
    foreach ($skills as $skill) if (!is_string($skill) || !in_array($skill, $allowed, true)) throw new \InvalidArgumentException('Invalid skill');
    $skills = array_values(array_unique($skills));
    $this->db->beginTransaction();
    try {
      $exists = $this->db->prepare('SELECT person_id FROM office_inzet_personnel_details WHERE person_id = ? FOR UPDATE');
      $exists->execute([$personId]);
      if ($exists->fetchColumn() !== false) {
        $q = $this->db->prepare('UPDATE office_inzet_personnel_details SET phone_country=?,phone_e164=?,nationality=?,preferred_language=?,vca_expiry=? WHERE person_id=?');
        $q->execute([$country,$e164,$nationality ?: null,$language,$expiry ?: null,$personId]);
      } else {
        $q = $this->db->prepare('INSERT INTO office_inzet_personnel_details (person_id,phone_country,phone_e164,nationality,preferred_language,vca_expiry) VALUES (?,?,?,?,?,?)');
        $q->execute([$personId,$country,$e164,$nationality ?: null,$language,$expiry ?: null]);
      }
      $delete = $this->db->prepare('DELETE FROM office_inzet_personnel_skills WHERE person_id = ?');
      $delete->execute([$personId]);
      $insert = $this->db->prepare('INSERT INTO office_inzet_personnel_skills (person_id,skill_code) VALUES (?,?)');
      foreach ($skills as $skill) $insert->execute([$personId,$skill]);
      $this->db->commit();
      return true;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
