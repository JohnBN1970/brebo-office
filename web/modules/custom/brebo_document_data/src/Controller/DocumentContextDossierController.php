<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Controller;

use Drupal\brebo_document_data\Service\DocumentRevisionSeries;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Read-only dossier index for all document families in one business context. */
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
    $cards = [];
    foreach ($families as $family) {
      $current = $this->revisionSeries->current($contextType, $context_id, $family);
      if ($current === NULL) {
        continue;
      }
      $timestamp = isset($current['authoritative_source_timestamp']) ? (int) $current['authoritative_source_timestamp'] : 0;
      $documentId = (int) ($current['id'] ?? 0);
      $documentTitle = (string) ($current['original_filename'] ?? $current['title'] ?? '');
      $revision = (string) ($current['revision_code'] ?? '');
      $sourceTime = $timestamp > 0 ? date('d-m-Y H:i:s', $timestamp) : 'Geen betrouwbare brondatum';
      $detailUrl = $documentId > 0 ? Url::fromRoute('brebo_document_data.document_detail', ['document_id' => $documentId])->toString() : '';
      $openUrl = $documentId > 0 ? Url::fromRoute('brebo_document_data.document_open', ['document_id' => $documentId])->toString() : '';
      $revisionUrl = Url::fromRoute('brebo_document_data.revision_dossier', [
        'context_type' => $contextType,
        'context_id' => $context_id,
        'document_family' => $family,
      ]);

      $documentCell = $documentTitle;
      if ($documentId > 0 && $documentTitle !== '') {
        $documentCell = ['data' => [
          '#type' => 'html_tag', '#tag' => 'button', '#value' => $documentTitle,
          '#attributes' => [
            'type' => 'button', 'class' => ['brebo-document-browser__document-link'],
            'data-document-preview' => '1', 'data-preview-url' => $openUrl,
            'data-detail-url' => $detailUrl, 'data-preview-title' => $documentTitle,
          ],
        ]];
      }

      $rows[] = [
        'family' => ['data' => ['#type' => 'link', '#title' => $family, '#url' => $revisionUrl]],
        'revision' => $revision,
        'document' => $documentCell,
        'original' => $documentId > 0 ? ['data' => [
          '#type' => 'link', '#title' => 'Origineel',
          '#url' => Url::fromRoute('brebo_document_data.document_open', ['document_id' => $documentId]),
          '#attributes' => ['target' => '_blank', 'rel' => 'noopener'],
        ]] : '',
        'source_time' => $sourceTime,
        'document_id' => $documentId,
      ];

      if ($documentId > 0) {
        $extension = strtolower((string) pathinfo($documentTitle, PATHINFO_EXTENSION));
        $cards[] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['brebo-document-card'], 'tabindex' => '0', 'role' => 'button',
            'data-document-preview' => '1', 'data-preview-url' => $openUrl,
            'data-detail-url' => $detailUrl, 'data-preview-title' => $documentTitle,
            'aria-label' => $this->t('Voorbeeld openen van @document', ['@document' => $documentTitle]),
          ],
          'preview' => ['#markup' => '<div class="brebo-document-card__preview" aria-hidden="true">' . htmlspecialchars($extension !== '' ? $extension : 'DOC', ENT_QUOTES, 'UTF-8') . '</div>'],
          'body' => [
            '#type' => 'container', '#attributes' => ['class' => ['brebo-document-card__body']],
            'title' => ['#markup' => '<div class="brebo-document-card__title">' . htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') . '</div>'],
            'meta' => ['#markup' => '<div class="brebo-document-card__meta"><span>' . htmlspecialchars($family, ENT_QUOTES, 'UTF-8') . '</span><span>Revisie ' . htmlspecialchars($revision !== '' ? $revision : '—', ENT_QUOTES, 'UTF-8') . '</span><span>' . htmlspecialchars($sourceTime, ENT_QUOTES, 'UTF-8') . '</span><span>Document-ID ' . $documentId . '</span></div>'],
            'actions' => [
              '#type' => 'container', '#attributes' => ['class' => ['brebo-document-card__actions']],
              'history' => ['#type' => 'link', '#title' => $this->t('Revisies'), '#url' => $revisionUrl],
              'open' => ['#type' => 'link', '#title' => $this->t('Origineel'), '#url' => Url::fromRoute('brebo_document_data.document_open', ['document_id' => $documentId]), '#attributes' => ['target' => '_blank', 'rel' => 'noopener']],
            ],
          ],
        ];
      }
    }

    $viewButtons = [];
    foreach (['list' => 'Lijst', 'details' => 'Details', 'tiles' => 'Tegels', 'large-tiles' => 'Grote tegels'] as $view => $label) {
      $viewButtons[$view] = [
        '#type' => 'html_tag', '#tag' => 'button', '#value' => $this->t($label),
        '#attributes' => ['type' => 'button', 'class' => ['brebo-document-browser__view'], 'data-document-view' => $view, 'aria-pressed' => $view === 'list' ? 'true' : 'false'],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-document-browser'], 'data-view' => 'list', 'data-preference-key' => $contextType . ':' . $context_id],
      '#attached' => ['library' => ['brebo_document_data/document-browser']],
      'toolbar' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-document-browser__toolbar']],
        'intro' => ['#markup' => '<div>Actuele documenten per documentfamilie. Klik op een document voor een direct voorbeeld.</div>'],
        'views' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-document-browser__views'], 'aria-label' => $this->t('Documentweergave')]] + $viewButtons,
      ],
      'table' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-document-browser__table']],
        'content' => [
          '#type' => 'table',
          '#header' => ['Documentfamilie', 'Revisie', 'Actueel document', 'Bestand', 'Brondatum en -tijd', 'Document-ID'],
          '#rows' => $rows,
          '#empty' => 'Nog geen geclassificeerde documentfamilies voor deze context.',
        ],
      ],
      'items' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-document-browser__items']]] + $cards,
      'preview' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-document-preview'], 'aria-hidden' => 'true'],
        'backdrop' => ['#markup' => '<button class="brebo-document-preview__backdrop" type="button" data-preview-close aria-label="Voorbeeld sluiten"></button>'],
        'viewer' => ['#markup' => '<div class="brebo-document-preview__viewer"><iframe src="about:blank" title="Documentvoorbeeld"></iframe></div>'],
        'panel' => ['#markup' => '<aside class="brebo-document-preview__panel"><button type="button" class="brebo-document-preview__close" data-preview-close aria-label="Sluiten">×</button><h2 data-preview-title>Document</h2><p>Dit is het geregistreerde BREBO-origineel. De bestaande opslag- en integriteitscontrole blijft leidend.</p><div class="brebo-document-preview__actions"><a href="#" data-preview-original target="_blank" rel="noopener">Origineel openen</a><a href="#" data-preview-detail>Documentdetails</a></div></aside>'],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
