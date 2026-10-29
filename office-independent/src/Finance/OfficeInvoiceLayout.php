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
}
