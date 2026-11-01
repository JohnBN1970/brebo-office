<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** CSV export for authorized, already filtered hours-report rows. */
final class OnSiteHoursReportCsv {
  /**
   * @param list<array<string,mixed>> $rows Output of OnSiteHoursReviewReport::filter().
   */
  public static function render(array $rows): string {
    $stream = fopen('php://temp', 'w+');
    if ($stream === false) throw new \RuntimeException('CSV export niet beschikbaar.');
    try {
      fwrite($stream, "\xEF\xBB\xBF");
      fputcsv($stream, ['Project','Medewerker','Begin','Einde','Status','Oorspronkelijke uren',
        'Geaccepteerde uren','Verschil uren','Beoordelaar','Beoordeeld op','Toelichting'], ';', '"', '');
      foreach ($rows as $row) {
        $hours = static fn(int $seconds):string => number_format($seconds / 3600, 2, ',', '');
        $safe = static function (mixed $value):string {
          $s = (string)($value ?? '');
          // Protect spreadsheet users from formula injection.
          return preg_match('/^[\s\x00-\x1f]*[=+@\-]/u', $s) ? "'" . $s : $s;
        };
        fputcsv($stream, [
          $safe($row['project_id'] ?? ''), $safe($row['person_id'] ?? ''),
          $safe($row['started_at'] ?? ''), $safe($row['ended_at'] ?? ''),
          $safe($row['review_status'] ?? 'pending'),
          $hours((int)($row['original_seconds'] ?? 0)),
          $hours((int)($row['accepted_seconds'] ?? 0)),
          isset($row['difference_seconds']) ? $hours((int)$row['difference_seconds']) : '',
          $safe($row['reviewer_operator_id'] ?? ''), $safe($row['reviewed_at'] ?? ''),
          $safe($row['review_reason'] ?? ''),
        ], ';', '"', '');
      }
      rewind($stream);
      $csv = stream_get_contents($stream);
      if ($csv === false) throw new \RuntimeException('CSV export mislukt.');
      return $csv;
    } finally {
      fclose($stream);
    }
  }
}
