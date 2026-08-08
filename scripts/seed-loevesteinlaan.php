<?php

declare(strict_types=1);

use Drupal\node\Entity\Node;

/**
 * Idempotently creates the Loevesteinlaan pilot work package and gates.
 */

$singleId = static function (string $entity_type, array $conditions): int {
  $query = \Drupal::entityQuery($entity_type)->accessCheck(FALSE);
  foreach ($conditions as [$field, $value, $operator]) {
    $query->condition($field, $value, $operator);
  }
  $ids = $query->execute();
  if (!$ids) {
    throw new RuntimeException("Vereist object niet gevonden: $entity_type / " . json_encode($conditions));
  }
  return (int) reset($ids);
};

$project_id = $singleId('node', [
  ['type', 'brebo_project', '='],
  ['field_brebo_project_code', 'LOE-2026-001', '='],
]);
$cluster_id = $singleId('node', [
  ['type', 'brebo_cluster', '='],
  ['field_brebo_cluster_code', 'CL-001', '='],
]);
$dwelling_id = $singleId('node', [
  ['type', 'brebo_dwelling', '='],
  ['field_brebo_dwelling_code', 'W-001', '='],
]);
$user_id = $singleId('user', [
  ['name', 'john.boon', '='],
]);

$position_ids = \Drupal::entityQuery('node')
  ->accessCheck(FALSE)
  ->condition('type', 'brebo_product_position')
  ->condition('field_brebo_dwelling_ref.target_id', $dwelling_id)
  ->condition('field_brebo_position_code', 'L91.%', 'LIKE')
  ->sort('field_brebo_position_code')
  ->execute();

if (count($position_ids) !== 23) {
  throw new RuntimeException('Verwacht 23 gecodeerde productposities, gevonden: ' . count($position_ids));
}

$package_ids = \Drupal::entityQuery('node')
  ->accessCheck(FALSE)
  ->condition('type', 'brebo_work_package')
  ->condition('field_brebo_project_ref.target_id', $project_id)
  ->condition('field_brebo_package_code', 'WP-GLAS-001')
  ->execute();

$package = $package_ids
  ? Node::load(reset($package_ids))
  : Node::create(['type' => 'brebo_work_package', 'status' => 1, 'uid' => $user_id]);

$package->setTitle('Beglazing vergelijkingswoning');
$package->set('field_brebo_package_code', 'WP-GLAS-001');
$package->set('field_brebo_project_ref', ['target_id' => $project_id]);
$package->set('field_brebo_cluster_ref', ['target_id' => $cluster_id]);
$package->set('field_brebo_package_positions', array_map(
  static fn (int|string $id): array => ['target_id' => (int) $id],
  array_values($position_ids),
));
$package->set('field_brebo_discipline', 'Beglazing');
$package->set('field_brebo_package_status', 'Voorbereiding');
$package->set('field_brebo_package_owner', ['target_id' => $user_id]);
$package->set('field_brebo_package_scope', [
  'value' => 'Documentcontrole, voorbereiding, uitvoering en kwaliteitsborging van 23 gecodeerde glasposities in de vergelijkingswoning.',
  'format' => 'plain_text',
]);
$package->setNewRevision(TRUE);
$package->setRevisionLogMessage('Pilotwerkpakket Loevesteinlaan ingericht.');
$package->save();

$created = 0;
$updated = 0;
foreach (['Documenten', 'Calculatie', 'Financiën', 'Veiligheid', 'Kwaliteit', 'Planning', 'Inkoop'] as $gate_type) {
  $gate_ids = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', 'brebo_release_gate')
    ->condition('field_brebo_package_ref.target_id', $package->id())
    ->condition('field_brebo_gate_type', $gate_type)
    ->execute();

  if ($gate_ids) {
    $gate = Node::load(reset($gate_ids));
    $updated++;
  }
  else {
    $gate = Node::create(['type' => 'brebo_release_gate', 'status' => 1, 'uid' => $user_id]);
    $created++;
  }

  $gate->set('field_brebo_package_ref', ['target_id' => $package->id()]);
  $gate->set('field_brebo_gate_type', $gate_type);
  $gate->set('field_brebo_gate_applicable', 1);
  $gate->set('field_brebo_gate_result', 'Niet beoordeeld');
  $gate->set('field_brebo_gate_assessment', [
    'value' => 'Poort aangemaakt voor integrale vrijgave van werkpakket WP-GLAS-001.',
    'format' => 'plain_text',
  ]);
  $gate->setNewRevision(TRUE);
  $gate->setRevisionLogMessage('Vrijgavepoort voor pilotwerkpakket ingericht.');
  $gate->save();
}

print "Werkpakket WP-GLAS-001 opgeslagen met 23 posities. Poorten: $created aangemaakt, $updated bijgewerkt. Werkpakket-ID: {$package->id()}.\n";
