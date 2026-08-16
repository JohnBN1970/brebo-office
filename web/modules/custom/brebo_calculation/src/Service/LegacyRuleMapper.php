<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\RuleType;

/** Maps legacy line/post semantics to the canonical calculation rule type. */
final class LegacyRuleMapper {

  /** @return array{type: RuleType, warning: ?string} */
  public function map(string $lineType, string $postType): array {
    if ($lineType === 'Notitie') {
      return ['type' => RuleType::Note, 'warning' => NULL];
    }
    if ($lineType === 'Verdisconteerd') {
      return ['type' => RuleType::Distributed, 'warning' => NULL];
    }

    return match ($postType) {
      'Vaste post' => ['type' => RuleType::Normal, 'warning' => NULL],
      'Stelpost' => ['type' => RuleType::Allowance, 'warning' => NULL],
      'Verrekenpost' => ['type' => RuleType::Adjustable, 'warning' => NULL],
      'Optie' => ['type' => RuleType::Option, 'warning' => NULL],
      'Alternatief' => ['type' => RuleType::Option, 'warning' => 'Legacy posttype Alternatief is voorlopig als optie gemapt en vereist functionele controle.'],
      'Meer-/minderwerk' => ['type' => RuleType::Adjustable, 'warning' => 'Legacy posttype Meer-/minderwerk is voorlopig als verrekenbaar gemapt en vereist functionele controle.'],
      default => ['type' => RuleType::Normal, 'warning' => sprintf('Onbekend legacy posttype "%s" is voorlopig als normale regel gemapt.', $postType)],
    };
  }

}
