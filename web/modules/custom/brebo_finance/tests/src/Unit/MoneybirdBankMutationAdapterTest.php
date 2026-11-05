<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_finance\Unit;

use Drupal\brebo_finance\Service\MoneybirdBankMutationAdapter;
use PHPUnit\Framework\TestCase;

final class MoneybirdBankMutationAdapterTest extends TestCase {

  public function testMapsOnlyVerifiedBankMutationsWithoutInvoiceData(): void {
    $adapter = new MoneybirdBankMutationAdapter();
    $result = $adapter->map([
      'accounts' => [['id' => 'bank-1', 'currency' => 'EUR']],
      'transactions' => [
        ['id' => 'm1', 'financial_account_id' => 'bank-1', 'amount' => '-25.00', 'date' => '2026-11-05', 'message' => 'BREBO 123', 'invoice' => ['secret' => 'no']],
        ['id' => 'm1', 'financial_account_id' => 'bank-1', 'amount' => '-25.00'],
        ['id' => 'm2', 'financial_account_id' => 'unknown', 'amount' => '30.00'],
        ['id' => 'm3', 'financial_account_id' => 'bank-1', 'amount' => 'not-a-number'],
      ],
    ]);
    self::assertCount(1, $result);
    self::assertSame('moneybird:bank-1:m1', $result[0]['transaction_id']);
    self::assertSame('moneybird', $result[0]['provider']);
    self::assertSame('EUR', $result[0]['currency']);
    self::assertSame('', $result[0]['end_to_end_id']);
    self::assertSame('', $result[0]['counterparty_iban']);
    self::assertStringNotContainsString('secret', json_encode($result, JSON_THROW_ON_ERROR));
  }
}
