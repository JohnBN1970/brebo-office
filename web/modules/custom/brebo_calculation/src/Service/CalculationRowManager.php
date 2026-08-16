<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/** Guarded mutations for editable calculation rows. */
final class CalculationRowManager {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function add(int $calculationId, string $version, string $paragraphKey, AccountInterface $account): int {
    $this->assertEditable($calculationId, $version, $account);
    $this->assertLeafParagraph($calculationId, $version, $paragraphKey);

    $storage = $this->entityTypeManager->getStorage('node');
    $legacyElementId = $this->resolveLegacyElementId($calculationId, $paragraphKey);
    if ($legacyElementId === NULL) {
      throw new \RuntimeException('No legacy calculation element is mapped to this paragraph yet.');
    }

    $sequence = $this->nextSequence($legacyElementId);
    $transaction = $this->database->startTransaction();
    try {
      $line = $storage->create([
        'type' => 'brebo_calc_line',
        'title' => 'Nieuwe calculatieregel',
        'status' => 1,
        'uid' => $account->id(),
        'field_brebo_calc_element_ref' => ['target_id' => $legacyElementId],
        'field_brebo_line_sequence' => $sequence,
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

      $this->database->insert('brebo_calculation_row_domain')->fields([
        'calc_line_id' => (int) $line->id(),
        'calculation_id' => $calculationId,
        'version' => $version,
        'paragraph_key' => $paragraphKey,
        'rule_type' => 'normal',
        'labour_unit_cost' => 0,
        'material_unit_cost' => 0,
        'equipment_unit_cost' => 0,
        'subcontracting_unit_cost' => 0,
        'other_unit_cost' => 0,
      ])->execute();
      return (int) $line->id();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function duplicate(int $calculationId, string $version, int $lineId, AccountInterface $account): int {
    $this->assertEditable($calculationId, $version, $account);
    $domain = $this->domainRow($calculationId, $version, $lineId);
    $storage = $this->entityTypeManager->getStorage('node');
    $source = $storage->load($lineId);
    if (!$source instanceof NodeInterface || $source->bundle() !== 'brebo_calc_line') {
      throw new \InvalidArgumentException('Calculation row not found.');
    }

    $copy = $source->createDuplicate();
    $copy->setOwnerId((int) $account->id());
    $copy->setTitle($source->label() . ' (kopie)');
    if ($copy->hasField('field_brebo_line_description')) {
      $copy->set('field_brebo_line_description', ((string) $source->get('field_brebo_line_description')->value) . ' (kopie)');
    }
    if ($copy->hasField('field_brebo_line_sequence')) {
      $elementId = (int) $source->get('field_brebo_calc_element_ref')->target_id;
      $copy->set('field_brebo_line_sequence', $this->nextSequence($elementId));
    }

    $transaction = $this->database->startTransaction();
    try {
      $copy->save();
      unset($domain['calc_line_id'], $domain['calculation_id'], $domain['version']);
      $domain['calc_line_id'] = (int) $copy->id();
      $domain['calculation_id'] = $calculationId;
      $domain['version'] = $version;
      $this->database->insert('brebo_calculation_row_domain')->fields($domain)->execute();
      return (int) $copy->id();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function delete(int $calculationId, string $version, int $lineId, AccountInterface $account): void {
    $this->assertEditable($calculationId, $version, $account);
    $this->domainRow($calculationId, $version, $lineId);
    $storage = $this->entityTypeManager->getStorage('node');
    $line = $storage->load($lineId);
    if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line') {
      throw new \InvalidArgumentException('Calculation row not found.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $this->database->delete('brebo_calculation_row_domain')
        ->condition('calc_line_id', $lineId)
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->execute();
      $line->delete();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function move(int $calculationId, string $version, int $lineId, string $targetParagraphKey, AccountInterface $account): void {
    $this->assertEditable($calculationId, $version, $account);
    $this->domainRow($calculationId, $version, $lineId);
    $this->assertLeafParagraph($calculationId, $version, $targetParagraphKey);
    $this->database->update('brebo_calculation_row_domain')
      ->fields(['paragraph_key' => $targetParagraphKey])
      ->condition('calc_line_id', $lineId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute();
  }

  /** @return array<string,mixed> */
  private function domainRow(int $calculationId, string $version, int $lineId): array {
    $row = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('calc_line_id', $lineId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$row) {
      throw new \InvalidArgumentException('Row does not belong to this calculation version.');
    }
    return $row;
  }

  private function assertEditable(int $calculationId, string $version, AccountInterface $account): void {
    if (!$account->hasPermission('edit brebo calculation workbench')) {
      throw new \RuntimeException('Missing calculation workbench edit permission.');
    }
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['locked_at', 'status'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    if (!$row || $row['locked_at'] !== NULL || $row['status'] !== 'draft') {
      throw new \RuntimeException('Only unlocked draft calculation versions may be changed.');
    }
    $calculation = $this->entityTypeManager->getStorage('node')->load($calculationId);
    if (!$calculation instanceof NodeInterface || !$calculation->access('update', $account)) {
      throw new \RuntimeException('Calculation update access denied.');
    }
  }

  private function assertLeafParagraph(int $calculationId, string $version, string $paragraphKey): void {
    $node = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s', ['node_key', 'node_type'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $paragraphKey)
      ->execute()->fetchAssoc();
    if (!$node || $node['node_type'] !== 'paragraph') {
      throw new \InvalidArgumentException('Rows can only be attached to paragraphs.');
    }
    $children = (int) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('parent_key', $paragraphKey)
      ->countQuery()->execute()->fetchField();
    if ($children > 0) {
      throw new \RuntimeException('Only leaf paragraphs may contain calculation rows.');
    }
  }

  private function nextSequence(int $elementId): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_calc_line')
      ->condition('field_brebo_calc_element_ref.target_id', $elementId)
      ->sort('field_brebo_line_sequence', 'DESC')->range(0, 1)->execute();
    $last = $ids ? $storage->load(reset($ids)) : NULL;
    return $last instanceof NodeInterface ? ((int) $last->get('field_brebo_line_sequence')->value + 10) : 10;
  }

  private function resolveLegacyElementId(int $calculationId, string $paragraphKey): ?int {
    if (preg_match('/(?:element|paragraph)[_:-]?(\d+)/i', $paragraphKey, $matches)) {
      $candidate = (int) $matches[1];
      $element = $this->entityTypeManager->getStorage('node')->load($candidate);
      if ($element instanceof NodeInterface
        && $element->bundle() === 'brebo_calc_element'
        && (int) $element->get('field_brebo_calculation_ref')->target_id === $calculationId) {
        return $candidate;
      }
    }
    return NULL;
  }

}
