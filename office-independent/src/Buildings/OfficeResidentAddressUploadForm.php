<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * HTML form fragment only. Rendering does not grant access or create a route.
 * The integrating controller must authorize both building management and
 * resident PII access, mint CSRF and source document identity, and scan files.
 */
final class OfficeResidentAddressUploadForm {
  public static function render(
    string $action,
    int $buildingId,
    string $csrfToken,
    string $documentId,
    bool $canManageBuilding,
    bool $canViewResidentData
  ): string {
    if (!$canManageBuilding || !$canViewResidentData) {
      throw new \DomainException('Resident upload permission required');
    }
    if ($buildingId < 1 || trim($csrfToken) === '' || trim($documentId) === '') {
      throw new \InvalidArgumentException('Missing upload form context');
    }
    if (!str_starts_with($action, '/') || str_starts_with($action, '//')
      || preg_match('/[\\x00-\\x1f\\x7f]/', $action)) {
      throw new \InvalidArgumentException('Unsafe upload action');
    }
    $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<form method="post" action="' . $e($action) . '" enctype="multipart/form-data">'
      . '<input type="hidden" name="building_id" value="' . $buildingId . '">'
      . '<input type="hidden" name="csrf_token" value="' . $e($csrfToken) . '">'
      . '<input type="hidden" name="document_id" value="' . $e($documentId) . '">'
      . '<label for="resident-address-file">Adressen en bewoners (CSV of XLSX)</label>'
      . '<input id="resident-address-file" name="resident_address_file" type="file" accept=".csv,.xlsx" required>'
      . '<p>Maximaal 10 MB. Alleen een controlevoorbeeld; gegevens worden niet automatisch opgeslagen.</p>'
      . '<button type="submit">Controleren voor import</button>'
      . '</form>';
  }
}
