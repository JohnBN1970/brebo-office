<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\TenderLeadScorer;
use PHPUnit\Framework\TestCase;

final class TenderLeadScorerTest extends TestCase {

  public function testStrongBreboTenderScoresHighly(): void {
    $result = (new TenderLeadScorer())->score([
      'title' => 'NEN 2767 conditiemeting en MJOP vastgoedonderhoud',
      'description' => 'Planmatig bouwkundig onderhoud en verduurzaming voor een woningcorporatie.',
      'deadline' => date('Y-m-d', strtotime('+21 days')),
    ]);
    self::assertGreaterThanOrEqual(70, $result['score']);
    self::assertContains('nen 2767', $result['reasons']);
  }

  public function testUnrelatedTenderScoresLow(): void {
    $result = (new TenderLeadScorer())->score([
      'title' => 'Levering kantoorartikelen',
      'description' => 'Pennen en papier.',
    ]);
    self::assertLessThan(35, $result['score']);
  }

}
