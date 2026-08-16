<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/**
 * Immutable calculation tree node.
 *
 * Main groups live at depth 0. Paragraphs may be nested to depth 3. Only a
 * leaf paragraph may own calculation rows; parent nodes are totalizers.
 */
final readonly class StructureNode {

  public function __construct(
    public string $id,
    public StructureNodeType $type,
    public string $code,
    public string $label,
    public int $depth,
    public int $sortOrder = 0,
    public ?string $parentId = NULL,
    public ?string $locationRef = NULL,
  ) {
    if ($this->id === '' || $this->label === '') {
      throw new \InvalidArgumentException('Structure node id and label are required.');
    }
    if ($this->type === StructureNodeType::MainGroup && $this->depth !== 0) {
      throw new \InvalidArgumentException('A main group must have depth 0.');
    }
    if ($this->type === StructureNodeType::Paragraph && ($this->depth < 1 || $this->depth > 3)) {
      throw new \InvalidArgumentException('A paragraph depth must be between 1 and 3.');
    }
    if ($this->depth > 0 && $this->parentId === NULL) {
      throw new \InvalidArgumentException('Nested nodes require a parent.');
    }
  }

}
