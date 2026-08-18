<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Service/RecipeFormulaEvaluator.php';

use Drupal\brebo_calculation\Service\RecipeFormulaEvaluator;

$evaluator = new RecipeFormulaEvaluator();

$assertSame = static function (float $expected, float $actual, string $message): void {
  if (abs($expected - $actual) > 0.000001) {
    throw new RuntimeException($message . sprintf(' Expected %.6f, got %.6f.', $expected, $actual));
  }
};

$assertThrows = static function (callable $callback, string $message): void {
  try {
    $callback();
  }
  catch (InvalidArgumentException) {
    return;
  }
  throw new RuntimeException($message);
};

// Twelve kozijnen with four metres of kit per kozijn must yield 48 m1.
$assertSame(48.0, $evaluator->evaluate('quantity * kit_per_stuk', [
  'quantity' => 12,
  'kit_per_stuk' => 4,
]), 'Recipe quantity multiplication failed.');

// Derived geometry can drive downstream material quantities.
$omtrek = $evaluator->evaluate('(breedte + hoogte) * 2', [
  'breedte' => 1.2,
  'hoogte' => 1.5,
]);
$assertSame(5.4, $omtrek, 'Perimeter formula failed.');
$assertSame(64.8, $evaluator->evaluate('omtrek * quantity', [
  'omtrek' => $omtrek,
  'quantity' => 12,
]), 'Derived parameter propagation failed.');

// Packaging/consumption rules may round upward to full packages.
$assertSame(5.0, $evaluator->evaluate('ceil(quantity * verbruik / verpakking)', [
  'quantity' => 12,
  'verbruik' => 0.55,
  'verpakking' => 1.5,
]), 'Packaging ceil formula failed.');

// Formula execution must fail closed on invalid input.
$assertThrows(
  static fn () => $evaluator->evaluate('quantity / 0', ['quantity' => 12]),
  'Division by zero should be rejected.',
);
$assertThrows(
  static fn () => $evaluator->evaluate('quantity * onbekend', ['quantity' => 12]),
  'Unknown recipe variables should be rejected.',
);
$assertThrows(
  static fn () => $evaluator->evaluate('phpinfo()', []),
  'Unsupported functions should be rejected.',
);

fwrite(STDOUT, "RECIPE_FORMULA_SMOKE_OK=1\n");
