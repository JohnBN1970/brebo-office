<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

require_once __DIR__ . '/OfficeResidentAddressFileImport.php';
require_once __DIR__ . '/OfficeResidentAddressReviewPresenter.php';

/**
 * Explicitly authorized import preview entrypoint for a future Office controller.
 * Does not accept raw HTTP requests, persist data or approve proposals.
 * Controller is responsible for session, CSRF, upload scanning and storage.
 */
final class OfficeResidentAddressReviewPage {
  public static function preview(
    string $trustedLocalPath,
    string $originalFilename,
    string $documentId,
    string $actorId,
    callable $canViewResidentData,
    int $maxRows = 1000
  ): string {
    if (trim($actorId) === '' || trim($documentId) === '') {
      throw new \InvalidArgumentException('Authenticated actor and document identity required');
    }
    if ($canViewResidentData($actorId, $documentId) !== true) {
      throw new \RuntimeException('Not authorized to review resident import');
    }
    $batch = OfficeResidentAddressFileImport::propose($trustedLocalPath, $originalFilename, $documentId, $maxRows);
    return OfficeResidentAddressReviewPresenter::render($batch, true);
  }
}
