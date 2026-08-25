<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_finance\Unit;

use Drupal\brebo_finance\Service\MoneybirdSupplierClassifier;
use PHPUnit\Framework\TestCase;

final class MoneybirdSupplierClassifierTest extends TestCase {

  public function testClassifiesKnownReceiptAndBusinessSupplierConservatively(): void {
    $classifier = new MoneybirdSupplierClassifier();
    $result = $classifier->classify([
      [
        'contact_id' => '1',
        'supplier_name' => '. Turnhout',
        'supplier_contact' => ['company_name' => '. Turnhout'],
      ],
      [
        'contact_id' => '2',
        'supplier_name' => 'Action Kalmthout',
        'supplier_contact' => ['company_name' => 'Action Kalmthout'],
      ],
      [
        'contact_id' => '3',
        'supplier_name' => 'Bouwmaat Nederland B.V.',
        'supplier_contact' => [
          'company_name' => 'Bouwmaat Nederland B.V.',
          'chamber_of_commerce' => '12345678',
        ],
      ],
      [
        'contact_id' => '4',
        'supplier_name' => 'Onbekende betaling',
        'supplier_contact' => [],
      ],
    ]);

    self::assertSame(4, $result['unique_contacts']);
    self::assertSame(1, $result['supplier_count']);
    self::assertSame(2, $result['receipt_count']);
    self::assertSame(1, $result['review_count']);
    self::assertSame('Tankstation', $result['receipt'][0]['transaction_category']);
    self::assertSame('Winkel', $result['receipt'][1]['transaction_category']);
  }

  public function testRepeatedUnknownContactStaysReview(): void {
    $classifier = new MoneybirdSupplierClassifier();
    $result = $classifier->classify([
      ['contact_id' => '9', 'supplier_name' => 'Mystery merchant', 'supplier_contact' => []],
      ['contact_id' => '9', 'supplier_name' => 'Mystery merchant', 'supplier_contact' => []],
    ]);

    self::assertSame(1, $result['review_count']);
    self::assertSame('repeated_without_business_identity', $result['review'][0]['reason']);
  }

}
