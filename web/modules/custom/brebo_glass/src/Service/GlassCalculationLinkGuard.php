<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_glass\Contract\GlassCalculationLinkRepositoryInterface;

/** Prevents duplicate glass exports and detects stale source checksums. */
final class GlassCalculationLinkGuard {
  public function __construct(
    private readonly GlassCalculationLinkRepositoryInterface $repository,
    private readonly GlassPositionRepository $positions,
  ) {}

  public function assertNotExported(int $positionId, int $calculationId, string $version): void {
    if (!$this->repository->isAvailable()) {
      throw new \RuntimeException('Calculatiebronkoppelingen zijn nog niet geinstalleerd.');
    }
    $count = $this->repository->countLinks($positionId, $calculationId, $version);
    if ($count > 0) {
      throw new \RuntimeException('Deze glaspositie is al opgenomen in deze calculatieversie. Dubbele export is geblokkeerd.');
    }
  }

  /** @return array{state:string,current_checksum:string,exported_checksums:array<int,string>,row_ids:array<int,int>,message:string} */
  public function status(int $positionId, int $calculationId, string $version): array {
    $position = $this->positions->find($positionId);
    if (!$position) {
      throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    }
    $current = trim((string) ($position['approval_checksum'] ?? ''));
    $rows = $this->repository->links($positionId, $calculationId, $version);
    if ($rows === []) {
      return ['state'=>'not_exported','current_checksum'=>$current,'exported_checksums'=>[],'row_ids'=>[],'message'=>'Glaspositie is nog niet in deze calculatieversie opgenomen.'];
    }
    $checksums=[];$rowIds=[];
    foreach ($rows as $row) {
      $checksum=trim((string) ($row['source_checksum'] ?? ''));
      if ($checksum !== '') $checksums[$checksum]=$checksum;
      $rowIds[]=(int) $row['row_id'];
    }
    $exported=array_values($checksums);
    $fresh=$current !== '' && count($exported) === 1 && hash_equals($current, $exported[0]);
    return [
      'state'=>$fresh?'current':'stale',
      'current_checksum'=>$current,
      'exported_checksums'=>$exported,
      'row_ids'=>$rowIds,
      'message'=>$fresh?'Calculatie is gebaseerd op de actuele technische glasvrijgave.':'Bronobject gewijzigd — hercalculatie vereist.',
    ];
  }
}
