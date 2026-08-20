<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Core\Database\Connection;

/** Prevents duplicate glass exports and detects stale source checksums. */
final class GlassCalculationLinkGuard {
  public function __construct(
    private readonly Connection $database,
    private readonly GlassPositionRepository $positions,
  ) {}

  public function assertNotExported(int $positionId, int $calculationId, string $version): void {
    if (!$this->database->schema()->tableExists('brebo_calculation_row_domain')) {
      throw new \RuntimeException('Calculatiebronkoppelingen zijn nog niet geinstalleerd.');
    }
    $count = (int) $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('source_domain', 'brebo_glass_position')
      ->condition('source_reference', (string) $positionId)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($count > 0) {
      throw new \RuntimeException('Deze glaspositie is al opgenomen in deze calculatieversie. Dubbele export is geblokkeerd.');
    }
  }

  /** @return array{state:string,current_checksum:string,exported_checksums:array<int,string>,line_ids:array<int,int>,message:string} */
  public function status(int $positionId, int $calculationId, string $version): array {
    $position = $this->positions->find($positionId);
    if (!$position) {
      throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    }
    $current = trim((string) ($position['approval_checksum'] ?? ''));
    $rows = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['calc_line_id', 'source_checksum'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('source_domain', 'brebo_glass_position')
      ->condition('source_reference', (string) $positionId)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    if ($rows === []) {
      return ['state'=>'not_exported','current_checksum'=>$current,'exported_checksums'=>[],'line_ids'=>[],'message'=>'Glaspositie is nog niet in deze calculatieversie opgenomen.'];
    }
    $checksums=[];$lineIds=[];
    foreach ($rows as $row) {
      $checksum=trim((string) ($row['source_checksum'] ?? ''));
      if ($checksum !== '') $checksums[$checksum]=$checksum;
      $lineIds[]=(int) $row['calc_line_id'];
    }
    $exported=array_values($checksums);
    $fresh=$current !== '' && count($exported) === 1 && hash_equals($current, $exported[0]);
    return [
      'state'=>$fresh?'current':'stale',
      'current_checksum'=>$current,
      'exported_checksums'=>$exported,
      'line_ids'=>$lineIds,
      'message'=>$fresh?'Calculatie is gebaseerd op de actuele technische glasvrijgave.':'Bronobject gewijzigd — hercalculatie vereist.',
    ];
  }
}
