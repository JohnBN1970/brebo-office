<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Drupal\brebo_mail_intake\Service\MailAttentionScorer;
use Drupal\brebo_mail_intake\Service\MailFollowupAdvisor;
use Drupal\brebo_mail_intake\Service\MailMeaningExtractor;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows only Mail Intake items that still need human attention.
 */
final class MailIntakeReviewV2Controller extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $breboEntityTypeManager,
    private readonly MailMeaningExtractor $meaningExtractor,
    private readonly MailFollowupAdvisor $followupAdvisor,
    private readonly MailAttentionScorer $attentionScorer,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_mail_intake.meaning_extractor'),
      $container->get('brebo_mail_intake.followup_advisor'),
      $container->get('brebo_mail_intake.attention_scorer'),
    );
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

    $items = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $status = trim((string) $node->get('field_brebo_intake_status')->value);
      if ($status === 'Afgehandeld') {
        continue;
      }

      $classification = trim((string) $node->get('field_brebo_mail_classification')->value);
      $confidenceRaw = $node->get('field_brebo_match_confidence')->value;
      $confidence = $confidenceRaw !== NULL && $confidenceRaw !== '' ? (float) $confidenceRaw : NULL;
      $hasCanonicalBuilding = !$node->get('field_brebo_building_ref')->isEmpty();
      $hasCanonicalProject = !$node->get('field_brebo_project_ref')->isEmpty();

      $subject = trim((string) $node->get('field_brebo_comm_subject')->value);
      $body = trim((string) $node->get('field_brebo_transcript')->value);
      $meaning = $this->meaningExtractor->extract($subject, $body);
      $meaningLabels = $this->meaningLabels($meaning);
      $followupAdvice = $this->followupAdvisor->advise($classification, $meaning);
      $attention = $this->attentionScorer->score($classification, $meaning);

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
      if ($this->requiresProjectContext($classification) && !$hasCanonicalBuilding && !$hasCanonicalProject) {
        $reasons[] = 'geen formele gebouw/projectkoppeling';
      }
      if ($meaningLabels !== []) {
        $reasons[] = 'betekenissignaal controleren';
      }
      if ($reasons === []) {
        continue;
      }

      $suggestedBuilding = $this->referenceLabel($node, 'field_brebo_suggest_building_ref');
      $suggestedProject = $this->referenceLabel($node, 'field_brebo_suggest_project_ref');
      $attentionLabel = (string) ($attention['label'] ?? 'Normaal');

      $items[] = [
        'score' => (int) ($attention['score'] ?? 0),
        'changed' => (int) $node->getChangedTime(),
        'row' => [
          'subject' => Link::fromTextAndUrl($node->label(), Url::fromRoute('entity.node.canonical', ['node' => $node->id()])),
          'attention' => $this->trafficLightLabel($attentionLabel),
          'classification' => $classification !== '' ? $classification : '—',
          'meaning' => $meaningLabels !== [] ? implode(', ', $meaningLabels) : '—',
          'followup' => $followupAdvice !== [] ? implode(', ', $followupAdvice) : '—',
          'suggestion' => implode(' / ', array_filter([$suggestedBuilding, $suggestedProject])) ?: '—',
          'confidence' => $confidence !== NULL ? sprintf('%.0f%%', $confidence) : '—',
          'reason' => implode('; ', $reasons),
          'edit' => [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'review' => [
                  'title' => 'Beoordelen',
                  'url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
                ],
                'close' => [
                  'title' => 'Afhandelen',
                  'url' => Url::fromRoute('brebo_mail_intake.close', ['node' => $node->id()]),
                ],
              ],
            ],
          ],
        ],
      ];
    }

    usort($items, static function (array $a, array $b): int {
      $priority = $b['score'] <=> $a['score'];
      return $priority !== 0 ? $priority : ($b['changed'] <=> $a['changed']);
    });
    $rows = array_column($items, 'row');

    return [
      'intro' => [
        '#markup' => '<p><strong>Stoplicht:</strong> 🔴 direct aandacht · 🟠 aandacht/opvolging · 🟢 afgehandeld en daarom niet meer zichtbaar in deze actieve werkbak.</p><p>Prioriteit, betekenissignalen en voorgestelde opvolging zijn adviserend en worden pas na menselijke controle dossierwaarheid of formele actie.</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Onderwerp', 'Stoplicht', 'Classificatie', 'Signalen', 'Voorgestelde opvolging', 'Voorgesteld object', 'Vertrouwen', 'Waarom aandacht', 'Actie'],
        '#rows' => $rows,
        '#empty' => 'Geen Mail Intake-uitzonderingen. De werkbak is leeg.',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function trafficLightLabel(string $attentionLabel): string {
    return match ($attentionLabel) {
      'Hoog' => '🔴 Hoog',
      'Aandacht' => '🟠 Aandacht',
      default => '🟠 Normaal',
    };
  }

  private function requiresProjectContext(string $classification): bool {
    return in_array($classification, [
      'garantie',
      'oplevering',
      'klacht',
      'meerwerk',
      'inkoop',
      'offerte',
      'planning',
      'bewonersmelding',
      'foto',
    ], TRUE);
  }

  /**
   * @param array<string, mixed> $meaning
   *
   * @return string[]
   */
  private function meaningLabels(array $meaning): array {
    $labels = [];
    $labelMap = [
      'amount_present' => 'bedrag',
      'deadline_present' => 'termijn',
      'action_requested' => 'actie gevraagd',
      'risk_present' => 'risico',
    ];
    foreach (($meaning['signals'] ?? []) as $signal => $present) {
      if ($present && isset($labelMap[$signal])) {
        $labels[] = $labelMap[$signal];
      }
    }
    foreach (($meaning['subtypes'] ?? []) as $subtype) {
      $labels[] = str_replace('_', ' ', (string) $subtype);
    }
    return array_values(array_unique($labels));
  }

  private function referenceLabel(object $node, string $fieldName): string {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return '';
    }
    $entity = $node->get($fieldName)->entity;
    return $entity ? (string) $entity->label() : '';
  }

}
