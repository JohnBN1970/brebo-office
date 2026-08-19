<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement\Form;

use Drupal\brebo_procurement\Service\ProcurementOfferManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Supplier-offer comparison and winner selection workbench. */
final class ProcurementOfferWorkbenchForm extends FormBase {
  private int $requestId = 0;

  public function __construct(
    private readonly Connection $database,
    private readonly ProcurementOfferManager $offers,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_procurement.offer_manager'));
  }

  public function getFormId(): string { return 'brebo_procurement_offer_workbench_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $request_id = NULL): array {
    $this->requestId = (int) $request_id;
    $request = $this->database->select('brebo_procurement_request', 'r')->fields('r')->condition('id', $this->requestId)->execute()->fetchAssoc();
    if (!$request) throw new \InvalidArgumentException('Leveranciersaanvraag bestaat niet.');

    $form['request'] = ['#type'=>'item','#title'=>$this->t('Aanvraag'),'#markup'=>$this->t('@number · project @project · gewenste levering @date', ['@number'=>$request['request_number'],'@project'=>$request['project_nid'] ?: '-','@date'=>$request['requested_delivery_date'] ?: '-'])];
    $comparison = $this->offers->compare($this->requestId);
    $options = [];
    $form['comparison'] = ['#type'=>'table','#header'=>[$this->t('Leverancier'),$this->t('Offerte'),$this->t('Bedrag'),$this->t('Levering'),$this->t('Techniek'),$this->t('Voorwaarden'),$this->t('Status')],'#empty'=>$this->t('Nog geen offertes geregistreerd.')];
    foreach ($comparison as $offer) {
      $id = (int) $offer['id'];
      $options[$id] = $offer['supplier_name'] . ' · € ' . number_format((float) $offer['quoted_total'], 2, ',', '.');
      $form['comparison'][$id] = [
        'supplier'=>['#plain_text'=>$offer['supplier_name']],
        'offer'=>['#plain_text'=>$offer['offer_number'] ?: '-'],
        'amount'=>['#plain_text'=>$offer['currency'].' '.number_format((float)$offer['quoted_total'],2,',','.')],
        'delivery'=>['#plain_text'=>($offer['delivery_date'] ?: '-').($offer['delivery_ok'] ? ' ✓' : ' ⚠')],
        'technical'=>['#plain_text'=>$offer['technical_ok'] ? 'Conform' : 'Afwijking: '.$offer['technical_deviation']],
        'conditions'=>['#plain_text'=>$offer['conditions_summary'] ?: '-'],
        'status'=>['#plain_text'=>$offer['status']],
      ];
    }

    $form['add'] = ['#type'=>'details','#title'=>$this->t('Offerte registreren'),'#open'=>!$comparison];
    foreach ([
      'supplier_name'=>['Leverancier','textfield',TRUE], 'supplier_ref'=>['Leveranciersreferentie','textfield',FALSE], 'offer_number'=>['Offertenummer','textfield',FALSE],
      'offer_date'=>['Offertedatum','date',FALSE], 'valid_until'=>['Geldig tot','date',FALSE], 'quoted_total'=>['Totaalbedrag','number',TRUE],
      'delivery_date'=>['Toegezegde leverdatum','date',FALSE], 'lead_time_days'=>['Levertijd (dagen)','number',FALSE],
    ] as $key=>$cfg) {
      $form['add'][$key] = ['#type'=>$cfg[1],'#title'=>$this->t($cfg[0]),'#required'=>$cfg[2]];
      if ($cfg[1] === 'number') $form['add'][$key]['#min'] = 0;
    }
    $form['add']['currency'] = ['#type'=>'textfield','#title'=>$this->t('Valuta'),'#default_value'=>'EUR','#size'=>6];
    $form['add']['technical_deviation'] = ['#type'=>'textarea','#title'=>$this->t('Technische afwijking'),'#description'=>$this->t('Leeg laten als de offerte volledig conform de aanvraag is.')];
    $form['add']['conditions_summary'] = ['#type'=>'textarea','#title'=>$this->t('Voorwaarden / bijzonderheden')];
    $form['actions'] = ['#type'=>'actions'];
    $form['actions']['add_offer'] = ['#type'=>'submit','#value'=>$this->t('Offerte toevoegen'),'#submit'=>['::addOffer']];

    if ($options) {
      $form['winner'] = ['#type'=>'radios','#title'=>$this->t('Voorkeursleverancier'),'#options'=>$options];
      $form['actions']['select_winner'] = ['#type'=>'submit','#value'=>$this->t('Winnaar selecteren'),'#button_type'=>'primary','#submit'=>['::selectWinner']];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function addOffer(array &$form, FormStateInterface $form_state): void {
    try {
      $this->offers->create($this->requestId, $form_state->getValues(), $this->currentUser());
      $this->messenger()->addStatus($this->t('Leveranciersofferte toegevoegd aan de vergelijking.'));
      $form_state->setRebuild();
    }
    catch (\Throwable $e) { $this->messenger()->addError($e->getMessage()); $form_state->setRebuild(); }
  }

  public function selectWinner(array &$form, FormStateInterface $form_state): void {
    $offerId = (int) $form_state->getValue('winner');
    if ($offerId <= 0) { $this->messenger()->addError($this->t('Selecteer eerst een offerte.')); $form_state->setRebuild(); return; }
    try {
      $this->offers->selectWinner($this->requestId, $offerId, $this->currentUser());
      $this->messenger()->addStatus($this->t('Voorkeursleverancier geselecteerd. De aanvraag is klaar om naar bestelling te worden omgezet.'));
      $form_state->setRebuild();
    }
    catch (\Throwable $e) { $this->messenger()->addError($e->getMessage()); $form_state->setRebuild(); }
  }
}
