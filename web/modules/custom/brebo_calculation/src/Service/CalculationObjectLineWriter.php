<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/** Writes object-derived rows through the canonical BREBO calculation model. */
final class CalculationObjectLineWriter {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CalculationRowManager $rowManager,
  ) {}

  /**
   * Creates one traceable calculation row for an object-domain source.
   *
   * @param array<string, float|int> $unitCosts
   *   Supported keys: labour, material, equipment, subcontracting, other.
   */
  public function write(
    int $calculationId,
    string $version,
    string $paragraphKey,
    string $description,
    float $quantity,
    string $unit,
    array $unitCosts,
    string $sourceDomain,
    string $sourceReference,
    AccountInterface $account,
  ): int {
    $description = trim($description);
    $unit = trim($unit);
    $sourceDomain = trim($sourceDomain);
    $sourceReference = trim($sourceReference);
    if ($description === '' || $unit === '' || $sourceDomain === '' || $sourceReference === '') {
      throw new \InvalidArgumentException('Object calculation rows require description, unit and source traceability.');
    }
    if ($quantity < 0) {
      throw new \InvalidArgumentException('Calculation quantity cannot be negative.');
    }

    $lineId = $this->rowManager->add($calculationId, $version, $paragraphKey, $account);
    $storage = $this->entityTypeManager->getStorage('node');
    $line = $storage->load($lineId);
    if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line') {
      throw new \RuntimeException('Canonical calculation row could not be loaded after creation.');
    }

    $costs = [
      'labour_unit_cost' => $this->cost($unitCosts, 'labour'),
      'material_unit_cost' => $this->cost($unitCosts, 'material'),
      'equipment_unit_cost' => $this->cost($unitCosts, 'equipment'),
      'subcontracting_unit_cost' => $this->cost($unitCosts, 'subcontracting'),
      'other_unit_cost' => $this->cost($unitCosts, 'other'),
    ];
    $unitPrice = array_sum($costs);

    $transaction = $this->database->startTransaction();
    try {
      $line->setTitle($description);
      $this->setIfPresent($line, 'field_brebo_line_description', $description);
      $this->setIfPresent($line, 'field_brebo_contract_quantity', number_format($quantity, 4, '.', ''));
      $this->setIfPresent($line, 'field_brebo_unit', $unit);
      $this->setIfPresent($line, 'field_brebo_unit_price', number_format($unitPrice, 4, '.', ''));
      $this->setIfPresent($line, 'field_brebo_line_status', 'Niet beoordeeld');
      $this->setIfPresent($line, 'field_brebo_line_type', 'Calculatieregel');
      $this->setIfPresent($line, 'field_brebo_note_visibility', 'Intern');
      $this->setIfPresent($line, 'field_brebo_line_note', sprintf('Bron: %s:%s', $sourceDomain, $sourceReference));
      $line->setNewRevision(TRUE);
      $line->setRevisionLogMessage(sprintf('Objectgestuurde calculatieregel uit %s:%s.', $sourceDomain, $sourceReference));
      $line->save();

      $costs['source_domain'] = $sourceDomain;
      $costs['source_reference'] = $sourceReference;
      $this->database->update('brebo_calculation_row_domain')
        ->fields($this->supportedDomainFields($costs))
        ->condition('calc_line_id', $lineId)
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      try {
        $this->rowManager->delete($calculationId, $version, $lineId, $account);
      }
      catch (\Throwable) {
        // Preserve the original exception; rollback cleanup is best effort.
      }
      throw $e;
    }

    return $lineId;
  }

  /** @param array<string, float|int> $costs */
  private function cost(array $costs, string $key): float {
    $value = (float) ($costs[$key] ?? 0.0);
    if ($value < 0) {
      throw new \InvalidArgumentException('Unit costs cannot be negative.');
    }
    return $value;
  }

  private function setIfPresent(NodeInterface $line, string $field, mixed $value): void {
    if ($line->hasField($field)) {
      $line->set($field, $value);
    }
  }

  /** @param array<string, mixed> $values */
  private function supportedDomainFields(array $values): array {
    $schema = $this->database->schema();
    $supported = [];
    foreach ($values as $field => $value) {
      if ($schema->fieldExists('brebo_calculation_row_domain', $field)) {
        $supported[$field] = $value;
      }
    }
    return $supported;
  }

}
