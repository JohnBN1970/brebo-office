<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\brebo_data_intake\Service\IntakeReviewRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Presents pending source-neutral intake records for human review. */
final class IntakeReviewController extends ControllerBase {

  public function __construct(
    private readonly IntakeReviewRepository $reviews,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_data_intake.review_repository'),
      $container->get('date.formatter'),
    );
  }

  public function overview(): array {
    $rows = [];
    foreach ($this->reviews->pending() as $record) {
      $payload = is_array($record['payload']) ? $record['payload'] : [];
      $classification = trim((string) ($payload['classification'] ?? $payload['document_type'] ?? $payload['type'] ?? ''));
      $project = trim((string) ($payload['project_label'] ?? $payload['project_name'] ?? ''));
      $subject = trim((string) ($payload['subject'] ?? $payload['filename'] ?? $payload['original_filename'] ?? $record['external_key'] ?? ''));
      $rows[] = [
        'data' => [
          $this->dateFormatter->format((int) $record['created'], 'short'),
          (string) $record['source_label'],
          $subject !== '' ? $subject : $this->t('Zonder omschrijving'),
          $classification !== '' ? $classification : $this->t('Nog te bepalen'),
          $project !== '' ? $project : $this->t('Nog te koppelen'),
          $this->t('Controleren'),
        ],
      ];
    }

    return [
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
          $this->t('Wat moet ik doen?'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Mooi: er staat niets te wachten op menselijke controle.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
