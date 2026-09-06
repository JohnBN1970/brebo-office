<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the overview does not fetch canonical source content at render time. */
final class IntakeReviewNoRemoteFetchTest extends UnitTestCase {

  public function testControllerDoesNotFetchSourceContent(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('file_get_contents(', $source);
    self::assertStringNotContainsString('request(', $source);
  }

}
