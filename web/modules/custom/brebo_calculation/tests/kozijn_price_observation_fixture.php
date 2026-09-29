<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Service/KozijnPriceObservationProviderInterface.php';

use Drupal\brebo_calculation\Service\KozijnPriceObservationProviderInterface;

/** @return KozijnPriceObservationProviderInterface */
function brebo_kozijn_test_observation_provider(): KozijnPriceObservationProviderInterface {
  return new class implements KozijnPriceObservationProviderInterface {
    private array $rows = [
      ['system'=>'ideal4000','width_mm'=>1200,'height_mm'=>1200,'fields_count'=>1,'configuration_type'=>'vast','supplier_gross'=>'242.1700'],
      ['system'=>'ideal4000','width_mm'=>1200,'height_mm'=>1200,'fields_count'=>1,'configuration_type'=>'draai-kiep','supplier_gross'=>'360.2700'],
      ['system'=>'ideal7000_nl','width_mm'=>1200,'height_mm'=>1200,'fields_count'=>1,'configuration_type'=>'vast','supplier_gross'=>'325.7100'],
      ['system'=>'ideal7000_nl','width_mm'=>1200,'height_mm'=>1200,'fields_count'=>1,'configuration_type'=>'draai-kiep','supplier_gross'=>'446.4300'],
      ['system'=>'ideal7000_nl','width_mm'=>980,'height_mm'=>1360,'fields_count'=>1,'configuration_type'=>'vast','supplier_gross'=>'311.0600'],
      ['system'=>'ideal7000_nl','width_mm'=>980,'height_mm'=>1360,'fields_count'=>1,'configuration_type'=>'draai-kiep','supplier_gross'=>'429.5800'],
    ];

    public function approved(string $system, string $type, int $fields = 1): array {
      return array_values(array_filter($this->rows, static fn(array $row): bool =>
        $row['system'] === $system &&
        $row['configuration_type'] === $type &&
        $row['fields_count'] === $fields
      ));
    }
  };
}
