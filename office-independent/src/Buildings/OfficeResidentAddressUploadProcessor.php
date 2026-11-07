<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

require_once __DIR__ . '/OfficeResidentAddressUploadGuard.php';
require_once __DIR__ . '/OfficeResidentAddressReviewPage.php';

/**
 * Preview-only upload processor. The caller supplies independently verified
 * session, CSRF token, building manage grant, document identity, and scanner.
 * No persistence or approval occurs here.
 */
final class OfficeResidentAddressUploadProcessor {
  public static function preview(
    array $server,
    array $upload,
    string $csrfProvided,
    string $csrfExpected,
    int $buildingId,
    bool $canManageBuilding,
    string $actorId,
    string $documentId,
    callable $scanUploadedFile,
    callable $canViewResidentData
  ): string {
    $validated = OfficeResidentAddressUploadGuard::validate(
      $server, $upload, $csrfProvided, $csrfExpected, $canManageBuilding, $buildingId
    );
    if (trim($actorId) === '' || trim($documentId) === '') {
      throw new \DomainException('Authenticated actor and document identity required');
    }
    // Scanner must return exactly true. Unavailable/failed scanner fails closed.
    if ($scanUploadedFile($validated['path'], $validated['filename']) !== true) {
      throw new \RuntimeException('Upload scanner did not approve file');
    }
    // Building management is not resident-data permission. Require a separate
    // grant scoped to this actor, building and document, failing closed.
    if ($canViewResidentData($actorId, $buildingId, $documentId) !== true) {
      throw new \DomainException('Resident data access denied');
    }
    // The source is the verified PHP upload tmp path; do not move into a web root.
    return OfficeResidentAddressReviewPage::preview(
      $validated['path'], $validated['filename'], $documentId, $actorId,
      static fn(string $checkedActor, string $checkedDocument): bool =>
        $checkedActor === $actorId && $checkedDocument === $documentId
        && $canViewResidentData($checkedActor, $buildingId, $checkedDocument) === true
    );
  }
}
