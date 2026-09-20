<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_finance\Unit;

use Drupal\brebo_finance\Service\ReceivablesDunningManager;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
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
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('brebo_finance_sales_invoice')->willReturn(TRUE);

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn($invoice);

    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')->willReturn($select);

    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->method('get')->willReturn([]);
    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->willReturn($store);

    $config = $this->createMock(Config::class);
    $config->method('get')->willReturn(NULL);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    return new ReceivablesDunningManager($database, $factory, $configFactory);
  }
}
