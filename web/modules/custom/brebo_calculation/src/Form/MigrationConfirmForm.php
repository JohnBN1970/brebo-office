<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\GuardedLegacyMigrator;
use Drupal\brebo_calculation\Service\LegacyDryRunService;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Explicit confirmation gate for a guarded calculation migration. */
final class MigrationConfirmForm extends ConfirmFormBase {

  private int $calculationId = 0;

  public function __construct(
    private readonly LegacyDryRunService $dryRun,
    private readonly GuardedLegacyMigrator $migrator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.legacy_dry_run'),
      $container->get('brebo_calculation.guarded_legacy_migrator'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_migration_confirm';
  }

  public function getQuestion(): string {
    return (string) $this->t('Calculatie @id migreren naar het nieuwe calculatiedomein?', ['@id' => $this->calculationId]);
  }

  public function getConfirmText(): string {
    return (string) $this->t('Gecontroleerd migreren');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_calculation.migration_audit', ['node' => $this->calculationId]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $node = NULL): array {
    $this->calculationId = (int) $node;
    $preview = $this->dryRun->preview($this->calculationId);
    if (!$preview->isSafeToMigrate()) {
      $this->messenger()->addError($this->t('Migratie geblokkeerd: de actuele dry-run is niet schoon.'));
      return [
        'blocked' => ['#markup' => $this->t('Los eerst alle migratieverschillen en waarschuwingen op via de migratiecontrole.')],
        'back' => [
          '#type' => 'link',
          '#title' => $this->t('Terug naar migratiecontrole'),
          '#url' => $this->getCancelUrl(),
        ],
      ];
    }

    $form = parent::buildForm($form, $form_state);
    $form['warning'] = [
      '#weight' => -10,
      '#markup' => '<p><strong>' . $this->t('De broncalculatie blijft intact. Alleen de additieve nieuwe domeintabellen worden gevuld. De migrator voert vlak voor schrijven opnieuw een dry-run uit en rolt volledig terug bij een verificatiefout.') . '</strong></p>',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->migrator->migrate($this->calculationId);
    $this->messenger()->addStatus($this->t(
      'Calculatie @id is gemigreerd als @version: @rows regels, @nodes structuurnodes. Hash: @hash',
      [
        '@id' => $result->calculationId,
        '@version' => $result->version,
        '@rows' => $result->rowCount,
        '@nodes' => $result->structureCount,
        '@hash' => $result->contentHash,
      ],
    ));
    $form_state->setRedirect('brebo_calculation.migration_audit', ['node' => $this->calculationId]);
  }

}
