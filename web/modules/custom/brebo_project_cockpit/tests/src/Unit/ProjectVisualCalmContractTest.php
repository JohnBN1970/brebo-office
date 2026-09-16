<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_project_cockpit\Unit;

use PHPUnit\Framework\TestCase;

/** Locks calm project styling to the BREBO design tokens. */
final class ProjectVisualCalmContractTest extends TestCase {

  public function testProjectVisualHierarchyUsesBreboTokens(): void {
    $root = dirname(__DIR__, 3);
    $css = file_get_contents(dirname($root, 3) . '/themes/custom/brebo_office/css/office-calm.css');

    self::assertIsString($css);
    self::assertStringContainsString('var(--brebo-accent-primary)', $css);
    self::assertStringContainsString('.brebo-context-tabs__items > a.is-active', $css);
    self::assertStringContainsString('box-shadow: inset 0 -3px 0 var(--brebo-accent-primary);', $css);
    self::assertStringContainsString('background: var(--brebo-bg-muted);', $css);
    self::assertStringContainsString('border-bottom-color: var(--brebo-calm-accent-line);', $css);
    self::assertStringContainsString('var(--brebo-status-warning)', $css);
    self::assertStringNotContainsString('#f47b20', $css, 'Use design tokens instead of hard-coded BREBO orange in the calm UI layer.');
  }

}
