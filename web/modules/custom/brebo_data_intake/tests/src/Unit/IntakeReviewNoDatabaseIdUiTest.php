<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies operator-facing table stays free of database IDs. */
final class IntakeReviewNoDatabaseIdUiTest extends UnitTestCase {

  public function testControllerDoesNotLabelAnyIdColumn(): void {
    $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($controller);
    self::assertStringNotContainsString("$this->t('ID')", $controller);
  }

}
