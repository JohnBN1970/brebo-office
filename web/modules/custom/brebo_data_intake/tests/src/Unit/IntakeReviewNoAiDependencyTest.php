<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies human review remains usable without an AI service. */
final class IntakeReviewNoAiDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotRequireAiService(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('openai', strtolower($source));
    self::assertStringNotContainsString('anthropic', strtolower($source));
  }

}
