<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationLineLegacyGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Transitional Drupal implementation for legacy calculation-line writes.
 */
final class DrupalCalculationLineLegacyGateway implements CalculationLineLegacyGatewayInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function create(int $legacyElementId, int $ownerId): int {
    $line = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'brebo_calc_line',
      'title' => 'Nieuwe calculatieregel',
      'status' => 1,
      'uid' => $ownerId,
      'field_brebo_calc_element_ref' => ['target_id' => $legacyElementId],
      'field_brebo_line_sequence' => $this->nextSequence($legacyElementId),
      'field_brebo_line_post_type' => 'Vaste post',
      'field_brebo_cost_category' => 'Overig',
      'field_brebo_line_description' => 'Nieuwe calculatieregel',
      'field_brebo_contract_quantity' => '1.0000',
      'field_brebo_unit' => 'post',
      'field_brebo_unit_price' => '0.0000',
      'field_brebo_hours_input_mode' => 'Normuren',
      'field_brebo_line_status' => 'Niet beoordeeld',
      'field_brebo_line_type' => 'Calculatieregel',
      'field_brebo_note_visibility' => 'Intern',
    ]);
    $line->save();
    return (int) $line->id();
  }

  public function updateQuickEntry(int $lineId, string $description, string $unit, float $quantity, array $unitCosts): void {
    $line = $this->line($lineId);
    $line->setTitle($description);
    $this->setIfPresent($line, 'field_brebo_line_description', $description);
    $this->setIfPresent($line, 'field_brebo_contract_quantity', number_format($quantity, 4, '.', ''));
    $this->setIfPresent($line, 'field_brebo_unit', $unit);
    $this->setIfPresent($line, 'field_brebo_unit_price', number_format(array_sum($unitCosts), 4, '.', ''));
    $line->setNewRevision(TRUE);
    $line->setRevisionLogMessage('Calculatieregel via quick-entry in de calculatiewerkbank bijgewerkt.');
    $line->save();
  }

  public function duplicate(int $lineId, int $ownerId): int {
    $source = $this->line($lineId);
    $copy = $source->createDuplicate();
    $copy->setOwnerId($ownerId);
    $copy->setTitle($source->label() . ' (kopie)');
    if ($copy->hasField('field_brebo_line_description')) {
      $copy->set('field_brebo_line_description', ((string) $source->get('field_brebo_line_description')->value) . ' (kopie)');
    }
    if ($copy->hasField('field_brebo_line_sequence')) {
      $elementId = (int) $source->get('field_brebo_calc_element_ref')->target_id;
      $copy->set('field_brebo_line_sequence', $this->nextSequence($elementId));
    }
    $copy->save();
    return (int) $copy->id();
  }

  public function delete(int $lineId): void {
    $this->line($lineId)->delete();
  }

  public function move(int $lineId, int $targetElementId): void {
    $line = $this->line($lineId);
    $line->set('field_brebo_calc_element_ref', ['target_id' => $targetElementId]);
    if ($line->hasField('field_brebo_line_sequence')) {
      $line->set('field_brebo_line_sequence', $this->nextSequence($targetElementId));
    }
    $line->setNewRevision(TRUE);
    $line->setRevisionLogMessage('Calculatieregel via nieuwe calculatiewerkbank naar andere paragraaf verplaatst.');
    $line->save();
  }

  public function nextSequence(int $elementId): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_line')
      ->condition('field_brebo_calc_element_ref.target_id', $elementId)
      ->sort('field_brebo_line_sequence', 'DESC')->range(0, 1)->execute();
    $last = $ids ? $storage->load(reset($ids)) : NULL;
    return $last instanceof NodeInterface ? ((int) $last->get('field_brebo_line_sequence')->value + 10) : 10;
  }

  public function resolveElementId(int $calculationId, string $paragraphKey): ?int {
    if (!preg_match('/(?:element|paragraph)[_:-]?(\d+)/i', $paragraphKey, $matches)) {
      return NULL;
    }
    $candidate = (int) $matches[1];
    $element = $this->entityTypeManager->getStorage('node')->load($candidate);
    if ($element instanceof NodeInterface
      && $element->bundle() === 'brebo_calc_element'
      && (int) $element->get('field_brebo_calculation_ref')->target_id === $calculationId) {
      return $candidate;
    }
    return NULL;
  }

  private function line(int $lineId): NodeInterface {
    $line = $this->entityTypeManager->getStorage('node')->load($lineId);
    if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line') {
      throw new \InvalidArgumentException('Calculation row not found.');
    }
    return $line;
  }

  private function setIfPresent(NodeInterface $line, string $field, mixed $value): void {
    if ($line->hasField($field)) {
      $line->set($field, $value);
    }
  }

}
