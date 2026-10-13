<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Opt-in canonical Office project-context record for Calc.
 * Data is entered or synchronized from an approved Office project source;
 * it is never inferred from legacy CRM opportunities.
 */
final class OfficeCalcProjectContextRepository {
  public function __construct(private readonly PDO $db) {}

  /** @return array<string,mixed>|null */
  public function find(int $projectId): ?array {
    if ($projectId < 1) {
      return null;
    }
    $stmt = $this->db->prepare(
      'SELECT project_id, project_code, title, status, client_name, client_organization_id, client_organization_title, project_kind, disciplines_json, description
       FROM office_calc_project_context WHERE project_id = :id'
    );
    $stmt->execute([':id' => $projectId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false || !in_array($row['status'], ['active', 'in_preparation'], true)) {
      return null;
    }
    foreach (['project_code', 'title', 'client_name', 'project_kind'] as $required) {
      if (!is_string($row[$required]) || trim($row[$required]) === '') {
        return null;
      }
    }
    try {
      $disciplines = json_decode((string) $row['disciplines_json'], true, 512, JSON_THROW_ON_ERROR);
    } catch (\\JsonException $e) {
      return null;
    }
    if (!is_array($disciplines) || !array_is_list($disciplines)) {
      return null;
    }
    foreach ($disciplines as $discipline) {
      if (!is_string($discipline)) {
        return null;
      }
    }
    $organization = null;
    if ($row['client_organization_id'] !== null || $row['client_organization_title'] !== null) {
      if (!ctype_digit((string) $row['client_organization_id'])
        || (int) $row['client_organization_id'] < 1
        || trim((string) $row['client_organization_title']) === '') {
        return null;
      }
      $organization = ['id' => (int) $row['client_organization_id'], 'title' => $row['client_organization_title']];
    }
    $buildingsStmt = $this->db->prepare(
      'SELECT building_id, title FROM office_calc_project_context_buildings WHERE project_id = :id ORDER BY building_id'
    );
    $buildingsStmt->execute([':id' => $projectId]);
    $buildings = [];
    foreach ($buildingsStmt->fetchAll(PDO::FETCH_ASSOC) as $building) {
      if ((int) $building['building_id'] < 1 || trim((string) $building['title']) === '') {
        return null;
      }
      $buildings[] = ['id' => (int) $building['building_id'], 'title' => $building['title']];
    }
    return [
      'contract' => 'brebo-office-calc-context-v1',
      'project' => [
        'id' => (int) $row['project_id'],
        'code' => $row['project_code'],
        'title' => $row['title'],
        'status' => $row['status'],
        'client_name' => $row['client_name'],
        'client_organization' => $organization,
        'project_kind' => $row['project_kind'],
        'disciplines' => $disciplines,
        'description' => (string) ($row['description'] ?? ''),
        'buildings' => $buildings,
      ],
    ];
  }
}
