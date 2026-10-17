<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Composes XLSX extraction, weekday detection, day rows and review flags.
 * Requires explicit header positions; does not guess supplier, calendar year or project IDs.
 */
final class OnSiteExcelWeekImportPipeline {
  /**
   * @param array<string,mixed> $layout sheet_name => header_row, first_data_row, person_column, project_column
   * @return array{rows:list<array<string,mixed>>,issues:list<array<string,mixed>>}
   */
  public function import(string $filename, array $layout): array {
    $reader = new OnSiteXlsxCellReader();
    $detector = new OnSiteExcelWeekSheetDetector();
    $extractor = new OnSiteExcelWeekRowExtractor();
    $normalizer = new OnSiteWeeklyTimesheetImport();
    $book = $reader->read($filename);
    $rows = [];
    $issues = [];
    foreach ($book['cells'] as $sheetId => $cells) {
      $name = $book['sheet_names'][$sheetId] ?? $sheetId;
      if (!isset($layout[$name])) {
        $issues[] = ['sheet'=>$name,'reason'=>'sheet_layout_not_confirmed'];
        continue;
      }
      $cfg = $layout[$name];
      $headerRow = (int)($cfg['header_row'] ?? 0);
      $firstRow = (int)($cfg['first_data_row'] ?? 0);
      $person = (string)($cfg['person_column'] ?? '');
      $project = (string)($cfg['project_column'] ?? '');
      if ($headerRow < 1 || $firstRow <= $headerRow || $person === '' || $project === '') {
        $issues[] = ['sheet'=>$name,'reason'=>'invalid_sheet_layout'];
        continue;
      }
      $headers = [];
      foreach ($cells as $address => $value) {
        if (preg_match('/^([A-Z]{1,3})'.preg_quote((string)$headerRow,'/').'$/D', $address, $m)) {
          $headers[$m[1]] = $value;
        }
      }
      $days = $detector->dayColumns($headers);
      if (count($days) < 5) {
        $issues[] = ['sheet'=>$name,'reason'=>'weekday_headers_missing'];
        continue;
      }
      $extracted = $extractor->extract($name,$cells,$book['comments'][$sheetId] ?? [],$days,$person,$project,$firstRow);
      $normalized = $normalizer->normalize($extracted['entries']);
      array_push($rows,...$normalized['rows']);
      array_push($issues,...$extracted['issues'],...$normalized['issues']);
    }
    return ['rows'=>$rows,'issues'=>$issues];
  }
}
