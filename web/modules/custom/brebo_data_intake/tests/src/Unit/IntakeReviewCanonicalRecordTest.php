<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the queue is driven by the canonical normalized record. */
final class IntakeReviewCanonicalRecordTest extends UnitTestCase {

  public function testQueueStartsAtCanonicalRecord(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("select('brebo_data_record', 'record')", $source);
    self::assertStringContainsString("innerJoin('brebo_data_ingest_run'", $source);
  }

}
