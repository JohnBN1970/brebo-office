<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

/** Evaluates price-source quality for calculation recipe lines. */
final class RecipePriceHealthInspector {

  /**
   * @param array<string,mixed> $line
   * @return array{level:string,code:string,label:string,price_date:?string,age_days:?int}
   */
  public function inspect(array $line, ?\DateTimeImmutable $today = NULL): array {
    $today ??= new \DateTimeImmutable('today');
    $lineType = strtolower(trim((string) ($line['line_type'] ?? '')));
    $isMaterial = in_array($lineType, ['material', 'materiaal'], TRUE);
    $unitCost = (float) ($line['unit_cost'] ?? 0);
    $materialRef = trim((string) ($line['material_ref'] ?? ''));
    $priceRef = trim((string) ($line['price_source_ref'] ?? ''));

    if (!$isMaterial) {
      return ['level' => 'ok', 'code' => 'not_material', 'label' => 'Geen materiaalprijs', 'price_date' => NULL, 'age_days' => NULL];
    }
    if ($unitCost <= 0) {
      return ['level' => 'error', 'code' => 'missing_price', 'label' => 'Prijs ontbreekt', 'price_date' => NULL, 'age_days' => NULL];
    }
    if ($materialRef === '' || !str_starts_with($materialRef, 'article:')) {
      return ['level' => 'warning', 'code' => 'manual_price', 'label' => 'Handmatige prijs', 'price_date' => NULL, 'age_days' => NULL];
    }
    if ($priceRef === '' || !preg_match('/date:(\d{4}-\d{2}-\d{2})/', $priceRef, $match)) {
      return ['level' => 'warning', 'code' => 'missing_price_source', 'label' => 'Prijsbron ontbreekt', 'price_date' => NULL, 'age_days' => NULL];
    }

    try {
      $priceDate = new \DateTimeImmutable($match[1]);
    }
    catch (\Throwable) {
      return ['level' => 'warning', 'code' => 'invalid_price_date', 'label' => 'Prijsdatum ongeldig', 'price_date' => NULL, 'age_days' => NULL];
    }

    $ageDays = max(0, (int) $priceDate->diff($today)->format('%a'));
    if ($priceDate > $today) {
      return ['level' => 'warning', 'code' => 'future_price', 'label' => 'Prijsdatum ligt in toekomst', 'price_date' => $match[1], 'age_days' => 0];
    }
    if ($ageDays > 365) {
      return ['level' => 'error', 'code' => 'stale_price', 'label' => 'Prijs ouder dan 12 maanden', 'price_date' => $match[1], 'age_days' => $ageDays];
    }
    if ($ageDays > 180) {
      return ['level' => 'warning', 'code' => 'aging_price', 'label' => 'Prijs ouder dan 6 maanden', 'price_date' => $match[1], 'age_days' => $ageDays];
    }
    return ['level' => 'ok', 'code' => 'current_price', 'label' => 'Actuele prijsbron', 'price_date' => $match[1], 'age_days' => $ageDays];
  }

  /**
   * @param array<int,array<string,mixed>> $lines
   * @return array{errors:int,warnings:int,ok:int,issues:array<int,array<string,mixed>>}
   */
  public function summarize(array $lines): array {
    $summary = ['errors' => 0, 'warnings' => 0, 'ok' => 0, 'issues' => []];
    foreach ($lines as $line) {
      $health = $this->inspect($line);
      if ($health['level'] === 'error') { $summary['errors']++; }
      elseif ($health['level'] === 'warning') { $summary['warnings']++; }
      else { $summary['ok']++; }
      if ($health['level'] !== 'ok') {
        $summary['issues'][] = ['line_id' => (int) ($line['id'] ?? 0), 'description' => (string) ($line['description'] ?? ''), ...$health];
      }
    }
    return $summary;
  }
}
