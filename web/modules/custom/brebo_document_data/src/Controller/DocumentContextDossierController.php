<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Controller;

use Drupal\brebo_document_data\Service\DocumentRevisionSeries;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only dossier index for all document families in one business context.
 */
class DocumentContextDossierController extends ControllerBase {

  private const CONTEXT_TYPES = ['project', 'building', 'organization', 'contact', 'brebo'];

  public function __construct(
    private readonly Connection $database,
    private readonly DocumentRevisionSeries $revisionSeries,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_document_data.revision_series'),
    );
  }

  public function view(string $context_type, int $context_id): array {
    $contextType = strtolower(trim($context_type));
    if (!in_array($contextType, self::CONTEXT_TYPES, TRUE) || ($contextType !== 'brebo' && $context_id <= 0)) {
      throw new NotFoundHttpException();
    }

    $query = $this->database->select('brebo_document_context', 'c');
    $query->innerJoin('brebo_document', 'd', 'd.id = c.document_id');
    $query->addField('d', 'document_family');
    $query->condition('c.context_type', $contextType);
    $query->condition('c.context_id', $context_id);
    $query->condition('d.lifecycle_status', 'deleted', '<>');
    $query->isNotNull('d.document_family');
    $query->condition('d.document_family', '', '<>');
    $query->distinct();
    $query->orderBy('d.document_family');
    $families = array_values(array_filter(array_map('strval', $query->execute()->fetchCol() ?: [])));

    $rows = [];
    foreach ($families as $family) {
      $current = $this->revisionSeries->current($contextType, $context_id, $family);
      if ($current === NULL) {
        continue;
      }
      $timestamp = isset($current['authoritative_source_timestamp']) ? (int) $current['authoritative_source_timestamp'] : 0;
      $documentId = (int) ($current['id'] ?? 0);
      $documentTitle = (string) ($current['original_filename'] ?? $current['title'] ?? '');
      $documentCell = $documentTitle;
      if ($documentId > 0 && $documentTitle !== '') {
        $documentCell = [
          'data' => [
            '#type' => 'link',
            '#title' => $documentTitle,
            '#url' => Url::fromRoute('brebo_document_data.document_open', [
              'document_id' => $documentId,
            ]),
            '#attributes' => ['target' => '_blank', 'rel' => 'noopener'],
          ],
        ];
      }

      $rows[] = [
        'family' => [
          'data' => [
            '#type' => 'link',
            '#title' => $family,
            '#url' => Url::fromRoute('brebo_document_data.revision_dossier', [
              'context_type' => $contextType,
              'context_id' => $context_id,
              'document_family' => $family,
            ]),
          ],
        ],
        'revision' => (string) ($current['revision_code'] ?? ''),
        'document' => $documentCell,
        'source_time' => $timestamp > 0 ? date('d-m-Y H:i:s', $timestamp) : 'Geen betrouwbare brondatum',
        'document_id' => $documentId,
      ];
    }

    return [
      'intro' => [
        '#markup' => '<p>Documenten zijn gegroepeerd per documentfamilie. De getoonde rij is steeds de actuele revisie op basis van de leidende brondatum en -tijd. Open een familie voor de volledige historie.</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Documentfamilie', 'Revisie', 'Actueel document', 'Brondatum en -tijd', 'Document-ID'],
        '#rows' => $rows,
        '#empty' => 'Nog geen geclassificeerde documentfamilies voor deze context.',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
