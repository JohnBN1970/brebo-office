<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\brebo_data_intake\Service\IntakeReviewRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Presents pending source-neutral intake records for human review. */
final class IntakeReviewController extends ControllerBase {

  private const PAGE_SIZE = 50;

  public function __construct(
    private readonly IntakeReviewRepository $reviews,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly RequestStack $requestStack,
    private readonly EntityTypeManagerInterface $intakeEntityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_data_intake.review_repository'),
      $container->get('date.formatter'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
    );
  }

  public function overview(): array {
    $request = $this->requestStack->getCurrentRequest();
    $page = max(0, (int) ($request?->query->get('page', 0) ?? 0));
    $total = $this->reviews->pendingCount();
    $rows = [];

    foreach ($this->reviews->pending($page, self::PAGE_SIZE) as $record) {
      $storedPayload = is_array($record['payload']) ? $record['payload'] : [];
      $envelope = is_array($storedPayload['envelope'] ?? NULL) ? $storedPayload['envelope'] : [];
      $payload = is_array($envelope['payload'] ?? NULL) ? $envelope['payload'] : $storedPayload;
      $canonical = is_array($envelope['canonical'] ?? NULL) ? $envelope['canonical'] : [];

      $classificationKey = trim((string) ($envelope['classification'] ?? $payload['classification'] ?? $payload['document_type'] ?? $payload['type'] ?? ''));
      $classification = $this->classificationLabel($classificationKey);
      $project = trim((string) ($payload['project_label'] ?? $payload['project_name'] ?? $canonical['project_label'] ?? $canonical['project_name'] ?? ''));
      $projectNid = (int) ($canonical['project_nid'] ?? 0);
      if ($project === '' && $projectNid > 0) {
        $node = $this->intakeEntityTypeManager->getStorage('node')->load($projectNid);
        if ($node !== NULL && $node->bundle() === 'brebo_project') {
          $project = (string) $node->label();
        }
        else {
          $project = (string) $this->t('Project #@id', ['@id' => $projectNid]);
        }
      }

      $subject = trim((string) ($payload['subject'] ?? $payload['filename'] ?? $payload['original_filename'] ?? ''));
      if ($subject === '') {
        $sourceReference = trim((string) ($record['source_reference'] ?? ''));
        if ($sourceReference !== '' && !str_starts_with($sourceReference, 'sha256:')) {
          $subject = $sourceReference;
        }
      }

      $receivedAt = $this->receivedTimestamp($envelope['received_at'] ?? NULL, (int) $record['created']);
      $rows[] = [
        'data' => [
          $this->dateFormatter->format($receivedAt, 'short'),
          (string) $record['source_label'],
          $subject !== '' ? $subject : $this->t('Zonder omschrijving'),
          $classification !== '' ? $classification : $this->t('Nog te bepalen'),
          $project !== '' ? $project : $this->t('Nog te koppelen'),
          [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Beoordelen'),
              '#url' => Url::fromRoute('brebo_data_intake.review_decision', ['record' => (int) $record['id']]),
              '#attributes' => ['class' => ['button', 'button--small']],
            ],
          ],
        ],
      ];
    }

    $build = [
      'intro' => [
        '#markup' => '<p>' . $this->t('Hier staan intake-items waarvoor Office menselijke controle nodig heeft. De oorspronkelijke bron blijft leidend; vanuit deze werkbank bepalen we wat het is, waar het bij hoort en wat ermee moet gebeuren.') . '</p>',
      ],
      'queue' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Ontvangen'),
          $this->t('Bron'),
          $this->t('Wat is binnengekomen'),
          $this->t('Office denkt'),
          $this->t('Hoort bij'),
          $this->t('Actie'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Mooi: er staat niets te wachten op menselijke controle.'),
      ],
      '#cache' => ['max-age' => 0],
    ];

    $pageCount = (int) ceil($total / self::PAGE_SIZE);
    if ($pageCount > 1) {
      pager_default_initialize($total, self::PAGE_SIZE);
      $build['pager'] = ['#type' => 'pager'];
    }

    return $build;
  }

  /** Returns an operator-facing label while keeping future keys readable. */
  private function classificationLabel(string $key): string {
    return match ($key) {
      'purchase_invoice' => (string) $this->t('Inkoopfactuur'),
      'project_communication' => (string) $this->t('Projectcommunicatie'),
      'document' => (string) $this->t('Document'),
      'request' => (string) $this->t('Aanvraag / offerteaanvraag'),
      'relationship_message' => (string) $this->t('Relatiebericht'),
      'other' => (string) $this->t('Overig / eerst beoordelen'),
      default => $key !== '' ? str_replace(['_', '-'], ' ', $key) : '',
    };
  }

  /** Uses the source receipt time when available, with persistence time fallback. */
  private function receivedTimestamp(mixed $receivedAt, int $fallback): int {
    if (is_int($receivedAt) || (is_string($receivedAt) && ctype_digit($receivedAt))) {
      $timestamp = (int) $receivedAt;
      return $timestamp > 0 ? $timestamp : $fallback;
    }
    if (is_string($receivedAt) && trim($receivedAt) !== '') {
      $timestamp = strtotime($receivedAt);
      return $timestamp !== FALSE ? $timestamp : $fallback;
    }
    return $fallback;
  }

}
