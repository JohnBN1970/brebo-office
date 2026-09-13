<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Form;

use Drupal\brebo_building_data\Service\PdokBuildingEnricher;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BuildingPdokRefreshForm extends FormBase {

  public function __construct(
    private readonly PdokBuildingEnricher $enricher,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_building_data.pdok_enricher'),
    );
  }

  public function getFormId(): string {
    return 'brebo_building_data_pdok_refresh';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_building') {
      throw new NotFoundHttpException();
    }

    $form_state->set('building_nid', (int) $node->id());

    $form['explanation'] = [
      '#markup' => '<p>Hiermee wordt BAG/PDOK opnieuw opgehaald voor dit gebouw. Dit draait als een losse actie en kan de normale gebouwpagina of het opslaan van gebouwgegevens niet blokkeren.</p>',
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('PDOK/BAG verversen'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => \Drupal\Core\Url::fromRoute('brebo_building_data.truth_workbench', ['node' => (int) $node->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $buildingNid = (int) $form_state->get('building_nid');
    $building = $this->entityTypeManager()->getStorage('node')->load($buildingNid);
    if (!$building instanceof NodeInterface || $building->bundle() !== 'brebo_building') {
      throw new NotFoundHttpException();
    }

    try {
      $result = $this->enricher->enrich($building);
      $state = (string) ($result['state'] ?? 'unknown');
      if ($state === 'enriched') {
        $this->messenger()->addStatus($this->t('PDOK/BAG bijgewerkt: pand @pand, @count adressen/eenheden.', [
          '@pand' => (string) ($result['pand_id'] ?? '—'),
          '@count' => (int) ($result['address_count'] ?? 0),
        ]));
      }
      else {
        $this->messenger()->addWarning($this->t('PDOK/BAG kon dit gebouw niet volledig verrijken (@state). Bestaande gebouwgegevens zijn ongewijzigd gebleven.', [
          '@state' => $state,
        ]));
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('brebo_building_data')->warning('Manual PDOK refresh failed for building @building: @message', [
        '@building' => $buildingNid,
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('PDOK/BAG is tijdelijk niet beschikbaar. De gebouwpagina en bestaande gegevens blijven gewoon beschikbaar.'));
    }

    $form_state->setRedirect('brebo_building_data.truth_workbench', ['node' => $buildingNid]);
  }

}
