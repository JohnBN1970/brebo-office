<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

/**
 * Builds a reviewable project-scope proposal from extracted source documents.
 *
 * This service deliberately produces proposals, never canonical truth. Every
 * inferred value keeps source evidence and conflicting values remain open.
 */
final class ProjectScopeInferenceEngine {

  /**
   * @param list<array<string,mixed>> $documents
   *
   * @return array<string,mixed>
   */
  public function infer(array $documents): array {
    $documentRoles = [];
    $items = [];
    $conflicts = [];
    $unreadable = [];
    $extracted = 0;

    foreach ($documents as $document) {
      $filename = trim((string) ($document['filename'] ?? 'document')) ?: 'document';
      $status = (string) ($document['status'] ?? 'unknown');
      $text = trim((string) ($document['text'] ?? ''));
      $role = $this->classifyDocument($filename, $text);
      $documentRoles[] = [
        'filename' => $filename,
        'role' => $role['role'],
        'confidence' => $role['confidence'],
        'status' => $status,
      ];

      if ($status !== 'extracted' || $text === '') {
        $unreadable[] = [
          'filename' => $filename,
          'status' => $status,
        ];
        continue;
      }
      $extracted++;

      $references = $this->extractReferences($text);
      foreach ($references as $reference) {
        $items[$reference] ??= $this->emptyItem($reference);
        $items[$reference]['sources'][$filename] = [
          'role' => $role['role'],
          'confidence' => (float) ($document['confidence'] ?? 0.0),
        ];
      }

      foreach ($this->extractQuantities($text, $filename) as $candidate) {
        $reference = $candidate['reference'];
        $items[$reference] ??= $this->emptyItem($reference);
        $items[$reference]['candidates']['quantity'][] = $candidate;
      }
      foreach ($this->extractDimensions($text, $filename) as $candidate) {
        $reference = $candidate['reference'];
        $items[$reference] ??= $this->emptyItem($reference);
        $items[$reference]['candidates']['dimensions'][] = $candidate;
      }

      $material = $this->singleSignal($text, [
        'kunststof' => ['kunststof', 'pvc'],
        'hout' => ['houten', 'hout'],
        'aluminium' => ['aluminium', 'alu '],
      ]);
      $glass = $this->singleSignal($text, [
        'HR++' => ['hr++', 'hr + +'],
        'triple' => ['triple glas', 'drievoudig glas', '3-voudig glas'],
        'dubbel' => ['dubbel glas', 'isolatieglas'],
        'vacuum' => ['vacuumglas', 'vacuümglas'],
      ]);
      $state = $this->singleSignal($text, [
        'bestaand' => ['bestaand kozijn', 'bestaande kozijnen', 'te vervangen'],
        'nieuw' => ['nieuw kozijn', 'nieuwe kozijnen', 'nieuw te plaatsen'],
      ]);

      if (count($references) === 1) {
        $reference = $references[0];
        if ($material !== NULL) {
          $items[$reference]['candidates']['material'][] = $this->signalCandidate($material, $filename, 0.72);
        }
        if ($glass !== NULL) {
          $items[$reference]['candidates']['glass'][] = $this->signalCandidate($glass, $filename, 0.7);
        }
        if ($state !== NULL) {
          $items[$reference]['candidates']['state'][] = $this->signalCandidate($state, $filename, 0.68);
        }
      }
    }

    ksort($items, SORT_NATURAL);
    $resolvedItems = [];
    foreach ($items as $reference => $item) {
      $resolved = [
        'reference' => $reference,
        'quantity' => $this->resolveField($reference, 'quantity', $item['candidates']['quantity'], $conflicts),
        'dimensions' => $this->resolveField($reference, 'dimensions', $item['candidates']['dimensions'], $conflicts),
        'material' => $this->resolveField($reference, 'material', $item['candidates']['material'], $conflicts),
        'glass' => $this->resolveField($reference, 'glass', $item['candidates']['glass'], $conflicts),
        'state' => $this->resolveField($reference, 'state', $item['candidates']['state'], $conflicts),
        'sources' => array_keys($item['sources']),
      ];
      $resolved['missing_fields'] = array_values(array_filter(
        ['quantity', 'dimensions', 'material'],
        static fn(string $field): bool => $resolved[$field] === NULL,
      ));
      $resolvedItems[] = $resolved;
    }

    $missing = [];
    if ($resolvedItems === []) {
      $missing[] = 'Geen kozijnreferenties met voldoende zekerheid herkend.';
    }
    if ($extracted === 0) {
      $missing[] = 'Geen bron kon inhoudelijk worden uitgelezen.';
    }
    if ($unreadable !== []) {
      $missing[] = count($unreadable) . ' document(en) zijn nog niet inhoudelijk uitleesbaar.';
    }

    return [
      'version' => 'project_scope_inference_v1',
      'status' => 'proposal',
      'review_required' => TRUE,
      'document_count' => count($documents),
      'extracted_document_count' => $extracted,
      'document_roles' => $documentRoles,
      'items' => $resolvedItems,
      'conflicts' => $conflicts,
      'missing_information' => $missing,
      'unreadable_documents' => $unreadable,
      'summary' => $this->summary($resolvedItems, $conflicts, $unreadable),
    ];
  }

