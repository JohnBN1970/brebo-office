<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Optional, untrusted positional extraction data.
 * The v1 text-only response remains valid; invalid layout is ignored.
 */
final class OfficeInvoiceLayout {
  public static function sanitize(mixed $raw): array {
    if (!is_array($raw) || !array_is_list($raw) || count($raw) > 2000) return [];
    $result = [];
    foreach ($raw as $block) {
      if (!is_array($block) || !isset($block['page'], $block['text'], $block['x'], $block['y'])
        || !is_int($block['page']) || $block['page'] < 1 || $block['page'] > 100
        || !is_string($block['text']) || strlen($block['text']) > 500
        || !is_numeric($block['x']) || !is_numeric($block['y'])) return [];
      $x = (float)$block['x'];
      $y = (float)$block['y'];
      if (!is_finite($x) || !is_finite($y) || $x < 0 || $x > 1 || $y < 0 || $y > 1) return [];
      $result[] = ['page'=>$block['page'], 'text'=>$block['text'], 'x'=>$x, 'y'=>$y];
    }
    return $result;
  }
  /**
   * Group normalized text blocks into visual rows without guessing monetary
   * columns. Consumers can inspect these rows before assigning semantics.
   */
  public static function rows(array $blocks, float $tolerance = 0.008): array {
    $blocks = self::sanitize($blocks);
    if ($blocks === []) return [];
    usort($blocks, static fn(array $a, array $b): int =>
      ($a['page'] <=> $b['page']) ?: ($a['y'] <=> $b['y']) ?: ($a['x'] <=> $b['x']));
    $rows = [];
    foreach ($blocks as $block) {
      $last = count($rows) - 1;
      if ($last >= 0 && $rows[$last]['page'] === $block['page']
        && abs($rows[$last]['y'] - $block['y']) <= $tolerance) {
        $rows[$last]['blocks'][] = $block;
      } else {
        $rows[] = ['page'=>$block['page'], 'y'=>$block['y'], 'blocks'=>[$block]];
      }
    }
    foreach ($rows as &$row) {
      usort($row['blocks'], static fn(array $a, array $b): int => $a['x'] <=> $b['x']);
    }
    unset($row);
    return $rows;
  }


  /**
   * Derive column intervals from genuine PDF header coordinates. Never infer
   * geometry from OCR text order. Returns null when headings are incomplete.
   */
  public static function columnsFromHeader(array $row): ?array {
    if (!isset($row['blocks']) || !is_array($row['blocks'])) return null;
    $aliases = [
      'article' => ['art.nr.', 'artikelnr.', 'artikelnummer'],
      'description' => ['omschrijving'],
      'specification' => ['specificatie'],
      'quantity' => ['aantal'],
      'price' => ['prijs'],
      'total' => ['totaal'],
    ];
    $found = [];
    foreach ($row['blocks'] as $block) {
      if (!isset($block['text'], $block['x']) || !is_string($block['text']) || !is_numeric($block['x'])) return null;
      $label = mb_strtolower(trim($block['text']), 'UTF-8');
      foreach ($aliases as $key => $names) {
        if (in_array($label, $names, true)) {
          if (isset($found[$key])) return null;
          $found[$key] = (float)$block['x'];
        }
      }
    }
    foreach (array_keys($aliases) as $key) if (!isset($found[$key])) return null;
    $keys = array_keys($aliases);
    $positions = array_values($found);
    $ordered = [];
    foreach ($keys as $key) $ordered[] = $found[$key];
    for ($i = 1; $i < count($ordered); $i++) {
      if ($ordered[$i] <= $ordered[$i - 1] + 0.01) return null;
    }
    $bounds = [];
    foreach ($keys as $i => $key) {
      $left = $i === 0 ? 0.0 : ($ordered[$i - 1] + $ordered[$i]) / 2;
      $right = $i === count($keys) - 1 ? 1.0 : ($ordered[$i] + $ordered[$i + 1]) / 2;
      $bounds[$key] = [$left, $right];
    }
    return $bounds;
  }

  /**
   * Extract a candidate from a visually separated invoice row. This does not
   * approve an invoice and refuses rows without unambiguous numeric columns.
   * Header column boundaries are supplier-specific and must be supplied.
   */
  public static function lineCandidate(array $row, array $columns): ?array {
    if (!isset($row['blocks']) || !is_array($row['blocks'])) return null;
    foreach (['article','description','quantity','unit','price','total'] as $key) {
      if (!isset($columns[$key]) || !is_array($columns[$key]) || count($columns[$key]) !== 2
        || !is_numeric($columns[$key][0]) || !is_numeric($columns[$key][1])) return null;
    }
    $values = [];
    foreach ($columns as $key => $bounds) {
      if (!is_array($bounds) || count($bounds) !== 2) return null;
      $values[$key] = [];
      foreach ($row['blocks'] as $block) {
        if (!isset($block['x'], $block['text']) || !is_numeric($block['x']) || !is_string($block['text'])) return null;
        if ($block['x'] >= (float)$bounds[0] && $block['x'] < (float)$bounds[1]) $values[$key][] = trim($block['text']);
      }
      $values[$key] = trim(implode(' ', $values[$key]));
    }
    foreach (['article','description','quantity','price','total'] as $key) {
      if (($values[$key] ?? '') === '') return null;
    }
    $number = static function (string $raw): ?float {
      $raw = str_replace(' ', '', $raw);
      if (!preg_match('/^\\d{1,9}(?:\\.\\d{3})*(?:,\\d{1,4})?$/D', $raw)) return null;
      return (float)str_replace(',', '.', str_replace('.', '', $raw));
    };
    $quantity = $number($values['quantity']);
    $price = $number($values['price']);
    $total = $number($values['total']);
    if ($quantity === null || $price === null || $total === null
      || abs(round($quantity * $price, 2) - $total) > 0.01) return null;
    return [
      'article' => $values['article'], 'description' => $values['description'],
      'quantity' => $quantity, 'unit' => $values['unit'],
      'unit_price' => $price, 'line_total' => $total,
      'review_required' => true,
    ];
  }

}
