<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Calculates glass risk value from canonical BREBO calculation rows. */
final class GlassFinancialImpactEvaluator {
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @return array{priced:bool,total_cost:float,labour_cost:float,material_cost:float,equipment_cost:float,subcontracting_cost:float,other_cost:float,labour_hour_rate:?float,source_lines:int,summary:string}
   */
  public function evaluate(int $positionId): array {
    $result=[
      'priced'=>FALSE,'total_cost'=>0.0,'labour_cost'=>0.0,'material_cost'=>0.0,'equipment_cost'=>0.0,
      'subcontracting_cost'=>0.0,'other_cost'=>0.0,'labour_hour_rate'=>NULL,'source_lines'=>0,
      'summary'=>'Geen geprijsde calculatieregels gekoppeld',
    ];
    $schema=$this->database->schema();
    if(!$schema->tableExists('brebo_calculation_row_domain')||!$schema->fieldExists('brebo_calculation_row_domain','source_domain')) return $result;

    $rows=$this->database->select('brebo_calculation_row_domain','d')
      ->fields('d',['calc_line_id','labour_unit_cost','material_unit_cost','equipment_unit_cost','subcontracting_unit_cost','other_unit_cost'])
      ->condition('source_domain','brebo_glass_position')
      ->condition('source_reference',(string)$positionId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if(!$rows) return $result;

    $storage=$this->entityTypeManager->getStorage('node');
    $labourQuantity=0.0;$labourValue=0.0;
    foreach($rows as$row){
      $line=$storage->load((int)$row['calc_line_id']);
      if(!$line instanceof NodeInterface) continue;
      $quantity=1.0;
      if($line->hasField('field_brebo_contract_quantity')&&!$line->get('field_brebo_contract_quantity')->isEmpty()) $quantity=(float)$line->get('field_brebo_contract_quantity')->value;
      $labour=(float)$row['labour_unit_cost']*$quantity;
      $material=(float)$row['material_unit_cost']*$quantity;
      $equipment=(float)$row['equipment_unit_cost']*$quantity;
      $subcontracting=(float)$row['subcontracting_unit_cost']*$quantity;
      $other=(float)$row['other_unit_cost']*$quantity;
      $result['labour_cost']+=$labour;$result['material_cost']+=$material;$result['equipment_cost']+=$equipment;$result['subcontracting_cost']+=$subcontracting;$result['other_cost']+=$other;
      if((float)$row['labour_unit_cost']>0){$labourQuantity+=$quantity;$labourValue+=$labour;}
      $result['source_lines']++;
    }
    $result['total_cost']=$result['labour_cost']+$result['material_cost']+$result['equipment_cost']+$result['subcontracting_cost']+$result['other_cost'];
    $result['priced']=$result['total_cost']>0;
    $result['labour_hour_rate']=$labourQuantity>0?round($labourValue/$labourQuantity,2):NULL;
    foreach(['total_cost','labour_cost','material_cost','equipment_cost','subcontracting_cost','other_cost'] as$key)$result[$key]=round((float)$result[$key],2);
    $result['summary']=$result['priced']
      ? sprintf('€ %.2f calculatiewaarde · arbeid € %.2f · materiaal € %.2f · materieel € %.2f',$result['total_cost'],$result['labour_cost'],$result['material_cost'],$result['equipment_cost'])
      : 'Calculatieregels aanwezig, maar nog niet geprijsd';
    return $result;
  }
}
