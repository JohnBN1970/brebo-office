<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Validates a portable CRM opportunity export before any import.
 * Existing Drupal node IDs remain external legacy IDs.
 */
final class WebsiteCrmLegacyExportValidator {

  /**
   * @param list<array<string,mixed>> $rows
   * @return array{records:int,website_request_ids:list<string>,legacy_node_ids:list<int>}
   */
  public function validate(array $rows): array {
    $nodeIds = [];
    $requestIds = [];
    foreach ($rows as $row) {
      $nodeId = $row['legacy_node_id'] ?? null;
      if (!is_int($nodeId) || $nodeId < 1 || isset($nodeIds[$nodeId])) {
        throw new \InvalidArgumentException('Missing or duplicate legacy CRM node ID.');
      }
      $nodeIds[$nodeId] = true;
      if (!is_string($row['title'] ?? null) || trim($row['title']) === ''
        || !is_string($row['stage'] ?? null) || trim($row['stage']) === ''
        || !is_int($row['owner_uid'] ?? null) || $row['owner_uid'] < 1) {
        throw new \InvalidArgumentException('Incomplete legacy CRM opportunity.');
      }
      $requestId = $row['website_request_id'] ?? null;
      if ($requestId !== null) {
        if (!is_string($requestId) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $requestId)) {
          throw new \InvalidArgumentException('Legacy website lead must have a full UUID.');
        }
        $requestId = strtolower($requestId);
        if (isset($requestIds[$requestId])) {
          throw new \InvalidArgumentException('Duplicate website request ID in CRM export.');
        }
        $requestIds[$requestId] = true;
      }
    }
    return [
      'records' => count($rows),
      'website_request_ids' => array_keys($requestIds),
      'legacy_node_ids' => array_keys($nodeIds),
    ];
  }
}
