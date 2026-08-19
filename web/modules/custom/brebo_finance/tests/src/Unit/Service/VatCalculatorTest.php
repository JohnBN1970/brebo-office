<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_finance\Unit\Service;

use Drupal\brebo_finance\Service\VatCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_finance\Service\VatCalculator
 * @group brebo_finance
 */
final class VatCalculatorTest extends TestCase {

  /**
   * @covers ::calculate
   */
  public function testCalculatesTwentyOnePercentVat(): void {
    $result = (new VatCalculator())->calculate('100.00', '21');

    self::assertSame('100.0000', $result->amountExVat);
    self::assertSame('21.0000', $result->vatAmount);
    self::assertSame('121.0000', $result->amountIncVat);
    self::assertSame('21.0000', $result->deductibleVatAmount);
  }

  /**
   * @covers ::calculate
   */
  public function testReverseChargeHasNoInvoiceVat(): void {
    $result = (new VatCalculator())->calculate('250.00', '21', TRUE);

    self::assertTrue($result->reverseCharge);
    self::assertSame('0.0000', $result->vatAmount);
    self::assertSame('250.0000', $result->amountIncVat);
  }

  /**
   * @covers ::calculate
   */
  public function testSeparatesNonDeductibleVat(): void {
    $result = (new VatCalculator())->calculate('100.00', '21', FALSE, '25');

    self::assertSame('15.7500', $result->deductibleVatAmount);
    self::assertSame('5.2500', $result->nonDeductibleVatAmount);
  }

  /**
   * @covers ::calculate
   */
  public function testRejectsUnsupportedRate(): void {
    $this->expectException(InvalidArgumentException::class);
    (new VatCalculator())->calculate('100.00', '19');
  }

}
