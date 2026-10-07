<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Infrastructure;

use Drupal\brebo_data_intake\Contract\DocumentExtractionRuntimeConfigInterface;
use Drupal\Core\Site\Settings;

final class DrupalDocumentExtractionRuntimeConfig implements DocumentExtractionRuntimeConfigInterface {

  public function endpoint(): string {
    $setting = trim((string) Settings::get('brebo_document_extraction_endpoint', ''));
    return $setting !== '' ? $setting : trim((string) (getenv('BREBO_DOCUMENT_EXTRACTION_ENDPOINT') ?: ''));
  }

  public function token(): string {
    $setting = trim((string) Settings::get('brebo_document_extraction_token', ''));
    return $setting !== '' ? $setting : trim((string) (getenv('DOCUMENT_EXTRACTION_TOKEN') ?: ''));
  }

}
