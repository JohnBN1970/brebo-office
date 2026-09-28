<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\brebo_data_intake\Service\ManagedDocumentTextExtractionProvider;
use Drupal\brebo_calculation\Service\SupplierQuoteNormalizer;
use Drupal\brebo_calculation\Service\CalcIntegrationRequestAuthenticator;
use Drupal\brebo_calculation\Service\SupplierQuotePdfTextExtractor;
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
    private readonly SupplierQuotePdfTextExtractor $pdfTextExtractor,
    private readonly SupplierQuoteNormalizer $normalizer,
    private readonly CalcIntegrationRequestAuthenticator $authenticator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('brebo_data_intake.managed_document_text_extraction_provider'),
      $container->get('brebo_calculation.supplier_quote_pdf_text_extractor'),
      $container->get('brebo_calculation.supplier_quote_normalizer'),
      $container->get('brebo_calculation.calc_request_authenticator'),
    );
  }

  public function upload(Request $request): JsonResponse {
    try {
      $bytes = (string) $request->getContent();
      $this->authenticator->assertSigned($request, $bytes);
    }
    catch (AccessDeniedHttpException $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      return $this->stageError('authentication', $e);
    }

    try {
      $mime = strtolower(trim((string) $request->headers->get('Content-Type', '')));
    }
    catch (\Throwable $e) {
      return $this->stageError('request_mime', $e);
    }
    if (!in_array($mime, self::MIME_TYPES, TRUE)) {
      throw new BadRequestHttpException('Dit bestandstype wordt nog niet ondersteund voor offerteherkenning.');
    }
    if ($bytes === '' || strlen($bytes) > 20 * 1024 * 1024) {
      throw new BadRequestHttpException('Offertebestand ontbreekt of is groter dan 20 MB.');
    }

    try {
      $calculationId = (int) $request->headers->get('X-BREBO-Calculation-Id', '0');
      $lineRef = trim((string) $request->headers->get('X-BREBO-Line-Ref', ''));
    }
    catch (\Throwable $e) {
      return $this->stageError('request_context', $e);
    }
    if ($calculationId <= 0 || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $lineRef)) {
      throw new BadRequestHttpException('Calculatie- of regelreferentie ontbreekt.');
    }

    try {
      $filename = $this->safeFilename((string) $request->headers->get('X-BREBO-Filename', 'offerte'));
      $directory = 'private://brebo/calculation-price-sources/' . $calculationId . '/' . date('Y/m');
    }
    catch (\Throwable $e) {
      return $this->stageError('request_setup', $e);
    }
    try {
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \RuntimeException('Private offertemap kon niet worden voorbereid.');
      }
      $destination = $directory . '/' . time() . '-' . bin2hex(random_bytes(6)) . '-' . $filename;
      $uri = $this->fileSystem->saveData($bytes, $destination, FileExists::Rename);
      if (!$uri) {
        throw new \RuntimeException('Offerte kon niet in Office worden opgeslagen.');
      }
    }
    catch (\Throwable $e) {
      return $this->stageError('storage', $e);
    }

    try {
      $file = File::create([
        'uri' => $uri,
        'filename' => $filename,
        'status' => 1,
        'uid' => 0,
      ]);
      $file->save();
    }
    catch (\Throwable $e) {
      return $this->stageError('file_entity', $e);
    }

    try {
      $extraction = [];
      if ($mime === 'application/pdf') {
        $realPath = $this->fileSystem->realpath($uri);
        if (is_string($realPath) && $realPath !== '') {
          $local = $this->pdfTextExtractor->extract($realPath);
          if (($local['status'] ?? '') === 'extracted' && trim((string) ($local['text'] ?? '')) !== '') {
            $extraction = $local;
          }
        }
      }
      if ($extraction === []) {
        $extraction = $this->extractor->extract($bytes, $mime, $filename);
      }
    }
    catch (\Throwable $e) {
      return $this->stageError('extraction', $e);
    }

    try {
      $proposal = $this->normalizer->normalize((string) ($extraction['text'] ?? ''), [
      'description' => trim((string) $request->headers->get('X-BREBO-Line-Description', '')),
      'quantity' => is_numeric($request->headers->get('X-BREBO-Line-Quantity')) ? (float) $request->headers->get('X-BREBO-Line-Quantity') : NULL,
        'unit' => trim((string) $request->headers->get('X-BREBO-Line-Unit', '')),
      ]);
    }
    catch (\Throwable $e) {
      return $this->stageError('normalization', $e);
    }

    return new JsonResponse([
      'contract' => 'brebo-calculation-supplier-quote-v2',
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
    $this->authenticator->assertSigned($request, '');
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

  private function stageError(string $stage, \Throwable $error): JsonResponse {
    $this->getLogger('brebo_calculation')->error('Calc supplier quote failed at @stage: @message', [
      '@stage' => $stage,
      '@message' => $error->getMessage(),
    ]);
    return new JsonResponse([
      'error' => 'supplier_quote_processing_failed',
      'stage' => $stage,
      'message' => $error->getMessage(),
    ], 500, ['Cache-Control' => 'no-store, private']);
  }

  private function safeFilename(string $filename): string {
    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename)) ?: 'offerte';
    return substr($safe, 0, 180);
  }

}
