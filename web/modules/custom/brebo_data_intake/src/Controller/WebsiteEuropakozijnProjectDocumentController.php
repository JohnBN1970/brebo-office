<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Controller;

use Drupal\brebo_data_intake\Service\DocumentTextExtractionProviderRegistry;
use Drupal\brebo_data_intake\Service\SourceNeutralIntakeManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\file\FileRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

/** Receives project documents from the public Europakozijn website. */
final class WebsiteEuropakozijnProjectDocumentController extends ControllerBase {

  private const int MAX_UPLOAD_BYTES = 104857600;
  private const int MAX_ZIP_ENTRIES = 50;
  private const int MAX_ZIP_ENTRY_BYTES = 26214400;
  private const int MAX_ZIP_TOTAL_BYTES = 104857600;

  /** @var array<string,string> */
  private const array MIME_BY_EXTENSION = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'heic' => 'image/heic',
    'heif' => 'image/heif',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip' => 'application/zip',
  ];

  public function __construct(
    private readonly SourceNeutralIntakeManager $intakeManager,
    private readonly DocumentTextExtractionProviderRegistry $providerRegistry,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_data_intake.source_neutral_intake_manager'),
      $container->get('brebo_data_intake.document_text_extraction_provider_registry'),
      $container->get('file.repository'),
      $container->get('file_system'),
    );
  }

  public function upload(Request $request): JsonResponse {
    $requestId = trim((string) $request->headers->get('X-BREBO-Request-Id', ''));
    $contentSha = strtolower(trim((string) $request->headers->get('X-BREBO-Content-SHA256', '')));
    $timestamp = trim((string) $request->headers->get('X-BREBO-Timestamp', ''));
    $signatureHeader = trim((string) $request->headers->get('X-BREBO-Signature', ''));

    $uploaded = $request->files->get('document');
    if (!$uploaded instanceof UploadedFile || !$uploaded->isValid()) {
      return $this->error(400, 'missing_or_invalid_document');
    }
    if (!preg_match('/^[0-9a-f-]{36}$/i', $requestId)) {
      return $this->error(422, 'invalid_request_id');
    }
    if ($uploaded->getSize() <= 0 || $uploaded->getSize() > self::MAX_UPLOAD_BYTES) {
      return $this->error(413, 'document_size_not_allowed');
    }

    $originalName = $this->safeFilename($uploaded->getClientOriginalName());
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!isset(self::MIME_BY_EXTENSION[$extension])) {
      return $this->error(415, 'document_type_not_allowed');
    }

    $bytes = file_get_contents($uploaded->getPathname());
    if (!is_string($bytes) || $bytes === '') {
      return $this->error(400, 'document_unreadable');
    }
    $actualSha = hash('sha256', $bytes);
    if (!hash_equals($actualSha, $contentSha)) {
      return $this->error(422, 'content_hash_mismatch');
    }
    if (!$this->authenticated($request->getPathInfo(), $requestId, $timestamp, $contentSha, $signatureHeader)) {
      return $this->error(401, 'invalid_signature');
    }

    $metadataRaw = trim((string) $request->request->get('metadata', ''));
    $metadata = [];
    if ($metadataRaw !== '') {
      try {
        $decoded = json_decode($metadataRaw, TRUE, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
          return $this->error(422, 'invalid_metadata');
        }
        $metadata = $decoded;
      }
      catch (\JsonException) {
        return $this->error(422, 'invalid_metadata');
      }
    }

    $directory = 'private://brebo-intake/website-europakozijn/' . $requestId;
    if (!$this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    )) {
      return $this->error(500, 'private_storage_unavailable');
    }

    $file = $this->fileRepository->writeData(
      $bytes,
      $directory . '/' . $originalName,
      FileExists::Rename,
    );
    $file->setPermanent();
    $file->save();

    $analysis = $extension === 'zip'
      ? $this->analyzeZip($uploaded->getPathname())
      : [$this->analyzeDocument($bytes, self::MIME_BY_EXTENSION[$extension], $originalName)];

    $intake = $this->intakeManager->intake([
      'source' => 'website',
      'source_record_id' => $requestId,
      'classification' => 'website_project_request',
      'confidence' => 1.0,
      'canonical' => [],
      'payload' => [
        'request_id' => $requestId,
        'filename' => $file->getFilename(),
        'file_id' => (int) $file->id(),
        'uri' => $file->getFileUri(),
        'mime_type' => self::MIME_BY_EXTENSION[$extension],
        'size' => (int) $file->getSize(),
        'content_sha256' => $actualSha,
        'metadata' => $metadata,
        'documents' => $analysis,
      ],
      'attachments' => [[
        'file_id' => (int) $file->id(),
        'filename' => $file->getFilename(),
        'uri' => $file->getFileUri(),
        'mime_type' => self::MIME_BY_EXTENSION[$extension],
        'size' => (int) $file->getSize(),
        'content_sha256' => $actualSha,
      ]],
      'received_at' => time(),
      'actor_uid' => 0,
    ]);

    $publicDocuments = array_map(static function (array $document): array {
      unset($document['text']);
      return $document;
    }, $analysis);

    return $this->response([
      'request_id' => $requestId,
      'intake' => $intake,
      'recognition' => [
        'documents' => $publicDocuments,
        'document_count' => count($publicDocuments),
        'extracted_count' => count(array_filter($publicDocuments, static fn(array $document): bool => ($document['status'] ?? '') === 'extracted')),
      ],
    ], 202);
  }

  /** @return array<string,mixed> */
  private function analyzeDocument(string $bytes, string $mimeType, string $filename): array {
    $provider = $this->providerRegistry->providerFor($mimeType);
    if ($provider === NULL) {
      return [
        'filename' => $filename,
        'mime_type' => $mimeType,
        'status' => 'extraction_provider_unavailable',
        'confidence' => 0.0,
        'text' => '',
        'excerpt' => '',
      ];
    }

    $result = $provider->extract($bytes, $mimeType, $filename);
    $text = trim((string) ($result['text'] ?? ''));
    return [
      'filename' => $filename,
      'mime_type' => $mimeType,
      'status' => (string) ($result['status'] ?? 'unknown'),
      'confidence' => isset($result['confidence']) ? (float) $result['confidence'] : 0.0,
      'extractor' => (string) ($result['extractor'] ?? ''),
      'text' => mb_substr($text, 0, 20000),
      'excerpt' => mb_substr($text, 0, 1200),
    ];
  }

  /** @return list<array<string,mixed>> */
  private function analyzeZip(string $path): array {
    if (!class_exists(ZipArchive::class)) {
      return [[
        'filename' => basename($path),
        'mime_type' => 'application/zip',
        'status' => 'zip_runtime_unavailable',
        'confidence' => 0.0,
        'text' => '',
        'excerpt' => '',
      ]];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== TRUE) {
      return [[
        'filename' => basename($path),
        'mime_type' => 'application/zip',
        'status' => 'zip_open_failed',
        'confidence' => 0.0,
        'text' => '',
        'excerpt' => '',
      ]];
    }

    $documents = [];
    $totalBytes = 0;
    $limit = min($zip->numFiles, self::MAX_ZIP_ENTRIES);
    for ($index = 0; $index < $limit; $index++) {
      $stat = $zip->statIndex($index);
      if (!is_array($stat)) {
        continue;
      }
      $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
      if ($name === '' || str_ends_with($name, '/') || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
        continue;
      }
      $entrySize = (int) ($stat['size'] ?? 0);
      $totalBytes += max(0, $entrySize);
      if ($entrySize <= 0 || $entrySize > self::MAX_ZIP_ENTRY_BYTES || $totalBytes > self::MAX_ZIP_TOTAL_BYTES) {
        $documents[] = [
          'filename' => basename($name),
          'mime_type' => 'application/octet-stream',
          'status' => 'zip_entry_size_not_allowed',
          'confidence' => 0.0,
          'text' => '',
          'excerpt' => '',
        ];
        continue;
      }
      $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if ($extension === 'zip' || !isset(self::MIME_BY_EXTENSION[$extension])) {
        continue;
      }
      $entry = $zip->getFromIndex($index);
      if (!is_string($entry) || $entry === '') {
        continue;
      }
      $documents[] = $this->analyzeDocument($entry, self::MIME_BY_EXTENSION[$extension], basename($name));
    }
    $zip->close();

    if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
      $documents[] = [
        'filename' => '__package__',
        'mime_type' => 'application/zip',
        'status' => 'zip_entry_limit_reached',
        'confidence' => 0.0,
        'text' => '',
        'excerpt' => '',
      ];
    }

    return $documents;
  }

  private function authenticated(string $path, string $requestId, string $timestamp, string $contentSha, string $signatureHeader): bool {
    $secret = trim((string) Settings::get('brebo_shared_secret', getenv('BREBO_SHARED_SECRET') ?: ''));
    if ($secret === '' || !preg_match('/^[0-9]+$/', $timestamp) || abs(time() - (int) $timestamp) > 300) {
      return FALSE;
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $contentSha) || !preg_match('/^v1=([a-f0-9]{64})$/', $signatureHeader, $match)) {
      return FALSE;
    }
    $canonical = implode("\n", ['POST', $path, $contentSha, $timestamp, $requestId]);
    return hash_equals(hash_hmac('sha256', $canonical, $secret), $match[1]);
  }

  private function safeFilename(string $filename): string {
    $filename = basename(str_replace('\\', '/', trim($filename)));
    $filename = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $filename) ?: 'document';
    return mb_substr($filename, 0, 180);
  }

  /** @param array<string,mixed> $payload */
  private function response(array $payload, int $status): JsonResponse {
    $response = new JsonResponse(['status' => 'ok'] + $payload, $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  private function error(int $status, string $code): JsonResponse {
    $response = new JsonResponse(['status' => 'error', 'error' => ['code' => $code]], $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
