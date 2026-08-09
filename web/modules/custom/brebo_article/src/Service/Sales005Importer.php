<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Imports Ketenstandaard SALES005 catalogues into BREBO Artikelbeheer.
 */
final class Sales005Importer {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly FileSystemInterface $fileSystem,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Imports an XML file or a ZIP containing exactly one XML catalogue.
   *
   * @return array<string, int|string>
   *   A compact, user-facing import result.
   */
  public function import(string $uri, string $sourceName): array {
    [$xmlPath, $temporaryPath] = $this->resolveXmlPath($uri);

    try {
      $sourceHash = hash_file('sha256', $xmlPath);
      if ($sourceHash === FALSE) {
        throw new \RuntimeException('De SHA-256-bronhash kon niet worden bepaald.');
      }

      $existing = $this->database->select('brebo_catalog_import', 'ci')
        ->fields('ci', ['id', 'record_count', 'status'])
        ->condition('source_hash', $sourceHash)
        ->execute()
        ->fetchAssoc();
      if ($existing && in_array($existing['status'], ['completed', 'actief'], TRUE)) {
        // Older importer versions stored successful catalogues as "completed",
        // while the article search deliberately exposes only active catalogues.
        // Promote such an import without creating duplicate price versions.
        if ($existing['status'] === 'completed') {
          $this->database->update('brebo_catalog_import')
            ->fields(['status' => 'actief'])
            ->condition('id', (int) $existing['id'])
            ->execute();
        }
        return [
          'status' => 'already_imported',
          'import_id' => (int) $existing['id'],
          'records' => (int) $existing['record_count'],
        ];
      }

      $metadata = $this->readMetadata($xmlPath);
      $transaction = $this->database->startTransaction();
      try {
        $supplierId = $this->upsertSupplier($metadata);
        $importId = $existing
          ? (int) $existing['id']
          : (int) $this->database->insert('brebo_catalog_import')->fields([
            'supplier_id' => $supplierId,
            'standard' => 'SALES005',
            'source_name' => mb_substr($sourceName, 0, 255),
            'source_hash' => $sourceHash,
            'price_date' => $metadata['price_date'],
            'status' => 'processing',
            'record_count' => 0,
            'created' => $this->time->getRequestTime(),
          ])->execute();

        $counts = $this->importLines($xmlPath, $supplierId, $importId, $metadata);
        $this->database->update('brebo_catalog_import')->fields([
          'status' => 'actief',
          'record_count' => $counts['records'],
        ])->condition('id', $importId)->execute();

        return ['status' => 'completed', 'import_id' => $importId] + $counts;
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
    }
    finally {
      if ($temporaryPath !== NULL && is_file($temporaryPath)) {
        @unlink($temporaryPath);
      }
    }
  }

  /** @return array{0: string, 1: ?string} */
  private function resolveXmlPath(string $uri): array {
    $path = $this->fileSystem->realpath($uri) ?: $uri;
    if (!is_file($path)) {
      throw new \InvalidArgumentException('Het geuploade bronbestand is niet leesbaar.');
    }
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'zip') {
      return [$path, NULL];
    }

    $zip = new \ZipArchive();
    if ($zip->open($path) !== TRUE) {
      throw new \InvalidArgumentException('Het ZIP-bestand kan niet worden geopend.');
    }
    $xmlIndexes = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
      $name = (string) $zip->getNameIndex($index);
      if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'xml') {
        $xmlIndexes[] = $index;
      }
    }
    if (count($xmlIndexes) !== 1) {
      $zip->close();
      throw new \InvalidArgumentException('Een SALES005-ZIP moet precies één XML-bestand bevatten.');
    }
    $temporaryPath = tempnam(sys_get_temp_dir(), 'brebo-sales005-');
    $stream = $zip->getStream((string) $zip->getNameIndex($xmlIndexes[0]));
    $target = $temporaryPath !== FALSE ? fopen($temporaryPath, 'wb') : FALSE;
    if ($stream === FALSE || $target === FALSE) {
      $zip->close();
      throw new \RuntimeException('Het XML-bestand kon niet veilig worden uitgepakt.');
    }
    stream_copy_to_stream($stream, $target);
    fclose($stream);
    fclose($target);
    $zip->close();
    return [$temporaryPath, $temporaryPath];
  }

  /** @return array<string, string> */
  private function readMetadata(string $xmlPath): array {
    $reader = new \XMLReader();
    if (!$reader->open($xmlPath, NULL, LIBXML_NONET | LIBXML_COMPACT)) {
      throw new \InvalidArgumentException('De XML-catalogus kan niet worden gelezen.');
    }
    $metadata = ['catalogue_number' => '', 'price_date' => '', 'supplier_gln' => '', 'supplier_name' => '', 'supplier_branch' => ''];
    while ($reader->read()) {
      if ($reader->nodeType !== \XMLReader::ELEMENT) {
        continue;
      }
      if ($reader->localName === 'PriceCatalogueNumber' && $metadata['catalogue_number'] === '') {
        $metadata['catalogue_number'] = trim($reader->readString());
      }
      elseif ($reader->localName === 'StartDate' && $metadata['price_date'] === '') {
        $metadata['price_date'] = trim($reader->readString());
      }
      elseif ($reader->localName === 'Supplier') {
        $supplier = simplexml_load_string($reader->readOuterXml());
        if ($supplier !== FALSE) {
          $metadata['supplier_gln'] = $this->value($supplier, 'GLN');
          $metadata['supplier_name'] = $this->value($supplier, 'Name');
          $metadata['supplier_branch'] = $this->value($supplier, 'City');
        }
        break;
      }
    }
    $reader->close();
    if ($metadata['supplier_name'] === '' || $metadata['price_date'] === '') {
      throw new \InvalidArgumentException('Dit bestand mist verplichte SALES005-catalogusgegevens.');
    }
    return $metadata;
  }

  /** @param array<string, string> $metadata */
  private function upsertSupplier(array $metadata): int {
    $code = $metadata['supplier_gln'] !== '' ? 'GLN-' . $metadata['supplier_gln'] : 'SALES-' . substr(hash('sha256', $metadata['supplier_name']), 0, 16);
    $now = $this->time->getRequestTime();
    $supplierFields = [
      'name' => $metadata['supplier_name'],
      'branch' => $metadata['supplier_branch'],
      'active' => 1,
      'changed' => $now,
    ];
    $this->database->merge('brebo_supplier')
      ->keys(['code' => $code])
      ->fields($supplierFields)
      ->insertFields([
        'code' => $code,
        'created' => $now,
      ] + $supplierFields)
      ->execute();
    return (int) $this->database->select('brebo_supplier', 's')->fields('s', ['id'])->condition('code', $code)->execute()->fetchField();
  }

  /**
   * @param array<string, string> $metadata
   * @return array{records: int, articles_created: int, articles_updated: int, prices: int}
   */
  private function importLines(string $xmlPath, int $supplierId, int $importId, array $metadata): array {
    $counts = ['records' => 0, 'articles_created' => 0, 'articles_updated' => 0, 'prices' => 0];
    $reader = new \XMLReader();
    $reader->open($xmlPath, NULL, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
    while ($reader->read()) {
      if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'TradeItemLine') {
        continue;
      }
      $line = simplexml_load_string($reader->readOuterXml());
      if ($line === FALSE) {
        throw new \InvalidArgumentException('Een artikelregel bevat ongeldige XML.');
      }
      $supplierArticleNo = $this->value($line, 'TradeItemIdentification/SuppliersTradeItemId');
      $description = $this->value($line, 'MultiLanguageTradeItemDescription/TradeItemDescription/Description');
      if ($supplierArticleNo === '' || $description === '') {
        throw new \InvalidArgumentException('Een artikelregel mist artikelnummer of omschrijving.');
      }
      $code = 'JON-' . $supplierArticleNo;
      $gtin = $this->value($line, 'TradeItemIdentification/GTIN');
      $productGroup = $this->value($line, 'TradeItemGrouping/BuyingGroup');
      $orderUnit = $this->value($line, 'OrderConditions/OrderUoM');
      $useUnit = $this->value($line, 'UseUnitInformation/UseUnitUoM') ?: $orderUnit ?: 'PCE';
      $articleId = $this->database->select('brebo_article', 'a')->fields('a', ['id'])->condition('code', $code)->execute()->fetchField();
      $articleFields = [
        'description' => mb_substr($description, 0, 512),
        'search_text' => mb_strtolower($description . ' ' . $supplierArticleNo . ' ' . $gtin . ' ' . $productGroup),
        'base_unit' => mb_substr($useUnit, 0, 16),
        'cost_category' => 'Materiaal',
        'active' => 1,
        'changed' => $this->time->getRequestTime(),
      ];
      if ($articleId) {
        $this->database->update('brebo_article')->fields($articleFields)->condition('id', $articleId)->execute();
        $counts['articles_updated']++;
      }
      else {
        $articleId = $this->database->insert('brebo_article')->fields(['code' => $code, 'created' => $this->time->getRequestTime()] + $articleFields)->execute();
        $counts['articles_created']++;
      }

      $supplierFields = [
        'article_id' => (int) $articleId,
        'gtin' => mb_substr($gtin, 0, 32) ?: NULL,
        'order_unit' => mb_substr($orderUnit, 0, 16) ?: NULL,
        'use_unit' => mb_substr($useUnit, 0, 16),
        'conversion_factor' => $this->decimal($this->value($line, 'OrderConditions/PriceToOrderUnitFactor'), '1'),
        'minimum_order' => $this->decimal($this->value($line, 'OrderConditions/MinimumOrderQuantity'), '1'),
        'product_group' => mb_substr($productGroup, 0, 255) ?: NULL,
        'product_url' => mb_substr($this->value($line, 'Attachment/URLInformation/URL'), 0, 1024) ?: NULL,
        'active' => 1,
      ];
      $this->database->merge('brebo_supplier_article')->keys(['supplier_id' => $supplierId, 'supplier_article_no' => $supplierArticleNo])->fields($supplierFields)->execute();
      $supplierArticleId = (int) $this->database->select('brebo_supplier_article', 'sa')->fields('sa', ['id'])->condition('supplier_id', $supplierId)->condition('supplier_article_no', $supplierArticleNo)->execute()->fetchField();

      $priceNodes = $this->nodes($line, 'PriceInformation');
      foreach ($priceNodes as $price) {
        $netPrice = $this->value($price, 'NetPrice');
        if ($netPrice === '') {
          continue;
        }
        $quantityFrom = $this->decimal($this->value($price, 'MinimumQuantity'), '1');
        $validFrom = $this->value($price, 'StartDatePriceInformation') ?: $metadata['price_date'];
        $this->database->merge('brebo_article_price')->keys([
          'supplier_article_id' => $supplierArticleId,
          'catalog_import_id' => $importId,
          'valid_from' => $validFrom,
          'quantity_from' => $quantityFrom,
        ])->fields([
          'valid_until' => $this->value($price, 'EndDatePriceInformation') ?: NULL,
          'net_price' => $this->decimal($netPrice, '0'),
          'gross_price' => $this->value($price, 'GrossPrice') !== '' ? $this->decimal($this->value($price, 'GrossPrice'), '0') : NULL,
          'currency' => 'EUR',
          'vat_rate' => $this->value($line, 'VATInformation/VATPercentage') !== '' ? $this->decimal($this->value($line, 'VATInformation/VATPercentage'), '0') : NULL,
        ])->execute();
        $counts['prices']++;
      }
      $counts['records']++;
    }
    $reader->close();
    if ($counts['records'] === 0) {
      throw new \InvalidArgumentException('Er zijn geen SALES005-artikelregels gevonden.');
    }
    return $counts;
  }

  private function decimal(string $value, string $default): string {
    $normalized = str_replace(',', '.', trim($value));
    return is_numeric($normalized) ? $normalized : $default;
  }

  /** Returns namespace-independent child elements for a slash-separated path. */
  private function nodes(\SimpleXMLElement $node, string $path): array {
    $xpath = './' . implode('/', array_map(
      static fn(string $name): string => '*[local-name()="' . $name . '"]',
      explode('/', $path),
    ));
    return $node->xpath($xpath) ?: [];
  }

  /** Returns the trimmed value of the first namespace-independent match. */
  private function value(\SimpleXMLElement $node, string $path): string {
    $matches = $this->nodes($node, $path);
    return isset($matches[0]) ? trim((string) $matches[0]) : '';
  }

}
