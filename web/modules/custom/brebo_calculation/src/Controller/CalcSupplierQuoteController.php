<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\brebo_data_intake\Service\ManagedDocumentTextExtractionProvider;
use Drupal\brebo_calculation\Service\SupplierQuoteNormalizer;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Receives supplier quote evidence from the external Calc workbench. */
final class CalcSupplierQuoteController extends ControllerBase {

  private const MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/heic',
    'image/heif',
  ];

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly ManagedDocumentTextExtractionProvider $extractor,
    private readonly SupplierQuoteNormalizer $normalizer,
    private readonly CacheBackendInterface $cache,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('brebo_data_intake.managed_document_text_extraction_provider'),
      $container->get('brebo_calculation.supplier_quote_normalizer'),
      $container->get('cache.default'),
    );
  }

  public function upload(Request $request): JsonResponse {
    $bytes = (string) $request->getContent();
    $this->assertSignedRequest($request, $bytes);

    $mime = strtolower(trim((string) $request->headers->get('Content-Type', '')));
    if (!in_array($mime, self::MIME_TYPES, TRUE)) {
      throw new BadRequestHttpException('Dit bestandstype wordt nog niet ondersteund voor offerteherkenning.');
    }
    if ($bytes === '' || strlen($bytes) > 20 * 1024 * 1024) {
      throw new BadRequestHttpException('Offertebestand ontbreekt of is groter dan 20 MB.');
    }

    $calculationId = (int) $request->headers->get('X-BREBO-Calculation-Id', 0);
    $lineRef = trim((string) $request->headers->get('X-BREBO-Line-Ref', ''));
    if ($calculationId <= 0 || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $lineRef)) {
      throw new BadRequestHttpException('Calculatie- of regelreferentie ontbreekt.');
    }

    $filename = $this->safeFilename((string) $request->headers->get('X-BREBO-Filename', 'offerte'));
    $directory = 'private://brebo/calculation-price-sources/' . $calculationId . '/' . date('Y/m');
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $directory . '/' . time() . '-' . bin2hex(random_bytes(6)) . '-' . $filename;
    $uri = $this->fileSystem->saveData($bytes, $destination, FileExists::Rename);
    if (!$uri) {
      throw new BadRequestHttpException('Offerte kon niet in Office worden opgeslagen.');
    }

    $file = File::create([
      'uri' => $uri,
      'filename' => $filename,
      'status' => 1,
      'uid' => 0,
    ]);
    $file->save();

    $extraction = $this->extractor->extract($bytes, $mime, $filename);
    $proposal = $this->normalizer->normalize((string) ($extraction['text'] ?? ''), [
      'description' => trim((string) $request->headers->get('X-BREBO-Line-Description', '')),
      'quantity' => is_numeric($request->headers->get('X-BREBO-Line-Quantity')) ? (float) $request->headers->get('X-BREBO-Line-Quantity') : NULL,
      'unit' => trim((string) $request->headers->get('X-BREBO-Line-Unit', '')),
    ]);
    return new JsonResponse([
      'contract' => 'brebo-office-calc-quote-source-v1',
      'source' => [
        'file_id' => (int) $file->id(),
        'calculation_id' => $calculationId,
        'line_ref' => $lineRef,
        'filename' => $filename,
        'mime_type' => $mime,
      ],
      'extraction' => [
        'status' => (string) ($extraction['status'] ?? 'unknown'),
        'text' => (string) ($extraction['text'] ?? ''),
        'confidence' => (float) ($extraction['confidence'] ?? 0),
        'extractor' => (string) ($extraction['extractor'] ?? ''),
      ],
      'proposal' => $proposal,
    ], 201, ['Cache-Control' => 'no-store, private']);
  }

  public function preview(Request $request, int $calculation, int $file): Response {
    $this->assertSignedRequest($request, '');
    $entity = File::load($file);
    if (!$entity) {
      return new Response('Offertebron niet gevonden.', 404);
    }
    $uri = (string) $entity->getFileUri();
    $expectedPrefix = 'private://brebo/calculation-price-sources/' . $calculation . '/';
    if (!str_starts_with($uri, $expectedPrefix)) {
      throw new AccessDeniedHttpException('Offertebron hoort niet bij deze calculatie.');
    }
    $realPath = $this->fileSystem->realpath($uri);
    if (!$realPath || !is_file($realPath)) {
      return new Response('Offertebestand ontbreekt.', 404);
    }
    $mime = (string) ($entity->getMimeType() ?: 'application/octet-stream');
    $response = new Response((string) file_get_contents($realPath));
    $response->headers->set('Content-Type', $mime);
    $response->headers->set('Content-Disposition', 'inline; filename="' . addcslashes((string) $entity->getFilename(), '"\\') . '"');
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  private function assertSignedRequest(Request $request, string $body): void {
    $secret = trim((string) Settings::get('brebo_calc_shared_secret', getenv('BREBO_CALC_SHARED_SECRET') ?: ''));
    $timestamp = trim((string) $request->headers->get('X-BREBO-Timestamp', ''));
    $requestId = trim((string) $request->headers->get('X-BREBO-Request-Id', ''));
    $signature = trim((string) $request->headers->get('X-BREBO-Signature', ''));
    if ($secret === '' || !ctype_digit($timestamp) || !preg_match('/^[0-9a-fA-F-]{36}$/', $requestId) || !str_starts_with($signature, 'v1=')) {
      throw new AccessDeniedHttpException('Invalid Calc authentication.');
    }
    $now = time();
    if (abs($now - (int) $timestamp) > 300) {
      throw new AccessDeniedHttpException('Expired request.');
    }
    $replayKey = 'brebo_calc_quote_request:' . hash('sha256', $requestId);
    if ($this->cache->get($replayKey)) {
      throw new AccessDeniedHttpException('Replayed request.');
    }
    $canonical = $request->getMethod() . "\n" . $request->getRequestUri() . "\n" . hash('sha256', $body) . "\n" . $timestamp . "\n" . $requestId;
    $expected = 'v1=' . hash_hmac('sha256', $canonical, $secret);
    if (!hash_equals($expected, $signature)) {
      throw new AccessDeniedHttpException('Invalid signature.');
    }
    $this->cache->set($replayKey, TRUE, $now + 600);
  }

  private function safeFilename(string $filename): string {
    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename)) ?: 'offerte';
    return substr($safe, 0, 180);
  }

}
