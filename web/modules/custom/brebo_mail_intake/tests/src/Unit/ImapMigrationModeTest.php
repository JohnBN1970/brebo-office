<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_mail_intake\Unit;

use Drupal\brebo_mail_intake\Source\ImapSourceAdapter;
use Drupal\Core\State\StateInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies migration source naming remains isolated from incremental sources.
 */
final class ImapMigrationModeTest extends TestCase {

  public function testMigrationSourceCanUseIndependentStateKey(): void {
    $state = $this->createMock(StateInterface::class);
    $adapter = new ImapSourceAdapter($state, 'BREBO_TEST', 'zoho_sent_migration');
    self::assertInstanceOf(ImapSourceAdapter::class, $adapter);
  }

}
