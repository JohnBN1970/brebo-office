<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows only Mail Intake items that still need human attention.
 */
final class MailIntakeReviewController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $breboEntityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Builds an exception-focused review queue.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  public function queue(): array {
    $storage = $this->breboEntityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_communication')
      ->exists('field_brebo_intake_status')
      ->sort('changed', 'DESC')
      ->range(0, 250)
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $status = trim((string) $node->get('field_brebo_intake_status')->value);
      $classification = trim((string) $node->get('field_brebo_mail_classification')->value);
      $confidenceRaw = $node->get('field_brebo_match_confidence')->value;
      $confidence = $confidenceRaw !== NULL && $confidenceRaw !== '' ? (float) $confidenceRaw : NULL;
      $hasCanonicalBuilding = !$node->get('field_brebo_building_ref')->isEmpty();
      $hasCanonicalProject = !$node->get('field_brebo_project_ref')->isEmpty();

      $reasons = [];
      if (in_array($status, ['Nieuw', 'Controle vereist'], TRUE)) {
        $reasons[] = $status;
      }
      if ($classification === '' || $classification === 'overig') {
        $reasons[] = 'classificatie controleren';
      }
      if ($confidence === NULL || $confidence < 100.0) {
        $reasons[] = $confidence === NULL
          ? 'koppeling niet beoordeeld'
          : sprintf('koppelvertrouwen %.0f%%', $confidence);
      }
      if (!$hasCanonicalBuilding && !$hasCanonicalProject) {
        $reasons[] = 'geen formele gebouw/projectkoppeling';
      }

      if ($reasons === []) {
        continue;
      }

      $suggestedBuilding = $this->referenceLabel($node, 'field_brebo_suggest_building_ref');
      $suggestedProject = $this->referenceLabel($node, 'field_brebo_suggest_project_ref');

      $rows[] = [
        'subject' => Link::fromTextAndUrl($node->label(), Url::fromRoute('entity.node.canonical', ['node' => $node->id()])),
        'classification' => $classification !== '' ? $classification : '—',
        'suggestion' => implode(' / ', array_filter([$suggestedBuilding, $suggestedProject])) ?: '—',
        'confidence' => $confidence !== NULL ? sprintf('%.0f%%', $confidence) : '—',
        'reason' => implode('; ', $reasons),
        'edit' => Link::fromTextAndUrl('Beoordelen', Url::fromRoute('entity.node.edit_form', ['node' => $node->id()])),
      ];
    }

    return [
      'intro' => [
        '#markup' => '<p>Alleen uitzonderingen en onzekere Mail Intake-items worden hier getoond. Items zonder beoordelingsreden verdwijnen automatisch uit deze werkbak.</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Onderwerp', 'Classificatie', 'Voorgesteld object', 'Vertrouwen', 'Waarom aandacht', 'Actie'],
        '#rows' => $rows,
        '#empty' => 'Geen Mail Intake-uitzonderingen. De werkbak is leeg.',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function referenceLabel(object $node, string $fieldName): string {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return '';
    }
    $entity = $node->get($fieldName)->entity;
    return $entity ? (string) $entity->label() : '';
  }

}
