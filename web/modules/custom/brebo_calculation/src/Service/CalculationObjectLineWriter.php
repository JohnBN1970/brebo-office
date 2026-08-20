<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/** Writes object-derived rows through the current BREBO calculation workbench. */
final class CalculationObjectLineWriter {
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $objectEntityTypeManager,
    private readonly CalculationRowManager $rowManager,
  ) {}

  /** @param array<string,float|int> $unitCosts @param array<string,mixed> $priceTrace */
  public function write(int $calculationId,string $version,string $paragraphKey,string $description,float $quantity,string $unit,array $unitCosts,string $sourceDomain,string $sourceReference,string $sourceChecksum,AccountInterface $account,array $priceTrace=[]): int {
    $description=trim($description);$unit=trim($unit);$sourceDomain=trim($sourceDomain);$sourceReference=trim($sourceReference);$sourceChecksum=trim($sourceChecksum);
    if($description===''||$unit===''||$sourceDomain===''||$sourceReference===''||$sourceChecksum==='') throw new \InvalidArgumentException('Objectgestuurde calculatieregels vereisen omschrijving, eenheid en volledige brontraceerbaarheid.');
    if($quantity<0) throw new \InvalidArgumentException('Calculatiehoeveelheid mag niet negatief zijn.');
    $lineId=$this->rowManager->add($calculationId,$version,$paragraphKey,$account);
    $line=$this->objectEntityTypeManager->getStorage('node')->load($lineId);
    if(!$line instanceof NodeInterface||$line->bundle()!=='brebo_calc_line') throw new \RuntimeException('Aangemaakte calculatieregel kon niet opnieuw worden geladen.');
    $costs=['labour_unit_cost'=>$this->cost($unitCosts,'labour'),'material_unit_cost'=>$this->cost($unitCosts,'material'),'equipment_unit_cost'=>$this->cost($unitCosts,'equipment'),'subcontracting_unit_cost'=>$this->cost($unitCosts,'subcontracting'),'other_unit_cost'=>$this->cost($unitCosts,'other')];
    $priceSourceRef=trim((string)($priceTrace['source_ref']??''));$priceSourceDate=trim((string)($priceTrace['source_date']??''));$priceConfidence=trim((string)($priceTrace['confidence']??''));$priceReason=trim((string)($priceTrace['reason']??''));
    $transaction=$this->database->startTransaction();
    try {
      $line->setTitle($description);$this->setIfPresent($line,'field_brebo_line_description',$description);$this->setIfPresent($line,'field_brebo_contract_quantity',number_format($quantity,4,'.',''));$this->setIfPresent($line,'field_brebo_unit',$unit);$this->setIfPresent($line,'field_brebo_unit_price',number_format(array_sum($costs),4,'.',''));
      $this->setIfPresent($line,'field_brebo_line_status','Niet beoordeeld');$this->setIfPresent($line,'field_brebo_line_type','Calculatieregel');$this->setIfPresent($line,'field_brebo_note_visibility','Intern');
      $note=sprintf('Objectbron %s:%s · checksum %s',$sourceDomain,$sourceReference,$sourceChecksum);if($priceSourceRef!=='')$note.=' · prijsbron '.$priceSourceRef;if($priceReason!=='')$note.=' · '.$priceReason;$this->setIfPresent($line,'field_brebo_line_note',$note);
      $line->setNewRevision(TRUE);$line->setRevisionLogMessage(sprintf('Objectgestuurde calculatieregel uit %s:%s.',$sourceDomain,$sourceReference));$line->save();
      $values=$costs+['source_domain'=>$sourceDomain,'source_reference'=>$sourceReference,'source_checksum'=>$sourceChecksum,'price_source_reference'=>$priceSourceRef?:NULL,'price_source_date'=>$priceSourceDate?:NULL,'price_confidence'=>$priceConfidence?:NULL];
      $supported=[];foreach($values as$field=>$value)if($this->database->schema()->fieldExists('brebo_calculation_row_domain',$field))$supported[$field]=$value;
      $this->database->update('brebo_calculation_row_domain')->fields($supported)->condition('calc_line_id',$lineId)->condition('calculation_id',$calculationId)->condition('version',$version)->execute();
    } catch(\Throwable $e){$transaction->rollBack();try{$this->rowManager->delete($calculationId,$version,$lineId,$account);}catch(\Throwable){}throw $e;}
    return $lineId;
  }

  /** @param array<string,float|int> $costs */
  private function cost(array $costs,string $key): float {$value=(float)($costs[$key]??0.0);if($value<0)throw new \InvalidArgumentException('Eenheidskosten mogen niet negatief zijn.');return$value;}
  private function setIfPresent(NodeInterface $line,string $field,mixed $value): void {if($line->hasField($field))$line->set($field,$value);}
}
