<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

/**
 * Enforces the minimum human/model review route for procurement decisions.
 */
final class ProcurementControlModeGate {

  public function __construct(private readonly ProcurementContextIntelligenceService $contextIntelligence) {}

  /**
   * @param array<string, mixed> $context
   * @return array<string, mixed>
   */
  public function assess(array $context, string $requestedMode): array {
    $requestedMode = strtolower(trim($requestedMode));
    $allowedModes = ['model_led', 'hybrid_review', 'senior_human_review'];
    if (!in_array($requestedMode, $allowedModes, TRUE)) {
      throw new \InvalidArgumentException('Onbekende controlmodus.');
    }

    $analysis = $this->contextIntelligence->analyze();
    $requiredMode = $this->requiredMode($analysis, $context);
    $rank = ['model_led' => 1, 'hybrid_review' => 2, 'senior_human_review' => 3];
    $allowed = $rank[$requestedMode] >= $rank[$requiredMode];

    return [
      'allowed' => $allowed,
      'requested_mode' => $requestedMode,
      'required_mode' => $requiredMode,
      'message' => $allowed
        ? 'Gekozen beoordelingsroute voldoet aan de vereiste controlmodus.'
        : 'Beoordelingsroute geblokkeerd: deze context vereist minimaal ' . $requiredMode . '.',
      'analysis_basis' => $analysis,
    ];
  }

  /**
   * @param array<string, mixed> $analysis
   * @param array<string, mixed> $context
   */
  private function requiredMode(array $analysis, array $context): string {
    $segments = (array) ($analysis['segments'] ?? []);
    $matchedModes = [];
    foreach ($segments as $segment) {
      $segmentName = (string) ($segment['segment'] ?? '');
      if ($this->matches($segmentName, $context)) {
        $matchedModes[] = (string) ($segment['recommended_control_mode'] ?? 'insufficient_data');
      }
    }

    if ($matchedModes !== []) {
      if (in_array('senior_human_review', $matchedModes, TRUE)) {
        return 'senior_human_review';
      }
      if (in_array('hybrid_review', $matchedModes, TRUE)) {
        return 'hybrid_review';
      }
      if (in_array('model_led', $matchedModes, TRUE)) {
        return 'model_led';
      }
    }

    // Conservative fallback for high-impact or poorly evidenced procurement.
    if (!empty($context['new_supplier'])
      || in_array(strtolower((string) ($context['warranty_intensity'] ?? '')), ['high', 'hoog'], TRUE)
      || in_array(strtolower((string) ($context['quality_criticality'] ?? '')), ['high', 'hoog'], TRUE)
      || in_array(strtolower((string) ($context['contract_complexity'] ?? '')), ['high', 'hoog'], TRUE)) {
      return 'senior_human_review';
    }

    if (in_array(strtolower((string) ($context['planning_criticality'] ?? '')), ['high', 'hoog'], TRUE)
      || in_array(strtolower((string) ($context['supplier_confidence'] ?? '')), ['low', 'laag', 'insufficient', 'onvoldoende'], TRUE)) {
      return 'hybrid_review';
    }

    return 'model_led';
  }

  /** @param array<string, mixed> $context */
  private function matches(string $segment, array $context): bool {
    if ($segment === 'supplier:new') {
      return !empty($context['new_supplier']);
    }
    [$key, $value] = array_pad(explode(':', $segment, 2), 2, '');
    if ($key === '' || $value === '' || !array_key_exists($key, $context)) {
      return FALSE;
    }
    return strtolower((string) $context[$key]) === $value;
  }
}