  /** @return array{role:string,confidence:float} */
  private function classifyDocument(string $filename, string $text): array {
    $haystack = mb_strtolower($filename . "\n" . mb_substr($text, 0, 5000));
    $scores = [
      'kozijnstaat' => $this->keywordScore($haystack, ['kozijnstaat', 'kozijnenstaat', 'elementenstaat']),
      'tekening' => $this->keywordScore($haystack, ['tekening', 'plattegrond', 'gevelaanzicht', 'doorsnede', 'maatvoering']),
      'offerte' => $this->keywordScore($haystack, ['offerte', 'aanbieding', 'prijs', 'totaal', 'excl. btw']),
      'bestek' => $this->keywordScore($haystack, ['bestek', 'werkomschrijving', 'technische omschrijving']),
      'foto' => preg_match('/\.(?:jpe?g|png|heic|heif|webp)$/i', $filename) === 1 ? 2 : 0,
    ];
    arsort($scores);
    $role = (string) array_key_first($scores);
    $score = (int) reset($scores);
    if ($score <= 0) {
      return ['role' => 'onbekend', 'confidence' => 0.35];
    }
    return ['role' => $role, 'confidence' => min(0.95, 0.55 + ($score * 0.1))];
  }

  private function keywordScore(string $haystack, array $keywords): int {
    $score = 0;
    foreach ($keywords as $keyword) {
      if (str_contains($haystack, $keyword)) {
        $score++;
      }
    }
    return $score;
  }

  /** @return list<string> */
  private function extractReferences(string $text): array {
    preg_match_all('/\b(?:kozijn\s*)?k[\s._-]*0*(\d{1,4})([a-z]?)\b/iu', $text, $matches, PREG_SET_ORDER);
    $references = [];
    foreach ($matches as $match) {
      $references[] = 'K' . (int) $match[1] . strtoupper((string) ($match[2] ?? ''));
    }
    return array_values(array_unique($references));
  }

  /** @return list<array<string,mixed>> */
  private function extractQuantities(string $text, string $filename): array {
    $patterns = [
      '/\b(\d{1,4})\s*(?:st\.?|stuks?|x)\s*(?:kozijn\s*)?k[\s._-]*0*(\d{1,4})([a-z]?)\b/iu',
      '/\b(?:kozijn\s*)?k[\s._-]*0*(\d{1,4})([a-z]?)\s*[:=-]?\s*(\d{1,4})\s*(?:st\.?|stuks?)\b/iu',
    ];
    $out = [];
    foreach ($patterns as $index => $pattern) {
      preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
      foreach ($matches as $match) {
        if ($index === 0) {
          $quantity = (int) $match[1];
          $reference = 'K' . (int) $match[2] . strtoupper((string) ($match[3] ?? ''));
        }
        else {
          $reference = 'K' . (int) $match[1] . strtoupper((string) ($match[2] ?? ''));
          $quantity = (int) $match[3];
        }
        if ($quantity <= 0 || $quantity > 9999) {
          continue;
        }
        $out[] = [
          'reference' => $reference,
          'value' => $quantity,
          'source' => $filename,
          'confidence' => 0.86,
        ];
      }
    }
    return $out;
  }

