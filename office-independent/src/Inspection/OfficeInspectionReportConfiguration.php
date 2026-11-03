<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

use InvalidArgumentException;

/** Report selection is separate from the canonical inspection record. */
final class OfficeInspectionReportConfiguration {
  public const SECTIONS = [
    'cover', 'scope', 'method', 'findings', 'photos',
    'risks', 'advice', 'cost', 'conclusion', 'appendix'
  ];

  public static function normalize(array $input): array {
    $type = $input['report_type'] ?? 'expertise';
    if (!is_string($type) || !in_array($type, ['expertise','inspection','delivery','damage','mjop'], true)) {
      throw new InvalidArgumentException('invalid_report_type');
    }
    $sections = $input['sections'] ?? ['cover','scope','method','findings','photos','risks','advice','conclusion','appendix'];
    if (!is_array($sections) || !array_is_list($sections)) throw new InvalidArgumentException('invalid_report_sections');
    $normalized = [];
    foreach ($sections as $section) {
      if (!is_string($section) || !in_array($section, self::SECTIONS, true) || in_array($section, $normalized, true)) {
        throw new InvalidArgumentException('invalid_report_section');
      }
      $normalized[] = $section;
    }
    if (!in_array('findings', $normalized, true)) throw new InvalidArgumentException('report_requires_findings');
    $notes = $input['notes'] ?? '';
    if (!is_string($notes) || mb_strlen($notes) > 4000) throw new InvalidArgumentException('invalid_report_notes');
    return ['report_type' => $type, 'sections' => $normalized, 'notes' => trim($notes)];
  }
}
