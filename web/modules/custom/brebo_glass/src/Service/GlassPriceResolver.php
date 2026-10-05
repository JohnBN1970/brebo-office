<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_calculation\Service\CalculationNormLibrary;
use Drupal\brebo_glass\Contract\GlassPriceRepositoryInterface;

/** Resolves glass material and labour rates only from governed BREBO sources. */
final class GlassPriceResolver {
  public function __construct(
    private readonly GlassPriceRepositoryInterface $repository,
    private readonly CalculationNormLibrary $norms,
  ) {}

  /** @param array<string,mixed> $calculationContext @return array<string,array<string,mixed>> */
  public function resolve(array $calculationContext): array {
    return [
      'material' => $this->material($calculationContext),
      'labour' => $this->labour($calculationContext),
    ];
  }

  /** Persist the selected article price as an immutable calculation snapshot. */
  public function snapshotMaterial(int $calculationLineId,array $price,int $selectedBy): void {
    if (empty($price['priced']) || empty($price['article_id']) || !$this->repository->snapshotAvailable()) {
      return;
    }
    if ($this->repository->snapshotExists($calculationLineId)) {
      throw new \RuntimeException('Voor deze calculatieregel bestaat al een artikelprijssnapshot.');
    }
    $this->repository->insertSnapshot([
      'calculation_line_id'=>$calculationLineId,
      'article_id'=>(int)$price['article_id'],
      'supplier_article_id'=>(int)$price['supplier_article_id'],
      'price_id'=>(int)$price['price_id'],
      'article_code'=>(string)$price['article_code'],
      'supplier_name'=>(string)$price['supplier_name'],
      'supplier_article_no'=>(string)$price['supplier_article_no'],
      'description'=>(string)$price['description'],
      'unit'=>(string)$price['unit'],
      'unit_price'=>(float)$price['unit_cost'],
      'price_date'=>(string)$price['source_date'],
      'catalog_import_id'=>(int)$price['catalog_import_id'],
      'selected_by'=>$selectedBy,
      'selected_at'=>time(),
    ]);
  }

  /** @param array<string,mixed> $context @return array<string,mixed> */
  private function material(array $context): array {
    if (!$this->repository->catalogAvailable()) {
      return $this->unpriced('Artikelcatalogus niet volledig beschikbaar.');
    }

    $recommended = trim((string) ($context['recommended_glass_ref'] ?? ''));
    $productCode = trim(explode('—', $recommended, 2)[0] ?? '');
    if ($productCode === '') {
      return $this->unpriced('Geen exacte productcode op de glaspositie.');
    }

    $quantity = max(0.0, (float) ($context['material_quantity_m2'] ?? 0));
    $today = date('Y-m-d');
    $row = $this->repository->findMaterialPrice($productCode, $quantity, $today);
    if (!$row) {
      return $this->unpriced('Geen geldige actuele catalogusprijs voor productcode ' . $productCode . '.');
    }

    $unit = strtolower(trim((string) ($row['use_unit'] ?: $row['base_unit'])));
    if (!in_array($unit, ['m2','m²'], TRUE)) {
      return $this->unpriced('Catalogusprijs gevonden maar eenheid is niet m²; automatische conversie is niet toegestaan.');
    }
    if (strtoupper((string) $row['currency']) !== 'EUR') {
      return $this->unpriced('Catalogusprijs is niet in EUR; automatische valutaconversie is niet toegestaan.');
    }

    return [
      'priced' => TRUE,
      'unit_cost' => (float) $row['net_price'],
      'source_ref' => sprintf('article:%d:supplier_article:%d:price:%d:catalog:%d', (int) $row['id'], (int) $row['supplier_article_id'], (int) $row['price_id'], (int) $row['catalog_import_id']),
      'source_date' => (string) $row['valid_from'],
      'confidence' => 'A',
      'label' => trim((string) $row['supplier_name'] . ' · ' . (string) $row['supplier_article_no']),
      'reason' => 'Exacte productcode en geldige netto catalogusprijs.',
      'article_id'=>(int)$row['id'],
      'article_code'=>(string)$row['code'],
      'supplier_article_id'=>(int)$row['supplier_article_id'],
      'supplier_article_no'=>(string)$row['supplier_article_no'],
      'supplier_name'=>(string)$row['supplier_name'],
      'price_id'=>(int)$row['price_id'],
      'catalog_import_id'=>(int)$row['catalog_import_id'],
      'description'=>(string)$row['description'],
      'unit'=>$unit,
    ];
  }

  /** @param array<string,mixed> $context @return array<string,mixed> */
  private function labour(array $context): array {
    $normContext = (array) ($context['context'] ?? []);
    $rate = $this->norms->value('glass', 'labour_cost_per_hour', $normContext, 0.0);
    if ($rate <= 0) {
      return $this->unpriced('Geen expliciet actief BREBO arbeidstarief voor glas.');
    }
    return [
      'priced' => TRUE,
      'unit_cost' => $rate,
      'source_ref' => 'norm:glass:labour_cost_per_hour',
      'source_date' => date('Y-m-d'),
      'confidence' => 'B',
      'label' => 'BREBO glas arbeidstarief',
      'reason' => 'Actieve centrale tariefnorm passend op de glascontext.',
    ];
  }

  /** @return array<string,mixed> */
  private function unpriced(string $reason): array {
    return ['priced'=>FALSE,'unit_cost'=>0.0,'source_ref'=>NULL,'source_date'=>NULL,'confidence'=>'D','label'=>'Ongeprijsd','reason'=>$reason];
  }
}
