<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit\Service;

use Drupal\brebo_office_core\Service\IntegrationApiRequestSigner;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\IntegrationApiRequestSigner
 * @group brebo_office_core
 */
final class IntegrationApiRequestSignerTest extends TestCase {

  /**
   * @covers ::sign
   */
  public function testSignatureMatchesWorkerContract(): void {
    $signer = new IntegrationApiRequestSigner();
    $headers = $signer->sign(
      'POST',
      '/v1/communications/analyze',
      '{"test_mode":true}',
      'test-shared-secret-with-sufficient-length',
      1786435200,
      '123e4567-e89b-42d3-a456-426614174000',
    );

    self::assertSame('1786435200', $headers['X-BREBO-Timestamp']);
    self::assertSame(
      '123e4567-e89b-42d3-a456-426614174000',
      $headers['X-BREBO-Request-Id'],
    );
    self::assertSame(
      'v1=85037f66c0052528a7e6289f5e1130ddb161db838f33bf2acdd193ab78c53da3',
      $headers['X-BREBO-Signature'],
    );
  }

  /**
   * @covers ::sign
   */
  public function testGeneratedRequestIdIsUuidV4AndUnique(): void {
    $signer = new IntegrationApiRequestSigner();
    $first = $signer->sign('GET', '/health/status', '', 'secret');
    $second = $signer->sign('GET', '/health/status', '', 'secret');

    self::assertMatchesRegularExpression(
      '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
      $first['X-BREBO-Request-Id'],
    );
    self::assertNotSame(
      $first['X-BREBO-Request-Id'],
      $second['X-BREBO-Request-Id'],
    );
    self::assertStringStartsWith('v1=', $first['X-BREBO-Signature']);
  }

}
