<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\LegacyDryRunService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Read-only legacy calculation migration audit. */
final class MigrationAuditController extends ControllerBase {

  public function __construct(
    private readonly LegacyDryRunService $dryRun,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_calculation.legacy_dry_run'));
  }

  public function audit(int $node): array {
    $result = $this->dryRun->preview($node);
    $reconciliation = $result->reconciliation;

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
        [$this->t('Veilig voor migratie'), $result->isSafeToMigrate() ? $this->t('JA') : $this->t('NEE')],
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

}
