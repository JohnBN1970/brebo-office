<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_calculation\Service\CalculationObjectLineWriter;
use Drupal\Core\Session\AccountInterface;

/** Exports approved glass positions as traceable and priced calculation rows. */
final class GlassCalculationExporter {
  public function __construct(
    private readonly GlassCalculationContextBuilder $contextBuilder,
    private readonly GlassPriceResolver $priceResolver,
    private readonly CalculationObjectLineWriter $writer,
  ) {}

  /** @return array{material_line_id:int,labour_line_id:int,material_priced:bool,labour_priced:bool} */
  public function export(int $positionId,int $calculationId,string $version,string $paragraphKey,AccountInterface $account): array {
    $context=$this->contextBuilder->build($positionId);
    $prices=$this->priceResolver->resolve($context);
    $sourceDomain='brebo_glass_position';
    $sourceReference=(string)$positionId;
    $checksum=(string)$context['approval_checksum'];

    $material=$this->writer->write(
      $calculationId,$version,$paragraphKey,
      $context['description'].' · materiaal',
      (float)$context['material_quantity_m2'],'m²',
      ['material'=>(float)$prices['material']['unit_cost']],
      $sourceDomain,$sourceReference,$checksum,$account,$prices['material'],
    );
    $labour=$this->writer->write(
      $calculationId,$version,$paragraphKey,
      $context['description'].' · montage',
      (float)$context['labour_hours'],'uur',
      ['labour'=>(float)$prices['labour']['unit_cost']],
      $sourceDomain,$sourceReference,$checksum,$account,$prices['labour'],
    );

    return [
      'material_line_id'=>$material,
      'labour_line_id'=>$labour,
      'material_priced'=>(bool)$prices['material']['priced'],
      'labour_priced'=>(bool)$prices['labour']['priced'],
    ];
  }
}
