<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Guard for a future authenticated Office POST controller.
 * It validates the request, but does not move, store, or scan uploaded files.
 * Caller must separately enforce a building-scoped manage grant and AV scan.
 */
final class OfficeResidentAddressUploadGuard {
  public static function validate(
    array $server,
    array $upload,
    string $csrfProvided,
    string $csrfExpected,
    bool $canManageBuilding,
    int $buildingId
  ): array {
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST') throw new \DomainException('POST required');
    if (($server['HTTPS'] ?? '') === '' || ($server['HTTPS'] ?? '') === 'off') {
      throw new \DomainException('HTTPS required');
    }
    if ($buildingId < 1 || !$canManageBuilding) throw new \DomainException('Building manage permission required');
    if ($csrfProvided === '' || $csrfExpected === '' || !hash_equals($csrfExpected, $csrfProvided)) {
      throw new \DomainException('CSRF validation failed');
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new \InvalidArgumentException('Upload failed');
    $path = $upload['tmp_name'] ?? '';
    $filename = $upload['name'] ?? '';
    $size = $upload['size'] ?? null;
    if (!is_string($path) || !is_uploaded_file($path)) throw new \InvalidArgumentException('Untrusted upload path');
    if (!is_string($filename) || $filename === '' || strlen($filename) > 255
        || basename(str_replace('\\', '/', $filename)) !== $filename) {
      throw new \InvalidArgumentException('Invalid filename');
    }
    if (!is_int($size) || $size < 1 || $size > 10 * 1024 * 1024) {
      throw new \LengthException('Upload size outside allowed range');
    }
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'xlsx'], true)) {
      throw new \InvalidArgumentException('Unsupported upload type');
    }
    return ['path' => $path, 'filename' => $filename, 'size' => $size, 'building_id' => $buildingId];
  }
}
