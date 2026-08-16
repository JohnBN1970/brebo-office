<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\LegacyDryRunService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Overview of legacy calculations and migration readiness. */
final class MigrationOverviewController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LegacyDryRunService $dryRun,
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_calculation.legacy_dry_run'),
      $container->get('database'),
    );
  }

  public function overview(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_calculation')
      ->sort('changed', 'DESC')
      ->execute();
    $calculations = $storage->loadMultiple($ids);

    $rows = [];
    $counts = ['green' => 0, 'blocked' => 0, 'migrated' => 0];

    foreach ($calculations as $calculation) {
      $id = (int) $calculation->id();
      $migration = $this->latestMigration($id);

      try {
        $preview = $this->dryRun->preview($id);
        $safe = $preview->isSafeToMigrate();
        $difference = $preview->reconciliation->difference;
        $warningCount = count($preview->warnings);
      }
      catch (\Throwable $e) {
        $safe = FALSE;
        $difference = 0.0;
        $warningCount = 1;
      }

      if ($migration !== NULL) {
        $state = '✅ ' . $this->t('Gemigreerd');
        $counts['migrated']++;
      }
      elseif ($safe) {
        $state = '🟢 ' . $this->t('Klaar voor migratie');
        $counts['green']++;
      }
      else {
        $state = '🔴 ' . $this->t('Geblokkeerd');
        $counts['blocked']++;
      }

      $rows[] = [
        'data' => [
          $id,
          $calculation->label(),
          $state,
          '€ ' . number_format($difference, 2, ',', '.'),
          $warningCount,
          $migration['version'] ?? '—',
          Link::fromTextAndUrl(
            $this->t('Open audit'),
            Url::fromRoute('brebo_calculation.migration_audit', ['node' => $id]),
          )->toRenderable(),
        ],
      ];
    }

    $build['summary'] = [
      '#markup' => '<p><strong>' . $this->t(
        '@total calculaties · @green klaar · @blocked geblokkeerd · @migrated gemigreerd',
        [
          '@total' => count($rows),
          '@green' => $counts['green'],
          '@blocked' => $counts['blocked'],
          '@migrated' => $counts['migrated'],
        ],
      ) . '</strong></p>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Calculatie'),
        $this->t('Migratiestatus'),
        $this->t('Verschil'),
        $this->t('Waarschuwingen'),
        $this->t('Domeinversie'),
        $this->t('Actie'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Geen calculaties gevonden.'),
    ];

    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /** @return array<string, mixed>|null */
  private function latestMigration(int $calculationId): ?array {
    $record = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version', 'status', 'content_hash'])
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()->fetchAssoc();
    return $record ?: NULL;
  }

}
