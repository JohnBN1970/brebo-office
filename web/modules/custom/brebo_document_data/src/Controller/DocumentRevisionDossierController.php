<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Controller;

use Drupal\brebo_document_data\Service\DocumentRepository;
use Drupal\brebo_document_data\Service\DocumentRevisionSeries;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only dossier view for one context-scoped document revision series.
 */
final class DocumentRevisionDossierController extends ControllerBase {

  public function __construct(
    private readonly DocumentRevisionSeries $revisionSeries,
    private readonly DocumentRepository $documentRepository,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_document_data.revision_series'),
      $container->get('brebo_document_data.repository'),
    );
  }

  /**
   * Displays the current revision first and historic revisions collapsed below.
   */
  public function view(string $context_type, int $context_id, string $document_family): array {
    $family = trim(rawurldecode($document_family));
    $rows = $this->revisionSeries->revisions($context_type, $context_id, $family);
    if ($rows === []) {
      throw new NotFoundHttpException('Geen documentrevisies gevonden voor deze dossiercontext.');
    }

    $current = array_shift($rows);
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-document-revision-dossier']],
      'heading' => [
        '#markup' => '<h2>' . $this->t('Actueel document') . '</h2>',
      ],
      'current' => $this->revisionCard($current, TRUE),
    ];

    if ($rows !== []) {
      $build['history'] = [
        '#type' => 'details',
        '#title' => $this->t('Eerdere revisies (@count)', ['@count' => count($rows)]),
        '#open' => FALSE,
      ];
      foreach ($rows as $index => $row) {
        $build['history']['revision_' . $index] = $this->revisionCard($row, FALSE);
      }
    }

    $build['audit_note'] = [
      '#type' => 'item',
      '#markup' => '<small>' . $this->t('Volgorde is gebaseerd op de meest recente betrouwbare brondatum en -tijd. Oudere revisies blijven ongewijzigd bewaard.') . '</small>',
    ];

    return $build;
  }

  /** @param array<string, mixed> $row */
  private function revisionCard(array $row, bool $current): array {
    $documentId = (int) ($row['id'] ?? 0);
    $sources = $documentId > 0 ? $this->documentRepository->sourcesForDocument($documentId) : [];
    $source = $sources[0] ?? [];
    $timestamp = trim((string) ($source['source_timestamp'] ?? ''));
    $sourceSystem = trim((string) ($source['source_system'] ?? ''));
    $filename = trim((string) ($row['original_filename'] ?? $row['title'] ?? 'Document'));
    $revision = trim((string) ($row['revision_code'] ?? ''));

    $items = [
      $this->t('Bestand: @filename', ['@filename' => $filename]),
      $revision !== '' ? $this->t('Revisie: @revision', ['@revision' => $revision]) : $this->t('Revisiecode: niet opgegeven'),
      $timestamp !== '' ? $this->t('Brondatum/tijd: @timestamp', ['@timestamp' => $timestamp]) : $this->t('Brondatum/tijd: onbekend'),
      $sourceSystem !== '' ? $this->t('Herkomst: @source', ['@source' => $sourceSystem]) : $this->t('Herkomst: onbekend'),
      $this->t('Document-ID: @id', ['@id' => $documentId]),
    ];

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['brebo-document-revision', $current ? 'is-current' : 'is-historic'],
      ],
      'title' => [
        '#markup' => '<h3>' . ($current ? $this->t('Actueel') : $this->t('Historische revisie')) . '</h3>',
      ],
      'meta' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      'actions' => $documentId > 0 ? [
        '#type' => 'container',
        'detail' => [
          '#type' => 'link',
          '#title' => $this->t('Documentdetails'),
          '#url' => Url::fromRoute('brebo_document_data.document_detail', ['document_id' => $documentId]),
          '#suffix' => ' · ',
        ],
        'original' => [
          '#type' => 'link',
          '#title' => $this->t('Origineel openen'),
          '#url' => Url::fromRoute('brebo_document_data.document_open', ['document_id' => $documentId]),
          '#attributes' => ['target' => '_blank', 'rel' => 'noopener'],
        ],
      ] : [],
    ];
  }

}
