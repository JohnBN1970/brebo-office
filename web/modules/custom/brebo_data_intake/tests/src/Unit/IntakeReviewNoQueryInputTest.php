<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first queue does not incorporate arbitrary request query input. */
final class IntakeReviewNoQueryInputTest extends UnitTestCase {

  public function testControllerHasNoRequestDependency(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('Request ', $source);
    self::assertStringNotContainsString('query->', $source);
  }

}
