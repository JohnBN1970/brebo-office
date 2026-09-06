<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the controller depends only on its read model and date formatting. */
final class IntakeReviewControllerServiceTest extends UnitTestCase {

  public function testControllerDependenciesStayNarrow(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("get('brebo_data_intake.review_repository')", $source);
    self::assertStringContainsString("get('date.formatter')", $source);
    self::assertStringNotContainsString("get('entity_type.manager')", $source);
  }

}
