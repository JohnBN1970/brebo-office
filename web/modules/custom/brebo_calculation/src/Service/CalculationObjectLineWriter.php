<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationLegacyLineCompatibilityInterface;
use Drupal\brebo_calculation\Contract\CalculationObjectLineRepositoryInterface;

/** Writes object-derived rows through the current BREBO calculation workbench. */
final class CalculationObjectLineWriter {
  public function __construct(
    private readonly CalculationObjectLineRepositoryInterface $repository,
    private readonly CalculationRowManager $rowManager,
    private readonly CalculationLegacyLineCompatibilityInterface $legacyCompatibility,
  ) {}

  /** @param array<string,float|int> $unitCosts @param array<string,mixed> $priceTrace */
  public function write(int $calculationId,string $version,string $paragraphKey,string $description,float $quantity,string $unit,array $unitCosts,string $sourceDomain,string $sourceReference,string $sourceChecksum,int $actorId,array $priceTrace=[]): int {
    $description=trim($description);$unit=trim($unit);$sourceDomain=trim($sourceDomain);$sourceReference=trim($sourceReference);$sourceChecksum=trim($sourceChecksum);
    if($description===''||$unit===''||$sourceDomain===''||$sourceReference===''||$sourceChecksum==='') throw new \InvalidArgumentException('Objectgestuurde calculatieregels vereisen omschrijving, eenheid en volledige brontraceerbaarheid.');
    if($quantity<0) throw new \InvalidArgumentException('Calculatiehoeveelheid mag niet negatief zijn.');
    $rowId=$this->rowManager->add($calculationId,$version,$paragraphKey,$actorId);
    $costs=['labour_unit_cost'=>$this->cost($unitCosts,'labour'),'material_unit_cost'=>$this->cost($unitCosts,'material'),'equipment_unit_cost'=>$this->cost($unitCosts,'equipment'),'subcontracting_unit_cost'=>$this->cost($unitCosts,'subcontracting'),'other_unit_cost'=>$this->cost($unitCosts,'other')];
    $priceSourceRef=trim((string)($priceTrace['source_ref']??''));$priceSourceDate=trim((string)($priceTrace['source_date']??''));$priceConfidence=trim((string)($priceTrace['confidence']??''));$priceReason=trim((string)($priceTrace['reason']??''));

      $this->legacyCompatibility->updateObjectDerived(
        $calculationId,
        $version,
        $rowId,
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
      $values=$costs+['description'=>$description,'contract_quantity'=>$quantity,'unit'=>$unit,'source_domain'=>$sourceDomain,'source_reference'=>$sourceReference,'source_checksum'=>$sourceChecksum,'price_source_reference'=>$priceSourceRef?:NULL,'price_source_date'=>$priceSourceDate?:NULL,'price_confidence'=>$priceConfidence?:NULL];
      try {
        $this->repository->updateObjectRow($calculationId, $version, $rowId, $values);
      }
      catch (\Throwable $e) {
        try {$this->rowManager->delete($calculationId, $version, $rowId, $actorId);} catch (\Throwable) {}
        throw $e;
      }
    return $rowId;
  }

  /** @param array<string,float|int> $costs */
  private function cost(array $costs,string $key): float {$value=(float)($costs[$key]??0.0);if($value<0)throw new \InvalidArgumentException('Eenheidskosten mogen niet negatief zijn.');return$value;}
}
