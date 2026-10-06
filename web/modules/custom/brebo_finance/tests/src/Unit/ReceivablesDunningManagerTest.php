<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_finance\Unit;

use Drupal\brebo_finance\Service\ReceivablesDunningManager;
use Drupal\brebo_finance\Contract\ReceivablesDunningRepositoryInterface;
use Drupal\brebo_finance\Contract\ReceivablesDunningScheduleSourceInterface;
use PHPUnit\Framework\TestCase;

/** @coversDefaultClass \Drupal\brebo_finance\Service\ReceivablesDunningManager */
final class ReceivablesDunningManagerTest extends TestCase {

  public function testDisputeBlocksEscalation(): void {
    $manager = $this->manager([
      'id' => 1,
      'invoice_number' => '2026-001',
      'due_date' => '2026-09-01',
      'status' => 'disputed',
      'amount_inc_vat' => '121.00',
      'paid_amount_inc_vat' => '0.00',
      'dispute_reason' => 'Inhoudelijk betwist',
    ]);

    self::assertNull($manager->nextStep(1, new \DateTimeImmutable('2026-09-20')));
    self::assertSame('betwist', $manager->state(1, new \DateTimeImmutable('2026-09-20'))['status']);
  }

  public function testPaidInvoiceStopsEscalation(): void {
    $manager = $this->manager([
      'id' => 1,
      'invoice_number' => '2026-002',
      'due_date' => '2026-09-01',
      'status' => 'paid',
      'amount_inc_vat' => '121.00',
      'paid_amount_inc_vat' => '121.00',
      'dispute_reason' => NULL,
    ]);

    self::assertNull($manager->nextStep(1, new \DateTimeImmutable('2026-09-20')));
    self::assertSame('betaald', $manager->state(1, new \DateTimeImmutable('2026-09-20'))['status']);
  }

  public function testFirstReminderBecomesDue(): void {
    $manager = $this->manager([
      'id' => 1,
      'invoice_number' => '2026-003',
      'due_date' => '2026-09-01',
      'status' => 'overdue',
      'amount_inc_vat' => '121.00',
      'paid_amount_inc_vat' => '21.00',
      'dispute_reason' => NULL,
    ]);

    self::assertSame('reminder', $manager->nextStep(1, new \DateTimeImmutable('2026-09-04')));
    self::assertSame('100.00', $manager->state(1, new \DateTimeImmutable('2026-09-04'))['outstanding_amount_inc_vat']);
  }

  /** @param array<string,mixed> $invoice */
  private function manager(array $invoice): ReceivablesDunningManager {
    $repository = $this->createMock(ReceivablesDunningRepositoryInterface::class);
    $repository->method('salesInvoice')->with(1)->willReturn($invoice);
    $repository->method('state')->with(1)->willReturn([]);

    $scheduleSource = $this->createMock(ReceivablesDunningScheduleSourceInterface::class);
    $scheduleSource->method('scheduleSettings')->willReturn([
      'reminder' => NULL,
      'demand' => NULL,
      'final_notice' => NULL,
      'collection_ready' => NULL,
    ]);

    return new ReceivablesDunningManager($repository, $scheduleSource);
  }
}
