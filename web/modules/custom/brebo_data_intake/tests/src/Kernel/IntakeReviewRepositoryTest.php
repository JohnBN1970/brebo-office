<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Kernel;

use Drupal\KernelTests\KernelTestBase;

/** @coversDefaultClass \Drupal\brebo_data_intake\Service\IntakeReviewRepository */
final class IntakeReviewRepositoryTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'brebo_data_intake'];

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('brebo_data_intake', ['brebo_data_source', 'brebo_data_ingest_run', 'brebo_data_record']);
  }

  public function testPendingQueuePreservesSourceProvenance(): void {
    $ingest = $this->container->get('brebo_data_intake.ingest_manager');
    $sourceId = $ingest->registerSource('test-mail', 'Test mailbox', 'mail', 'test');
    $runId = $ingest->startRun($sourceId, 'test', 'message:42');
    $recordId = $ingest->addRecord($runId, 'document', ['subject' => 'Factuur 42', 'project_name' => 'Testproject'], '42', 'message:42', .72, 'review_required');
    $ingest->finishRun($runId, 'completed', ['record_count' => 1]);

    $rows = $this->container->get('brebo_data_intake.review_repository')->pending();
    self::assertCount(1, $rows);
    self::assertSame($recordId, (int) $rows[0]['id']);
    self::assertSame('Test mailbox', $rows[0]['source_label']);
    self::assertSame('Factuur 42', $rows[0]['payload']['subject']);
    self::assertSame('message:42', $rows[0]['source_reference']);
  }

}
