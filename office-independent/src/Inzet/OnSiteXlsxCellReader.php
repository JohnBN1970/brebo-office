<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Reads XLSX worksheet cells, including legacy Excel cell comments.
 * Never evaluates formulas or runs macros. Caller maps sheets/rows to projects.
 */
final class OnSiteXlsxCellReader {
  /** @return array{cells:array<string,array<string,string>>,comments:array<string,array<string,string>>} */
  public function read(string $filename, int $maxEntries = 2000, int $maxBytes = 30000000): array {
    if (!class_exists(\ZipArchive::class)) {
      throw new \RuntimeException('PHP ZipArchive is vereist voor XLSX-import.');
    }
    $zip = new \ZipArchive();
    if ($zip->open($filename) !== true) {
      throw new \InvalidArgumentException('Ongeldig XLSX-bestand.');
    }
    try {
      if ($zip->numFiles > $maxEntries) {
        throw new \DomainException('XLSX bevat te veel onderdelen.');
      }
      $parts = [];
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = (string)$stat['name'];
        if (str_contains($name, '..') || str_starts_with($name, '/')) {
          throw new \DomainException('Onveilig XLSX-pad.');
        }
        if ((int)$stat['size'] > $maxBytes) {
          throw new \DomainException('XLSX-onderdeel te groot.');
        }
        if (preg_match('~^xl/(?:worksheets/sheet[0-9]+\.xml|comments[0-9]+\.xml|sharedStrings\.xml)$~', $name)) {
          $parts[$name] = $zip->getFromIndex($i);
          if (!is_string($parts[$name])) {
            throw new \DomainException('XLSX-onderdeel onleesbaar.');
          }
        }
      }
      $shared = [];
      if (isset($parts['xl/sharedStrings.xml'])) {
        $xml = $this->xml($parts['xl/sharedStrings.xml']);
        foreach ($xml->si as $item) {
          $shared[] = trim((string)$item->t) !== '' ? (string)$item->t
            : implode('', array_map(static fn($run): string => (string)$run->t, iterator_to_array($item->r)));
        }
      }
      $cells = [];
      $comments = [];
      foreach ($parts as $name => $source) {
        if (preg_match('~^xl/worksheets/(sheet[0-9]+)\.xml$~', $name, $match)) {
          $xml = $this->xml($source);
          foreach ($xml->sheetData->row as $row) {
            foreach ($row->c as $cell) {
              $address = (string)$cell['r'];
              if (!preg_match('/^[A-Z]{1,3}[1-9][0-9]*$/D', $address)) continue;
              $type = (string)$cell['t'];
              $value = $type === 'inlineStr' ? (string)$cell->is->t : (string)$cell->v;
              if ($type === 's') $value = $shared[(int)$value] ?? '';
              $cells[$match[1]][$address] = $value;
            }
          }
        }
        if (preg_match('~^xl/(comments[0-9]+)\.xml$~', $name)) {
          $xml = $this->xml($source);
          foreach ($xml->commentList->comment as $comment) {
            $address = (string)$comment['ref'];
            $runs = $comment->text->r;
            $value = count($runs) ? implode('', array_map(static fn($run): string => (string)$run->t, iterator_to_array($runs)))
              : (string)$comment->text->t;
            $comments[$name][$address] = $value;
          }
        }
      }
      return ['cells'=>$cells,'comments'=>$comments];
    } finally {
      $zip->close();
    }
  }

  private function xml(string $source): \SimpleXMLElement {
    $prior = libxml_use_internal_errors(true);
    try {
      $xml = simplexml_load_string($source, \SimpleXMLElement::class, LIBXML_NONET);
      if (!$xml) throw new \DomainException('Ongeldige XLSX-XML.');
      return $xml;
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors($prior);
    }
  }
}
