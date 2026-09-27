<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationLegacyLineMirrorPolicyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/** Drupal-backed policy for optional legacy calculation-line mirror creation. */
final class DrupalCalculationLegacyLineMirrorPolicy implements CalculationLegacyLineMirrorPolicyInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function createLegacyMirrors(): bool {
    return (bool) $this->configFactory
      ->get('brebo_calculation.settings')
      ->get('legacy_line_mirror_enabled');
  }

}
