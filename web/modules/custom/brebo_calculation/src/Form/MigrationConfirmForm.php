<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\GuardedLegacyMigrator;
use Drupal\brebo_calculation\Service\LegacyDryRunService;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Explicit confirmation gate for a guarded calculation migration. */
final class MigrationConfirmForm extends ConfirmFormBase {

  private int $calculationId = 0;

  private ?NodeInterface $calculation = NULL;

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
    return (string) $this->t('Deze bestaande calculatie omzetten naar de nieuwe calculatiewerkbank?');
  }

  public function getConfirmText(): string {
    return (string) $this->t('Omzetten naar nieuwe calculatie');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_calculation.migration_audit', ['node' => $this->calculationId]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation' || !$node->access('update', $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }
    $this->calculation = $node;
    $this->calculationId = (int) $node->id();
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
    if (!$this->calculation instanceof NodeInterface || !$this->calculation->access('update', $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }
    $result = $this->migrator->migrate((int) $this->calculation->id());
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
    $form_state->setRedirect('brebo_calculation.workbench', ['node' => $this->calculationId]);
  }

}
