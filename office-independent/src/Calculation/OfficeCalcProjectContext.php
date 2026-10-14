<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Strict v1 Calc context boundary. A caller must load real project data from
 * the independent Office project repository; no Drupal fallback is permitted.
 */
final class OfficeCalcProjectContext {
  public static function fromRecord(int $requestedId, array $record): array {
    if ($requestedId < 1 || ($record['id'] ?? null) !== $requestedId) {
      throw new \DomainException('Project context unavailable.');
    }
    foreach (['code', 'title', 'status', 'client_name', 'project_kind', 'description'] as $key) {
      if (!isset($record[$key]) || !is_string($record[$key])) {
        throw new \DomainException('Project context incomplete.');
      }
    }
    if (trim($record['code']) === '' || trim($record['title']) === '' || trim($record['status']) === '') {
      throw new \DomainException('Project context incomplete.');
    }
    if (!isset($record['disciplines'], $record['buildings'])
      || !is_array($record['disciplines']) || !array_is_list($record['disciplines'])
      || !is_array($record['buildings']) || !array_is_list($record['buildings'])) {
      throw new \DomainException('Project context incomplete.');
    }
    foreach ($record['disciplines'] as $discipline) {
      if (!is_string($discipline)) {
        throw new \DomainException('Invalid discipline.');
      }
    }
    foreach ($record['buildings'] as $building) {
      if (!is_array($building) || !isset($building['id'], $building['title'])
        || !is_int($building['id']) || $building['id'] < 1 || !is_string($building['title'])
        || trim($building['title']) === '') {
        throw new \DomainException('Invalid building.');
      }
    }
    $client = $record['client_organization'] ?? null;
    if ($client !== null && (!is_array($client) || !isset($client['id'], $client['title'])
      || !is_int($client['id']) || $client['id'] < 1 || !is_string($client['title'])
      || trim($client['title']) === '')) {
      throw new \DomainException('Invalid client organization.');
    }
    return [
      'contract' => 'brebo-office-calc-context-v1',
      'project' => [
        'id' => $requestedId,
        'code' => $record['code'],
        'title' => $record['title'],
        'status' => $record['status'],
        'client_name' => $record['client_name'],
        'client_organization' => $client,
        'project_kind' => $record['project_kind'],
        'disciplines' => $record['disciplines'],
        'description' => $record['description'],
        'buildings' => $record['buildings'],
      ],
    ];
  }
}
