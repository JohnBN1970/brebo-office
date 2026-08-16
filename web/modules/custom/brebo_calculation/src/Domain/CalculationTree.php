<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Validates hierarchy and rolls row costs up through the calculation tree. */
final class CalculationTree {

  /** @var array<string, StructureNode> */
  private array $nodes = [];

  /** @var array<int, CalculationRow> */
  private array $rows = [];

  /** @param iterable<StructureNode> $nodes @param iterable<CalculationRow> $rows */
  public function __construct(iterable $nodes, iterable $rows) {
    foreach ($nodes as $node) {
      if (isset($this->nodes[$node->id])) {
        throw new \InvalidArgumentException('Duplicate calculation structure node id.');
      }
      $this->nodes[$node->id] = $node;
    }
    foreach ($this->nodes as $node) {
      if ($node->parentId !== NULL && !isset($this->nodes[$node->parentId])) {
        throw new \InvalidArgumentException('Calculation structure parent does not exist.');
      }
    }
    foreach ($rows as $row) {
      if (isset($this->rows[$row->legacyLineId])) {
        throw new \InvalidArgumentException('Duplicate calculation line id.');
      }
      $paragraph = $this->nodes[$row->paragraphId] ?? NULL;
      if (!$paragraph || $paragraph->type !== StructureNodeType::Paragraph) {
        throw new \InvalidArgumentException('Calculation rows must reference a paragraph.');
      }
      if ($this->hasChildren($paragraph->id)) {
        throw new \InvalidArgumentException('Only leaf paragraphs may contain calculation rows.');
      }
      $this->rows[$row->legacyLineId] = $row;
    }
  }

  public function totalForNode(string $nodeId): float {
    if (!isset($this->nodes[$nodeId])) {
      throw new \InvalidArgumentException('Unknown calculation structure node.');
    }
    $descendants = $this->descendantIds($nodeId);
    $descendants[$nodeId] = TRUE;
    $total = 0.0;
    foreach ($this->rows as $row) {
      if (isset($descendants[$row->paragraphId])) {
        $total += $row->directCost();
      }
    }
    return $total;
  }

  public function inheritedLocation(string $nodeId): ?string {
    $visited = [];
    $current = $this->nodes[$nodeId] ?? NULL;
    while ($current !== NULL) {
      if (isset($visited[$current->id])) {
        throw new \LogicException('Cycle detected in calculation structure.');
      }
      $visited[$current->id] = TRUE;
      if ($current->locationRef !== NULL) {
        return $current->locationRef;
      }
      $current = $current->parentId !== NULL ? ($this->nodes[$current->parentId] ?? NULL) : NULL;
    }
    return NULL;
  }

  private function hasChildren(string $nodeId): bool {
    foreach ($this->nodes as $node) {
      if ($node->parentId === $nodeId) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /** @return array<string, bool> */
  private function descendantIds(string $nodeId): array {
    $result = [];
    $frontier = [$nodeId];
    while ($frontier !== []) {
      $parent = array_pop($frontier);
      foreach ($this->nodes as $node) {
        if ($node->parentId === $parent && !isset($result[$node->id])) {
          $result[$node->id] = TRUE;
          $frontier[] = $node->id;
        }
      }
    }
    return $result;
  }

}
