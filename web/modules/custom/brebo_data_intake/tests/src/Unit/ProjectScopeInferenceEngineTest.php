<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\brebo_data_intake\Service\ProjectScopeInferenceEngine;
use PHPUnit\Framework\TestCase;

final class ProjectScopeInferenceEngineTest extends TestCase {

  public function testCorrelatesReferenceQuantityAndDimensionsAcrossDocuments(): void {
    $engine = new ProjectScopeInferenceEngine();

    $scope = $engine->infer([
      [
        'filename' => 'kozijnenstaat.pdf',
        'status' => 'extracted',
        'confidence' => 0.91,
        'text' => "Kozijnstaat\nK01 1200 x 1500 mm\nK02 900 x 1200 mm",
      ],
      [
        'filename' => 'offerte.pdf',
        'status' => 'extracted',
        'confidence' => 0.88,
        'text' => "Offerte kunststof kozijnen\n12 st K01\n8 st K02",
      ],
    ]);

    self::assertSame('proposal', $scope['status']);
    self::assertTrue($scope['review_required']);
    self::assertCount(2, $scope['items']);
    self::assertSame('K1', $scope['items'][0]['reference']);
    self::assertSame(12, $scope['items'][0]['quantity']['value']);
    self::assertSame(1200, $scope['items'][0]['dimensions']['value']['width_mm']);
    self::assertSame(1500, $scope['items'][0]['dimensions']['value']['height_mm']);
  }

  public function testKeepsConflictingQuantitiesOpen(): void {
    $engine = new ProjectScopeInferenceEngine();

    $scope = $engine->infer([
      [
        'filename' => 'staat.pdf',
        'status' => 'extracted',
        'confidence' => 0.9,
        'text' => '10 st K03',
      ],
      [
        'filename' => 'offerte.pdf',
        'status' => 'extracted',
        'confidence' => 0.9,
        'text' => '12 st K03',
      ],
    ]);

    self::assertNull($scope['items'][0]['quantity']);
    self::assertSame('quantity', $scope['conflicts'][0]['field']);
    self::assertSame('K3', $scope['conflicts'][0]['reference']);
  }

  public function testUnreadableDocumentsRemainExplicit(): void {
    $engine = new ProjectScopeInferenceEngine();

    $scope = $engine->infer([
      [
        'filename' => 'IMG_1421.HEIC',
        'status' => 'provider_error',
        'confidence' => 0.0,
        'text' => '',
      ],
    ]);

    self::assertSame(0, $scope['extracted_document_count']);
    self::assertCount(1, $scope['unreadable_documents']);
    self::assertNotEmpty($scope['missing_information']);
  }

}