  /** @return list<array<string,mixed>> */
  private function extractDimensions(string $text, string $filename): array {
    preg_match_all('/\b(?:kozijn\s*)?k[\s._-]*0*(\d{1,4})([a-z]?).{0,100}?(\d{3,4})\s*(?:x|×)\s*(\d{3,4})\s*(?:mm)?\b/iu', $text, $matches, PREG_SET_ORDER);
    $out = [];
    foreach ($matches as $match) {
      $width = (int) $match[3];
      $height = (int) $match[4];
      if ($width < 200 || $height < 200 || $width > 10000 || $height > 10000) {
        continue;
      }
      $out[] = [
        'reference' => 'K' . (int) $match[1] . strtoupper((string) ($match[2] ?? '')),
        'value' => ['width_mm' => $width, 'height_mm' => $height],
        'source' => $filename,
        'confidence' => 0.78,
      ];
    }
    return $out;
  }

  /** @param array<string,list<string>> $signals */
  private function singleSignal(string $text, array $signals): ?string {
    $haystack = mb_strtolower($text);
    $found = [];
    foreach ($signals as $value => $keywords) {
      foreach ($keywords as $keyword) {
        if (str_contains($haystack, mb_strtolower($keyword))) {
          $found[] = $value;
          break;
        }
      }
    }
    $found = array_values(array_unique($found));
    return count($found) === 1 ? $found[0] : NULL;
  }

  /** @return array<string,mixed> */
  private function signalCandidate(string $value, string $filename, float $confidence): array {
    return ['value' => $value, 'source' => $filename, 'confidence' => $confidence];
  }

  /** @return array<string,mixed> */
  private function emptyItem(string $reference): array {
    return [
      'reference' => $reference,
      'sources' => [],
      'candidates' => [
        'quantity' => [],
        'dimensions' => [],
        'material' => [],
        'glass' => [],
        'state' => [],
      ],
    ];
  }

  /**
   * @param list<array<string,mixed>> $candidates
   * @param list<array<string,mixed>> $conflicts
   *
   * @return array<string,mixed>|null
   */
  private function resolveField(string $reference, string $field, array $candidates, array &$conflicts): ?array {
    if ($candidates === []) {
      return NULL;
    }
    $groups = [];
    foreach ($candidates as $candidate) {
      $key = json_encode($candidate['value'] ?? NULL, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $groups[$key][] = $candidate;
    }
    if (count($groups) > 1) {
      $conflicts[] = [
        'reference' => $reference,
        'field' => $field,
        'values' => array_map(static fn(array $group): mixed => $group[0]['value'] ?? NULL, array_values($groups)),
        'sources' => array_values(array_unique(array_map(
          static fn(array $candidate): string => (string) ($candidate['source'] ?? ''),
          $candidates,
        ))),
      ];
      return NULL;
    }
    $group = reset($groups);
    $confidence = max(array_map(static fn(array $candidate): float => (float) ($candidate['confidence'] ?? 0.0), $group));
    return [
      'value' => $group[0]['value'] ?? NULL,
      'confidence' => $confidence,
      'evidence' => array_values(array_unique(array_map(
        static fn(array $candidate): string => (string) ($candidate['source'] ?? ''),
        $group,
      ))),
    ];
  }

  /**
   * @param list<array<string,mixed>> $items
   * @param list<array<string,mixed>> $conflicts
   * @param list<array<string,mixed>> $unreadable
   */
  private function summary(array $items, array $conflicts, array $unreadable): string {
    if ($items === []) {
      return 'Nog geen betrouwbare kozijnregels uit de beschikbare bronnen samengesteld.';
    }
    $knownQuantities = 0;
    $totalQuantity = 0;
    foreach ($items as $item) {
      if (is_array($item['quantity'] ?? NULL) && isset($item['quantity']['value'])) {
        $knownQuantities++;
        $totalQuantity += (int) $item['quantity']['value'];
      }
    }
    $parts = [count($items) . ' kozijnreferentie(s) herkend'];
    if ($knownQuantities > 0) {
      $parts[] = $knownQuantities . ' met herkend aantal' . ($totalQuantity > 0 ? ' (totaal ' . $totalQuantity . ')' : '');
    }
    if ($conflicts !== []) {
      $parts[] = count($conflicts) . ' conflict(en) open';
    }
    if ($unreadable !== []) {
      $parts[] = count($unreadable) . ' bron(nen) nog niet uitleesbaar';
    }
    return implode('; ', $parts) . '. Alle waarden zijn voorlopig en moeten worden gecontroleerd.';
  }

}
