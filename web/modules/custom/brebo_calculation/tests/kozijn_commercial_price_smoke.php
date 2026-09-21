<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Domain/CalculationParameters.php';
require_once __DIR__ . '/../src/Domain/CommercialResult.php';
require_once __DIR__ . '/../src/Service/CommercialCalculator.php';
require_once __DIR__ . '/../src/Service/KozijnPriceEngine.php';
require_once __DIR__ . '/../src/Service/KozijnCommercialPriceService.php';

use Drupal\brebo_calculation\Domain\CalculationParameters;
use Drupal\brebo_calculation\Service\CommercialCalculator;
use Drupal\brebo_calculation\Service\KozijnCommercialPriceService;
use Drupal\brebo_calculation\Service\KozijnPriceEngine;

$service = new KozijnCommercialPriceService(new KozijnPriceEngine(), new CommercialCalculator());
$params = new CalculationParameters(commercialMethod: 'single_margin', singleMarginPct: 10.0);
$result = $service->calculate([
  'width_mm' => 1200,
  'height_mm' => 1200,
  'system' => 'ideal7000_nl',
  'type' => 'vast',
  'fields' => 1,
], $params);

if (($result['status'] ?? NULL) !== 'calculated') {
  throw new RuntimeException('Known calibration must yield a commercial indication.');
}
if (($result['public']['low'] ?? 0) <= 0 || ($result['public']['expected'] ?? 0) <= 0 || ($result['public']['high'] ?? 0) <= 0) {
  throw new RuntimeException('Public sales indication must be positive.');
}
if (!(($result['public']['low'] ?? 0) < ($result['public']['expected'] ?? 0) && ($result['public']['expected'] ?? 0) < ($result['public']['high'] ?? 0))) {
  throw new RuntimeException('Public price band must be ordered low < expected < high.');
}
foreach (['supplier_gross', 'net_purchase_expected', 'brebo_discount_percent'] as $secret) {
  if (array_key_exists($secret, $result['public'])) {
    throw new RuntimeException('Public commercial indication leaks internal data: ' . $secret);
  }
}
$zeroBand = $service->calculate([
  'width_mm' => 400,
  'height_mm' => 700,
  'system' => 'ideal7000_nl',
  'type' => 'vast',
  'fields' => 1,
], new CalculationParameters());
if (($zeroBand['status'] ?? NULL) !== 'insufficient_calibration') {
  throw new RuntimeException('Non-positive public price bands must not be published.');
}
$unsupported = $service->calculate([
  'width_mm' => 1200,
  'height_mm' => 1200,
  'system' => 'ideal7000_nl',
  'type' => 'vast',
  'fields' => 2,
], $params);
if (($unsupported['status'] ?? NULL) !== 'insufficient_calibration') {
  throw new RuntimeException('Unsupported geometry must not produce a sales indication.');
}
echo "KOZIJN_COMMERCIAL_PRICE_SMOKE=OK\n";
