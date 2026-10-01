<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationWorkspaceReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Drupal database adapter for the calculation workspace read model.
 */
final class DatabaseCalculationWorkspaceReadRepository implements CalculationWorkspaceReadRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function latestVersion(int $calculationId): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  public function structure(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function rows(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('paragraph_key')
      ->orderBy('sort_order')
      ->orderBy('row_id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function recipes(int $calculationId, string $version): array {
    $instances = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i')
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->orderBy('paragraph_key')
      ->orderBy('sort_order')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($instances as &$instance) {
      $instanceId = (int) $instance['id'];
      $instance['parameters'] = $this->database->select('brebo_calculation_recipe_instance_parameter', 'p')
        ->fields('p')
        ->condition('recipe_instance_id', $instanceId)
        ->orderBy('id')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);

      $instance['lines'] = $this->database->select('brebo_calculation_recipe_instance_line', 'l')
        ->fields('l')
        ->condition('recipe_instance_id', $instanceId)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($instance['lines'] as &$line) {
        $line['packaging'] = NULL;
        if (
          preg_match('/article:(\\d+):supplier_article:(\\d+)/', (string) ($line['material_ref'] ?? ''), $materialMatch) &&
          preg_match('/article_price:(\\d+):catalog:(\\d+):date:([^:]+)/', (string) ($line['price_source_ref'] ?? ''), $priceMatch)
        ) {
          $query = $this->database->select('brebo_supplier_article', 'sa');
          $query->join('brebo_article', 'a', 'a.id = sa.article_id');
          $query->join('brebo_article_price', 'p', 'p.supplier_article_id = sa.id');
          $query->fields('a', ['base_unit']);
          $query->fields('sa', ['order_unit', 'use_unit', 'conversion_factor', 'minimum_order']);
          $query->fields('p', ['quantity_from', 'net_price', 'valid_from']);
          $query->condition('a.id', (int) $materialMatch[1]);
          $query->condition('sa.id', (int) $materialMatch[2]);
          $query->condition('p.id', (int) $priceMatch[1]);
          $packaging = $query->execute()->fetchAssoc();
          if ($packaging) {
            $line['packaging'] = [
              'base_unit' => (string) $packaging['base_unit'],
              'use_unit' => $packaging['use_unit'] !== NULL ? (string) $packaging['use_unit'] : NULL,
              'order_unit' => $packaging['order_unit'] !== NULL ? (string) $packaging['order_unit'] : NULL,
              'conversion_factor' => (float) $packaging['conversion_factor'],
              'minimum_order' => (float) $packaging['minimum_order'],
              'quantity_from' => (float) $packaging['quantity_from'],
              'net_price' => (float) $packaging['net_price'],
              'price_date' => (string) $packaging['valid_from'],
            ];
          }
        }
      }
      unset($line);
    }
    unset($instance);

    return $instances;
  }

  public function subcalculations(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_subcalculation', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('label')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
