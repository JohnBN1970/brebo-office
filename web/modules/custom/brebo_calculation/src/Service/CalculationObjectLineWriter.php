<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Writes object-derived rows through the current BREBO calculation workbench. */
final class CalculationObjectLineWriter {
  public function __construct(
    private readonly Connection $database,
    private readonly CalculationRowManager $rowManager,
    private readonly \Drupal\brebo_calculation\Contract\CalculationLineLegacyGatewayInterface $legacyLineGateway,
  ) {}

  /** @param array<string,float|int> $unitCosts @param array<string,mixed> $priceTrace */
  public function write(int $calculationId,string $version,string $paragraphKey,string $description,float $quantity,string $unit,array $unitCosts,string $sourceDomain,string $sourceReference,string $sourceChecksum,int $actorId,array $priceTrace=[]): int {
    $description=trim($description);$unit=trim($unit);$sourceDomain=trim($sourceDomain);$sourceReference=trim($sourceReference);$sourceChecksum=trim($sourceChecksum);
    if($description===''||$unit===''||$sourceDomain===''||$sourceReference===''||$sourceChecksum==='') throw new \InvalidArgumentException('Objectgestuurde calculatieregels vereisen omschrijving, eenheid en volledige brontraceerbaarheid.');
    if($quantity<0) throw new \InvalidArgumentException('Calculatiehoeveelheid mag niet negatief zijn.');
    $lineId=$this->rowManager->add($calculationId,$version,$paragraphKey,$actorId);
    $costs=['labour_unit_cost'=>$this->cost($unitCosts,'labour'),'material_unit_cost'=>$this->cost($unitCosts,'material'),'equipment_unit_cost'=>$this->cost($unitCosts,'equipment'),'subcontracting_unit_cost'=>$this->cost($unitCosts,'subcontracting'),'other_unit_cost'=>$this->cost($unitCosts,'other')];
    $priceSourceRef=trim((string)($priceTrace['source_ref']??''));$priceSourceDate=trim((string)($priceTrace['source_date']??''));$priceConfidence=trim((string)($priceTrace['confidence']??''));$priceReason=trim((string)($priceTrace['reason']??''));
    $transaction=$this->database->startTransaction();
    try {
      $this->legacyLineGateway->updateObjectDerived(
        $lineId,
        $description,
        $unit,
        $quantity,
        $costs,
        $sourceDomain,
        $sourceReference,
        $sourceChecksum,
        $priceSourceRef ?: NULL,
        $priceReason ?: NULL,
      );
      $values=$costs+['source_domain'=>$sourceDomain,'source_reference'=>$sourceReference,'source_checksum'=>$sourceChecksum,'price_source_reference'=>$priceSourceRef?:NULL,'price_source_date'=>$priceSourceDate?:NULL,'price_confidence'=>$priceConfidence?:NULL];
      $supported=[];foreach($values as$field=>$value)if($this->database->schema()->fieldExists('brebo_calculation_row_domain',$field))$supported[$field]=$value;
      $this->database->update('brebo_calculation_row_domain')->fields($supported)->condition('calc_line_id',$lineId)->condition('calculation_id',$calculationId)->condition('version',$version)->execute();
    } catch(\Throwable $e){$transaction->rollBack();try{$this->rowManager->delete($calculationId,$version,$lineId,$actorId);}catch(\Throwable){}throw $e;}
    return $lineId;
  }

  /** @param array<string,float|int> $costs */
  private function cost(array $costs,string $key): float {$value=(float)($costs[$key]??0.0);if($value<0)throw new \InvalidArgumentException('Eenheidskosten mogen niet negatief zijn.');return$value;}
}
