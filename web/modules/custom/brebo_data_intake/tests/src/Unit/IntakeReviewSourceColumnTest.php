<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies operators see the human source of each pending item. */
final class IntakeReviewSourceColumnTest extends UnitTestCase {

  public function testTableShowsSourceColumn(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$this->t('Bron')", $source);
  }

}
