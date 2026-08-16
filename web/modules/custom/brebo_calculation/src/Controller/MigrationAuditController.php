<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\LegacyDryRunService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Read-only legacy calculation migration audit. */
final class MigrationAuditController extends ControllerBase {

  public function __construct(
    private readonly LegacyDryRunService $dryRun,
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUserAccount,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.legacy_dry_run'),
      $container->get('database'),
      $container->get('current_user'),
    );
  }

  public function audit(int $node): array {
    $result = $this->dryRun->preview($node);
    $reconciliation = $result->reconciliation;
    $migration = $this->migrationStatus($node);
    $safe = $result->isSafeToMigrate();

    $build['state'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-calculation-migration-state']],
    ];

    if ($migration !== NULL) {
      $build['state']['message'] = [
        '#type' => 'status_messages',
      ];
      $build['state']['existing'] = [
        '#markup' => '<p><strong>' . $this->t(
          'Nieuwe domeinversie aanwezig: @version · status @status · @nodes structuurnodes · @rows regels.',
          [
            '@version' => $migration['version'],
            '@status' => $migration['status'],
            '@nodes' => $migration['structure_count'],
            '@rows' => $migration['row_count'],
          ],
        ) . '</strong></p>',
      ];
    }
    elseif ($safe) {
      $build['state']['ready'] = [
        '#markup' => '<p><strong>🟢 ' . $this->t('Audit is schoon. Deze calculatie kan gecontroleerd worden gemigreerd.') . '</strong></p>',
      ];
      if ($this->currentUserAccount->hasPermission('migrate brebo calculation')) {
        $build['state']['action'] = Link::fromTextAndUrl(
          $this->t('Gecontroleerd migreren'),
          Url::fromRoute('brebo_calculation.migration_confirm', ['node' => $node]),
        )->toRenderable();
        $build['state']['action']['#attributes']['class'][] = 'button';
        $build['state']['action']['#attributes']['class'][] = 'button--primary';
      }
    }
    else {
      $build['state']['blocked'] = [
        '#markup' => '<p><strong>🔴 ' . $this->t('Migratie geblokkeerd. Los eerst de financiële verschillen en/of waarschuwingen hieronder op.') . '</strong></p>',
      ];
    }

    $build['summary'] = [
      '#type' => 'table',
      '#caption' => $this->t('Read-only migratiecontrole — er wordt niets gewijzigd.'),
      '#header' => [$this->t('Controle'), $this->t('Waarde')],
      '#rows' => [
        [$this->t('Calculatie'), $result->calculationId],
        [$this->t('Structuurnodes'), count($result->structure)],
        [$this->t('Calculatieregels'), count($result->rows)],
        [$this->t('Legacy totaal'), '€ ' . number_format($reconciliation->legacyAmount, 2, ',', '.')],
        [$this->t('Nieuw totaal'), '€ ' . number_format($reconciliation->newAmount, 2, ',', '.')],
        [$this->t('Verschil'), '€ ' . number_format($reconciliation->difference, 2, ',', '.')],
        [$this->t('Tolerantie'), '€ ' . number_format($reconciliation->tolerance, 2, ',', '.')],
        [$this->t('Financiële aansluiting'), $reconciliation->matches ? $this->t('JA') : $this->t('NEE')],
        [$this->t('Veilig voor migratie'), $safe ? $this->t('JA') : $this->t('NEE')],
        [$this->t('Nieuwe domeinversie'), $migration !== NULL ? $migration['version'] : $this->t('Nog niet gemigreerd')],
      ],
    ];

    $totals = $result->totals->toArray();
    $build['buckets'] = [
      '#type' => 'table',
      '#caption' => $this->t('Nieuwe financiële bakken'),
      '#header' => [$this->t('Bak'), $this->t('Bedrag')],
      '#rows' => array_map(
        static fn (string $key, float $amount): array => [$key, '€ ' . number_format($amount, 2, ',', '.')],
        array_keys($totals),
        array_values($totals),
      ),
    ];

    $build['warnings'] = [
      '#type' => 'details',
      '#title' => $this->formatPlural(count($result->warnings), '1 migratiewaarschuwing', '@count migratiewaarschuwingen'),
      '#open' => $result->warnings !== [],
    ];
    $build['warnings']['items'] = [
      '#theme' => 'item_list',
      '#items' => $result->warnings ?: [$this->t('Geen waarschuwingen.')],
    ];

    $build['structure'] = [
      '#type' => 'table',
      '#caption' => $this->t('Gemapte structuur'),
      '#header' => [$this->t('Type'), $this->t('Code'), $this->t('Omschrijving'), $this->t('Parent'), $this->t('Locatie')],
      '#rows' => array_map(static fn ($item): array => [
        $item->type->value,
        $item->code,
        $item->label,
        $item->parentId ?? '—',
        $item->locationRef ?? '—',
      ], $result->structure),
    ];

    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /** @return array<string, mixed>|null */
  private function migrationStatus(int $calculationId): ?array {
    $record = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version', 'status', 'content_hash'])
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()->fetchAssoc();

    if (!$record) {
      return NULL;
    }

    $record['structure_count'] = (int) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', (string) $record['version'])
      ->countQuery()->execute()->fetchField();
    $record['row_count'] = (int) $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', (string) $record['version'])
      ->countQuery()->execute()->fetchField();

    return $record;
  }

}
