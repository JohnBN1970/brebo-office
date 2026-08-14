<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Controller;

use Drupal\brebo_document_data\Service\DocumentRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Read-only canonical document page with provenance and object relations. */
final class DocumentDetailController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly DocumentRepository $documents,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_document_data.repository'),
      $container->get('entity_type.manager'),
    );
  }

  public function view(int $document_id): array {
    $document = $this->database->select('brebo_document', 'd')
      ->fields('d')
      ->condition('id', $document_id)
      ->condition('lifecycle_status', 'deleted', '<>')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$document) {
      throw new NotFoundHttpException();
    }

    $communicationRows = [];
    foreach ($this->documents->communicationsForDocument($document_id) as $relation) {
      $communicationId = (int) ($relation['communication_nid'] ?? 0);
      $title = trim((string) ($relation['communication_title'] ?? 'Communicatie ' . $communicationId));
      $communicationRows[] = [
        'role' => $this->roleLabel((string) ($relation['relation_role'] ?? '')),
        'communication' => $communicationId > 0 ? [
          'data' => [
            '#type' => 'link',
            '#title' => $title !== '' ? $title : 'Communicatie ' . $communicationId,
            '#url' => Url::fromRoute('entity.node.canonical', ['node' => $communicationId]),
          ],
        ] : '',
        'date' => !empty($relation['created']) ? date('d-m-Y H:i:s', (int) $relation['created']) : '',
      ];
    }

    $contextRows = [];
    foreach ($this->documents->contextsForDocument($document_id) as $context) {
      $type = (string) ($context['context_type'] ?? '');
      $id = (int) ($context['context_id'] ?? 0);
      $label = $type === 'brebo' ? 'BREBO' : ucfirst($type) . ' ' . $id;
      $node = $id > 0 ? $this->entityTypeManager->getStorage('node')->load($id) : NULL;
      if ($node) {
        $label = (string) $node->label();
      }
      $contextRows[] = [
        'type' => ucfirst($type),
        'object' => $node ? [
          'data' => [
            '#type' => 'link',
            '#title' => $label,
            '#url' => Url::fromRoute('entity.node.canonical', ['node' => $id]),
          ],
        ] : $label,
        'role' => (string) ($context['relation_role'] ?? ''),
        'status' => (string) ($context['review_status'] ?? ''),
      ];
    }

    $sourceRows = [];
    foreach ($this->documents->sourcesForDocument($document_id) as $source) {
      $sourceRows[] = [
        (string) ($source['source_system'] ?? ''),
        (string) ($source['source_actor'] ?? ''),
        (string) ($source['source_timestamp'] ?? ''),
        (string) ($source['original_filename'] ?? ''),
        (string) ($source['review_status'] ?? ''),
      ];
    }

    $family = trim((string) ($document['document_family'] ?? ''));
    $revision = trim((string) ($document['revision_code'] ?? ''));

    return [
      'heading' => ['#markup' => '<h2>' . htmlspecialchars((string) $document['title'], ENT_QUOTES, 'UTF-8') . '</h2>'],
      'meta' => [
        '#theme' => 'item_list',
        '#items' => [
          'Document-ID: ' . $document_id,
          'Documentfamilie: ' . ($family !== '' ? $family : 'Nog niet vastgesteld'),
          'Revisie: ' . ($revision !== '' ? $revision : 'Nog niet vastgesteld'),
          'SHA-256: ' . (string) $document['sha256'],
        ],
      ],
      'open' => [
        '#type' => 'link',
        '#title' => $this->t('Origineel openen'),
        '#url' => Url::fromRoute('brebo_document_data.document_open', ['document_id' => $document_id]),
        '#attributes' => ['class' => ['button'], 'target' => '_blank', 'rel' => 'noopener'],
      ],
      'communications_heading' => ['#markup' => '<h3>Communicatie</h3>'],
      'communications' => [
        '#type' => 'table',
        '#header' => ['Relatie', 'Communicatie', 'Vastgelegd'],
        '#rows' => $communicationRows,
        '#empty' => 'Nog geen expliciete communicatierelaties.',
      ],
      'contexts_heading' => ['#markup' => '<h3>Objectcontext</h3>'],
      'contexts' => [
        '#type' => 'table',
        '#header' => ['Type', 'Object', 'Rol', 'Status'],
        '#rows' => $contextRows,
        '#empty' => 'Nog geen objectcontext gekoppeld.',
      ],
      'sources_heading' => ['#markup' => '<h3>Bronnen</h3>'],
      'sources' => [
        '#type' => 'table',
        '#header' => ['Bronsysteem', 'Actor', 'Brondatum', 'Bestandsnaam', 'Status'],
        '#rows' => $sourceRows,
        '#empty' => 'Nog geen bronregistraties.',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function roleLabel(string $role): string {
    return match ($role) {
      'received_with' => 'Ontvangen via',
      'sent_with' => 'Meegestuurd met',
      'created_with' => 'Aangemaakt bij',
      default => $role,
    };
  }

}
