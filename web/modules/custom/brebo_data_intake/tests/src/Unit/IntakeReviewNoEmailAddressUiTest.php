<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the generic queue is not shaped around email-only fields. */
final class IntakeReviewNoEmailAddressUiTest extends UnitTestCase {

  public function testControllerDoesNotDependOnEmailAddressFields(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("payload['from']", $source);
    self::assertStringNotContainsString("payload['to']", $source);
  }

}
