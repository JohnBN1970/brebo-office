<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use InvalidArgumentException;
use UnexpectedValueException;

/** Canonical hierarchy for zones, building parts, elements and components. */
final class BuildingObjectRepository {
  private const TYPES = ['zone','building_part','element','component'];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  public function create(int $buildingNid, string $type, string $code, string $label, ?int $parentId = NULL, array $metadata = []): int {
    $this->assertBuilding($buildingNid);
    $type = trim($type); $code = trim($code); $label = trim($label);
    if (!in_array($type, self::TYPES, TRUE)) throw new InvalidArgumentException('Unknown BREBO building object type.');
    if ($code === '' || $label === '') throw new InvalidArgumentException('Building object code and label are required.');
    $this->ensureStorage();
    if ($parentId !== NULL) {
      $parent = $this->load($parentId);
      if ((int) $parent['building_nid'] !== $buildingNid) throw new InvalidArgumentException('Parent object belongs to another building.');
      $this->assertParentType($type, (string) $parent['object_type']);
    }
    $existing = $this->database->select('brebo_building_object', 'o')->fields('o', ['id'])
      ->condition('building_nid', $buildingNid)->condition('object_code', $code)->execute()->fetchField();
    if ($existing) throw new UnexpectedValueException('Building object code already exists in this building.');
    $now = $this->time->getRequestTime();
    return (int) $this->database->insert('brebo_building_object')->fields([
      'building_nid' => $buildingNid,
      'parent_id' => $parentId,
      'object_type' => $type,
      'object_code' => $code,
      'label' => $label,
      'status' => trim((string) ($metadata['status'] ?? 'active')) ?: 'active',
      'classification' => trim((string) ($metadata['classification'] ?? '')) ?: NULL,
      'source' => trim((string) ($metadata['source'] ?? '')) ?: NULL,
      'source_ref' => trim((string) ($metadata['source_ref'] ?? '')) ?: NULL,
      'metadata' => json_encode($metadata['metadata'] ?? [], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  public function load(int $id): array {
    $this->ensureStorage();
    $row = $this->database->select('brebo_building_object', 'o')->fields('o')->condition('id', $id)->execute()->fetchAssoc();
    if ($row === FALSE) throw new UnexpectedValueException('BREBO building object does not exist.');
    return $row;
  }

  public function tree(int $buildingNid): array {
    $this->assertBuilding($buildingNid); $this->ensureStorage();
    $rows = $this->database->select('brebo_building_object', 'o')->fields('o')->condition('building_nid', $buildingNid)
      ->orderBy('parent_id', 'ASC')->orderBy('object_type', 'ASC')->orderBy('object_code', 'ASC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $byParent = []; foreach ($rows as $row) $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
    $build = function(int $parent) use (&$build, $byParent): array { $out=[]; foreach($byParent[$parent]??[] as $row){$row['children']=$build((int)$row['id']);$out[]=$row;} return $out; };
    return $build(0);
  }

  public function ancestors(int $objectId): array {
    $current = $this->load($objectId); $out = [$current];
    while (!empty($current['parent_id'])) { $current = $this->load((int) $current['parent_id']); array_unshift($out, $current); }
    return $out;
  }

  private function assertParentType(string $type, string $parentType): void {
    $allowed = [
      'zone' => [],
      'building_part' => ['zone'],
      'element' => ['zone','building_part'],
      'component' => ['zone','building_part','element'],
    ];
    if (!in_array($parentType, $allowed[$type], TRUE)) throw new InvalidArgumentException(sprintf('%s cannot be placed below %s.', $type, $parentType));
  }

  private function assertBuilding(int $buildingNid): void {
    $node = $this->entityTypeManager->getStorage('node')->load($buildingNid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_building') throw new InvalidArgumentException(sprintf('Node %d is not a BREBO building.', $buildingNid));
  }

  private function ensureStorage(): void {
    $schema = $this->database->schema(); if ($schema->tableExists('brebo_building_object')) return;
    $schema->createTable('brebo_building_object', [
      'description' => 'Canonical hierarchical building objects used across BREBO Office.',
      'fields' => [
        'id' => ['type'=>'serial','unsigned'=>TRUE,'not null'=>TRUE],
        'building_nid' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'parent_id' => ['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'object_type' => ['type'=>'varchar','length'=>32,'not null'=>TRUE],
        'object_code' => ['type'=>'varchar','length'=>128,'not null'=>TRUE],
        'label' => ['type'=>'varchar','length'=>255,'not null'=>TRUE],
        'status' => ['type'=>'varchar','length'=>32,'not null'=>TRUE,'default'=>'active'],
        'classification' => ['type'=>'varchar','length'=>128,'not null'=>FALSE],
        'source' => ['type'=>'varchar','length'=>64,'not null'=>FALSE],
        'source_ref' => ['type'=>'varchar','length'=>255,'not null'=>FALSE],
        'metadata' => ['type'=>'text','size'=>'big','not null'=>TRUE],
        'created' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'changed' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['building_object_code' => ['building_nid','object_code']],
      'indexes' => ['building_nid'=>['building_nid'],'parent_id'=>['parent_id'],'object_type'=>['object_type'],'classification'=>['classification']],
    ]);
  }
}
