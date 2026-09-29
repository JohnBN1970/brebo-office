<?php

declare(strict_types=1);

require_once __DIR__ . '/kozijn_price_observation_fixture.php';
require_once __DIR__ . '/../src/Service/KozijnPriceEngine.php';

use Drupal\brebo_calculation\Service\KozijnPriceEngine;

$engine = new KozijnPriceEngine(brebo_kozijn_test_observation_provider());
$estimate = $engine->estimate(['width_mm' => 1200, 'height_mm' => 1200, 'system' => 'ideal7000_nl', 'type' => 'vast', 'fields' => 1]);
if (($estimate['supported'] ?? FALSE) !== TRUE || abs(($estimate['supplier_gross'] ?? 0) - 325.71) > 0.01) {
  throw new RuntimeException('Known calibration anchor must remain stable.');
}
$public = $engine->toPublicIndication($estimate);
foreach (['supplier_gross', 'net_purchase_expected', 'brebo_discount_percent'] as $secret) {
  if (array_key_exists($secret, $public)) {
    throw new RuntimeException('Public projection leaks internal commercial data: ' . $secret);
  }
}
$negativeDomain = $engine->estimate(['width_mm' => 400, 'height_mm' => 400, 'system' => 'ideal7000_nl', 'type' => 'vast', 'fields' => 1]);
if (($negativeDomain['supported'] ?? TRUE) !== FALSE) {
  throw new RuntimeException('Geometry outside the calibrated price domain must not yield a negative supported estimate.');
}
$unsupported = $engine->estimate(['width_mm' => 1200, 'height_mm' => 1200, 'system' => 'ideal7000_nl', 'type' => 'vast', 'fields' => 2]);
if (($unsupported['supported'] ?? TRUE) !== FALSE) {
  throw new RuntimeException('Uncalibrated multi-field geometry must not invent a price.');
}
echo "KOZIJN_PRICE_ENGINE_SMOKE=OK\n";
