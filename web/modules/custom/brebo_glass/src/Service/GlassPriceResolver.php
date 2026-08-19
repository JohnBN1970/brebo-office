<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_calculation\Service\CalculationNormLibrary;
use Drupal\Core\Database\Connection;

/** Resolves glass material and labour rates only from governed BREBO sources. */
final class GlassPriceResolver {
  public function __construct(
    private readonly Connection $database,
    private readonly CalculationNormLibrary $norms,
  ) {}

  /** @param array<string,mixed> $calculationContext @return array<string,array<string,mixed>> */
  public function resolve(array $calculationContext): array {
    return [
      'material' => $this->material($calculationContext),
      'labour' => $this->labour($calculationContext),
    ];
  }

  /** @param array<string,mixed> $context @return array<string,mixed> */
  private function material(array $context): array {
    $required = ['brebo_article','brebo_supplier_article','brebo_article_price','brebo_catalog_import','brebo_supplier'];
    foreach ($required as $table) {
      if (!$this->database->schema()->tableExists($table)) {
        return $this->unpriced('Artikelcatalogus niet volledig beschikbaar.');
      }
    }

    $recommended = trim((string) ($context['recommended_glass_ref'] ?? ''));
    $productCode = trim(explode('—', $recommended, 2)[0] ?? '');
    if ($productCode === '') {
      return $this->unpriced('Geen exacte productcode op de glaspositie.');
    }

    $quantity = max(0.0, (float) ($context['material_quantity_m2'] ?? 0));
    $today = date('Y-m-d');
    $query = $this->database->select('brebo_article', 'a');
    $query->innerJoin('brebo_supplier_article', 'sa', 'sa.article_id = a.id');
    $query->innerJoin('brebo_supplier', 's', 's.id = sa.supplier_id');
    $query->innerJoin('brebo_article_price', 'p', 'p.supplier_article_id = sa.id');
    $query->innerJoin('brebo_catalog_import', 'ci', 'ci.id = p.catalog_import_id');
    $query->fields('a', ['id','code','description','base_unit']);
    $query->addField('sa', 'id', 'supplier_article_id');
    $query->fields('sa', ['supplier_article_no','use_unit']);
    $query->addField('s', 'name', 'supplier_name');
    $query->addField('p', 'id', 'price_id');
    $query->fields('p', ['net_price','currency','valid_from','valid_until','quantity_from']);
    $query->addField('ci', 'id', 'catalog_import_id');
    $query->condition('a.code', $productCode)->condition('a.active', 1)->condition('sa.active', 1)->condition('s.active', 1);
    $query->condition('p.valid_from', $today, '<=');
    $validity = $query->orConditionGroup()->condition('p.valid_until', NULL, 'IS NULL')->condition('p.valid_until', $today, '>=');
    $query->condition($validity)->condition('p.quantity_from', max(1.0, $quantity), '<=');
    $query->orderBy('p.valid_from', 'DESC')->orderBy('p.quantity_from', 'DESC')->range(0, 1);
    $row = $query->execute()->fetchAssoc();
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
