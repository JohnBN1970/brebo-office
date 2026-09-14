<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/** Adds missing BAG/PDOK map positions to existing BREBO buildings. */
final class BuildingPdokBulkRefreshForm extends ConfirmFormBase {

  public function getFormId(): string {
    return 'brebo_building_data_pdok_bulk_refresh';
  }

  public function getQuestion(): string {
    return (string) $this->t('Ontbrekende kaartposities automatisch aanvullen?');
  }

  public function getDescription(): string {
    return (string) $this->t('Alleen gebouwen zonder opgeslagen BAG-kaartpositie worden verwerkt. BREBO Office gebruikt dezelfde strikte PDOK/BAG-controle als bij handmatig verversen. Onvolledige of niet eenduidig gevonden adressen worden overgeslagen; bestaande kaartposities worden niet gewijzigd.');
  }

  public function getConfirmText(): string {
    return (string) $this->t('Kaartposities aanvullen');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_office_core.buildings');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $database = \Drupal::database();
    $schema = $database->schema();
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = array_values($storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_building')
      ->sort('nid', 'ASC')
      ->execute());

    if ($ids === []) {
      $this->messenger()->addStatus($this->t('Er zijn geen gebouwen om te verwerken.'));
      $form_state->setRedirect('brebo_office_core.buildings');
      return;
    }

    $mapped = [];
    if ($schema->tableExists('brebo_building_address')
      && $schema->fieldExists('brebo_building_address', 'latitude')
      && $schema->fieldExists('brebo_building_address', 'longitude')) {
      $query = $database->select('brebo_building_address', 'a');
      $query->addField('a', 'building_nid');
      $query->condition('building_nid', $ids, 'IN');
      $query->isNotNull('latitude');
      $query->isNotNull('longitude');
      $query->groupBy('building_nid');
      $mapped = array_map('intval', $query->execute()->fetchCol());
    }

    $missing = array_values(array_diff(array_map('intval', $ids), $mapped));
    if ($missing === []) {
      $this->messenger()->addStatus($this->t('Alle gebouwen hebben al een kaartpositie.'));
      $form_state->setRedirect('brebo_office_core.buildings');
      return;
    }

    $operations = [];
    foreach ($missing as $buildingNid) {
      $operations[] = [[self::class, 'processBuilding'], [$buildingNid]];
    }

    batch_set([
      'title' => $this->t('BAG/PDOK-kaartposities aanvullen'),
      'init_message' => $this->t('Gebouwen voorbereiden…'),
      'progress_message' => $this->t('Gebouw @current van @total verwerken…'),
      'error_message' => $this->t('De bulkverrijking is voortijdig gestopt.'),
      'operations' => $operations,
      'finished' => [self::class, 'finished'],
    ]);
  }

  /** Batch operation for one building. */
  public static function processBuilding(int $buildingNid, array &$context): void {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $building = $storage->load($buildingNid);
    $context['results']['processed'] = (int) ($context['results']['processed'] ?? 0) + 1;

    if (!$building instanceof NodeInterface || $building->bundle() !== 'brebo_building' || !$building->access('update')) {
      $context['results']['skipped'] = (int) ($context['results']['skipped'] ?? 0) + 1;
      return;
    }

    try {
      /** @var \Drupal\brebo_building_data\Service\PdokBuildingEnricher $enricher */
      $enricher = \Drupal::service('brebo_building_data.pdok_enricher');
      $result = $enricher->enrich($building);
      $state = (string) ($result['state'] ?? 'unknown');
      if ($state === 'enriched') {
        $context['results']['enriched'] = (int) ($context['results']['enriched'] ?? 0) + 1;
        $context['results']['addresses'] = (int) ($context['results']['addresses'] ?? 0) + (int) ($result['address_count'] ?? 0);
      }
      else {
        $context['results']['skipped'] = (int) ($context['results']['skipped'] ?? 0) + 1;
        $context['results']['states'][$state] = (int) ($context['results']['states'][$state] ?? 0) + 1;
      }
    }
    catch (\Throwable $e) {
      $context['results']['failed'] = (int) ($context['results']['failed'] ?? 0) + 1;
      \Drupal::logger('brebo_building_data')->warning('Bulk PDOK refresh failed for building @building: @message', [
        '@building' => $buildingNid,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /** Batch completion callback. */
  public static function finished(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::messenger();
    if (!$success) {
      $messenger->addError(t('Niet alle gebouwen konden worden verwerkt. Bestaande gebouwgegevens zijn behouden.'));
      return;
    }

    $enriched = (int) ($results['enriched'] ?? 0);
    $skipped = (int) ($results['skipped'] ?? 0);
    $failed = (int) ($results['failed'] ?? 0);
    $messenger->addStatus(t('Kaartverrijking afgerond: @enriched gebouwen verrijkt, @skipped overgeslagen, @failed mislukt.', [
      '@enriched' => $enriched,
      '@skipped' => $skipped,
      '@failed' => $failed,
    ]));
  }

}
