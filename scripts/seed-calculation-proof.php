<?php

declare(strict_types=1);

use Drupal\node\Entity\Node;

/**
 * Idempotently creates the controlled BREBO calculation-chain proof.
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

$upsertNode = static function (string $bundle, array $conditions, int $user_id): Node {
  $query = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', $bundle);
  foreach ($conditions as [$field, $value, $operator]) {
    $query->condition($field, $value, $operator);
  }
  $ids = $query->execute();
  return $ids
    ? Node::load(reset($ids))
    : Node::create(['type' => $bundle, 'status' => 1, 'uid' => $user_id]);
};

$user_id = $singleId('user', [['name', 'john.boon', '=']]);
$package_id = $singleId('node', [
  ['type', 'brebo_work_package', '='],
  ['field_brebo_package_code', 'WP-GLAS-001', '='],
]);

$calculation = $upsertNode('brebo_calculation', [
  ['field_brebo_calc_code', 'CALC-TEST-001', '='],
], $user_id);
$calculation->setTitle('Proefcalculatie gesloten calculatieketen');
$calculation->set('field_brebo_calc_code', 'CALC-TEST-001');
$calculation->set('field_brebo_package_ref', ['target_id' => $package_id]);
$calculation->set('field_brebo_calc_version', '1.0');
$calculation->set('field_brebo_calc_status', 'Vastgesteld');
$calculation->set('field_brebo_price_date', '2026-08-08');
$calculation->set('field_brebo_calc_description', [
  'value' => 'Gecontroleerde proef met arbeid, materiaal, stelpost, verrekenpost, 30% opslag en overdracht naar werkbegroting.',
  'format' => 'plain_text',
]);
$calculation->setNewRevision(TRUE);
$calculation->setRevisionLogMessage('Proefcalculatieketen ingericht of bijgewerkt.');
$calculation->save();

$element = $upsertNode('brebo_calc_element', [
  ['field_brebo_calculation_ref.target_id', $calculation->id(), '='],
  ['field_brebo_element_code', 'TEST-41', '='],
], $user_id);
$element->setTitle('Testelement buitenwandafwerking');
$element->set('field_brebo_calculation_ref', ['target_id' => $calculation->id()]);
$element->set('field_brebo_element_code', 'TEST-41');
$element->set('field_brebo_element_sequence', 10);
$element->set('field_brebo_element_scope', [
  'value' => 'Rekenkundig ijkelement voor de gesloten BREBO-calculatieketen.',
  'format' => 'plain_text',
]);
$element->setNewRevision(TRUE);
$element->setRevisionLogMessage('Testelement ingericht of bijgewerkt.');
$element->save();

$lineDefinitions = [
  'TEST-ARBEID' => [
    'description' => 'Schilderwerk — arbeid',
    'post_type' => 'Vaste post',
    'category' => 'Arbeid',
    'quantity' => '100.0000',
    'unit' => 'm²',
    'unit_price' => '23.4000',
    'norm_hours' => '0.4000',
    'labor_rate' => '58.5000',
    'tariff_group' => 'E',
    'markup' => 1,
    'sequence' => 10,
    'vat' => '9.0000',
  ],
  'TEST-MATERIAAL' => [
    'description' => 'Schilderwerk — materiaal',
    'post_type' => 'Vaste post',
    'category' => 'Materiaal',
    'quantity' => '100.0000',
    'unit' => 'm²',
    'unit_price' => '15.0000',
    'norm_hours' => NULL,
    'labor_rate' => NULL,
    'tariff_group' => '',
    'markup' => 1,
    'sequence' => 20,
    'vat' => '21.0000',
  ],
  'TEST-STELPOST' => [
    'description' => 'Stelpost houtrotherstel',
    'post_type' => 'Stelpost',
    'category' => 'Overig',
    'quantity' => '1.0000',
    'unit' => 'post',
    'unit_price' => '2000.0000',
    'norm_hours' => NULL,
    'labor_rate' => NULL,
    'tariff_group' => '',
    'markup' => 0,
    'sequence' => 30,
    'vat' => '21.0000',
  ],
  'TEST-VERREKEN' => [
    'description' => 'Scheurherstel op verrekenbare hoeveelheid',
    'post_type' => 'Verrekenpost',
    'category' => 'Onderaanneming',
    'quantity' => '40.0000',
    'unit' => 'm¹',
    'unit_price' => '117.0500',
    'norm_hours' => NULL,
    'labor_rate' => NULL,
    'tariff_group' => '',
    'markup' => 1,
    'sequence' => 40,
    'vat' => '9.0000',
  ],
];

$lines = [];
foreach ($lineDefinitions as $code => $definition) {
  $line = $upsertNode('brebo_calc_line', [
    ['field_brebo_calc_element_ref.target_id', $element->id(), '='],
    ['field_brebo_line_memo', $code, '='],
  ], $user_id);
  $line->setTitle($definition['description']);
  $line->set('field_brebo_calc_element_ref', ['target_id' => $element->id()]);
  $line->set('field_brebo_line_post_type', $definition['post_type']);
  $line->set('field_brebo_cost_category', $definition['category']);
  $line->set('field_brebo_line_description', $definition['description']);
  $line->set('field_brebo_contract_quantity', $definition['quantity']);
  $line->set('field_brebo_unit', $definition['unit']);
  $line->set('field_brebo_unit_price', $definition['unit_price']);
  $line->set('field_brebo_price_source', 'BREBO gecontroleerde proefset');
  $line->set('field_brebo_line_sequence', $definition['sequence']);
  $line->set('field_brebo_norm_hours', $definition['norm_hours']);
  $line->set('field_brebo_tariff_group', $definition['tariff_group']);
  $line->set('field_brebo_labor_rate', $definition['labor_rate']);
  $line->set('field_brebo_vat_rate', $definition['vat']);
  $line->set('field_brebo_markup_applicable', $definition['markup']);
  $line->set('field_brebo_line_memo', $code);
  if ($code === 'TEST-MATERIAAL' && $line->hasField('field_brebo_material_code')) {
    $line->set('field_brebo_material_code', 'MAT-VERF-001');
    $line->set('field_brebo_material_spec', 'Watergedragen aflak, wit, overeengekomen kwaliteit.');
    $line->set('field_brebo_waste_percent', '5.0000');
    $line->set('field_brebo_pack_quantity', '10.0000');
    $line->set('field_brebo_preferred_supplier', 'BREBO testleverancier');
  }
  $line->setNewRevision(TRUE);
  $line->setRevisionLogMessage('Proefcalculatieregel ingericht of bijgewerkt.');
  $line->save();
  $lines[$code] = $line;
}

$provisional = $upsertNode('brebo_provisional_sum', [
  ['field_brebo_calc_line_ref.target_id', $lines['TEST-STELPOST']->id(), '='],
], $user_id);
$provisional->set('field_brebo_calc_line_ref', ['target_id' => $lines['TEST-STELPOST']->id()]);
$provisional->set('field_brebo_provisional_scope', [
  'value' => 'Plaatselijk houtrotherstel inclusief arbeid en basismateriaal.',
  'format' => 'plain_text',
]);
$provisional->set('field_brebo_prov_exclusions', [
  'value' => 'Constructieve vervanging en verborgen gebreken zijn uitgesloten.',
  'format' => 'plain_text',
]);
$provisional->set('field_brebo_budget_amount', '2000.0000');
$provisional->set('field_brebo_decision_date', '2026-09-01');
$provisional->set('field_brebo_responsible_user', ['target_id' => $user_id]);
$provisional->set('field_brebo_provisional_status', 'Open');
$provisional->set('field_brebo_settlement_notes', [
  'value' => 'Nog te onderbouwen met opname en leveranciersofferte.',
  'format' => 'plain_text',
]);
$provisional->setNewRevision(TRUE);
$provisional->setRevisionLogMessage('Gecontroleerde proefstelpost ingericht.');
$provisional->save();

$settlement = $upsertNode('brebo_quantity_settlement', [
  ['field_brebo_calc_line_ref.target_id', $lines['TEST-VERREKEN']->id(), '='],
], $user_id);
$settlement->set('field_brebo_calc_line_ref', ['target_id' => $lines['TEST-VERREKEN']->id()]);
$settlement->set('field_brebo_measured_quantity', '52.0000');
$settlement->set('field_brebo_approved_quantity', '52.0000');
$settlement->set('field_brebo_settlement_rate', '117.0500');
$settlement->set('field_brebo_measurement_source', [
  'value' => 'Gecontroleerde proefmeetstaat: contract 40 m¹, goedgekeurd werkelijk 52 m¹.',
  'format' => 'plain_text',
]);
$settlement->set('field_brebo_settlement_status', 'Akkoord');
$settlement->set('field_brebo_approved_by', ['target_id' => $user_id]);
$settlement->setNewRevision(TRUE);
$settlement->setRevisionLogMessage('Proefverrekening met goedgekeurde hoeveelheid ingericht.');
$settlement->save();

$adjustment = $upsertNode('brebo_calc_adjustment', [
  ['field_brebo_adjust_target.target_id', $calculation->id(), '='],
  ['field_brebo_adjust_sequence', 100, '='],
], $user_id);
$adjustment->set('field_brebo_adjust_target', ['target_id' => $calculation->id()]);
$adjustment->set('field_brebo_adjust_method', 'Percentage');
$adjustment->set('field_brebo_adjust_direction', 'Opslag');
$adjustment->set('field_brebo_adjust_base', 'Alle directe kosten');
$adjustment->set('field_brebo_adjust_value', '30.0000');
$adjustment->set('field_brebo_adjust_sequence', 100);
$adjustment->set('field_brebo_adjust_cumulative', 0);
$adjustment->set('field_brebo_adjust_reason', [
  'value' => 'Proefopslag 30% over alle regels waarvoor Opnemen in opslaggrondslag is aangevinkt. De stelpost is expliciet uitgesloten.',
  'format' => 'plain_text',
]);
$adjustment->setNewRevision(TRUE);
$adjustment->setRevisionLogMessage('Gecontroleerde proefopslag ingericht.');
$adjustment->save();

$contract_direct = 2340.00 + 1500.00 + 2000.00 + 4682.00;
$contract_markup = (2340.00 + 1500.00 + 4682.00) * 0.30;
$contract_total = $contract_direct + $contract_markup;
$forecast_direct = 2340.00 + 1500.00 + 2000.00 + 6086.60;
$forecast_markup = (2340.00 + 1500.00 + 6086.60) * 0.30;
$forecast_total = $forecast_direct + $forecast_markup;

print "Proefcalculatie CALC-TEST-001 opgeslagen.\n";
print "Calculatie-ID: {$calculation->id()} | Element-ID: {$element->id()} | Regels: " . count($lines) . "\n";
print "Controle contract: direct € " . number_format($contract_direct, 2, ',', '.') .
  " + opslag € " . number_format($contract_markup, 2, ',', '.') .
  " = € " . number_format($contract_total, 2, ',', '.') . "\n";
print "Controle prognose: direct € " . number_format($forecast_direct, 2, ',', '.') .
  " + opslag € " . number_format($forecast_markup, 2, ',', '.') .
  " = € " . number_format($forecast_total, 2, ',', '.') . "\n";
print "Verrekening: 12,00 m¹ × € 117,05 = € 1.404,60; inclusief 30% opslag effect € 1.825,98.\n";
print "Budgeturen arbeid: 100,00 m² × 0,4000 = 40,00 uur.\n";
