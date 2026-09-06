<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies normalized payload decoding is explicit and strict. */
final class IntakeReviewPayloadDecodeTest extends UnitTestCase {

  public function testRepositoryUsesThrowingJsonDecode(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString('json_decode((string) $values[\'payload\'], TRUE, 512, JSON_THROW_ON_ERROR)', $source);
  }

}
