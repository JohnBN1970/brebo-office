<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first bounded queue does not add pager/session complexity. */
final class IntakeReviewNoPagerTest extends UnitTestCase {

  public function testControllerDoesNotUsePager(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('pager', strtolower($source));
  }

}
