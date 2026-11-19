<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;
use PDO;

require_once __DIR__.'/OfficeBuildingCalculationSource.php';

/**
 * Integration contract for the existing calculation workbench.
 * No new calculation screens or mutable draft records are created here.
 * The caller must authenticate and authorize building + calculation access.
 */
final class OfficeBuildingCalculationBridge {
  public function __construct(private PDO $db) {}

  public function inventoryForCalculation(int $buildingId,string $scope='building',?int $scopeId=null): array {
    $source=new OfficeBuildingCalculationSource($this->db);
    $rows=$source->preview($buildingId,$scope,$scopeId);
    return [
      'building_id'=>$buildingId,
      'scope'=>$scope,
      'scope_id'=>$scopeId,
      'source_kind'=>'building_inventory',
      'quantity_mode'=>'measured_only',
      'rows'=>array_map(static fn(array $row):array=>[
        'component_code'=>(string)$row['component_code'],
        'description'=>(string)$row['description'],
        'unit'=>(string)$row['unit'],
        'quantity'=>(string)$row['quantity'],
        'source_lines'=>(int)$row['source_lines']
      ],$rows)
    ];
  }

  /**
   * Deterministic source snapshot fingerprint. The calculation writer should
   * persist this alongside its own immutable version before pricing.
   * A hash is NOT itself a persisted version or approval.
   */
  public static function fingerprint(array $source): string {
    if (($source['source_kind']??null)!=='building_inventory' || !isset($source['rows']) || !is_array($source['rows'])) {
      throw new InvalidArgumentException('invalid_inventory_snapshot');
    }
    return hash('sha256',json_encode($source,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION));
  }

  /** Safe links for an existing calculation UI to open building inventory. */
  public static function buildingLinks(int $buildingId): array {
    if ($buildingId<1) throw new InvalidArgumentException('invalid_building');
    $base='/office-test/gebouwen?id='.$buildingId;
    return [
      'inventory'=>$base,
      'quantities_csv'=>$base.'&quantity_export=csv&quantity_level=building',
      'quantities_print'=>$base.'&quantity_print=1&quantity_level=building'
    ];
  }
}
