<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement\Form;

use Drupal\brebo_procurement\Service\ProcurementOrderManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ProcurementReceiptForm extends FormBase {
  private int $orderId = 0;

  public function __construct(
    private readonly Connection $database,
    private readonly ProcurementOrderManager $orders,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_procurement.order_manager'));
  }

  public function getFormId(): string { return 'brebo_procurement_receipt_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $order_id = NULL): array {
    $this->orderId=(int)$order_id;
    $order=$this->database->select('brebo_procurement_order','o')->fields('o')->condition('id',$this->orderId)->execute()->fetchAssoc();
    if (!$order) throw new \InvalidArgumentException('Bestelling bestaat niet.');
    $form['order']=['#type'=>'item','#title'=>$this->t('Bestelling'),'#markup'=>$this->t('@number · @supplier · verwacht @date', ['@number'=>$order['order_number'],'@supplier'=>$order['supplier_name'],'@date'=>$order['expected_delivery_date'] ?: '-'])];
    foreach ([
      'quantity_ok'=>'Aantal klopt',
      'dimensions_ok'=>'Maatvoering klopt',
      'specification_ok'=>'Specificatie/opbouw klopt',
      'damage_free'=>'Geen transportschade of zichtbare gebreken',
      'checksum_ok'=>'Technische vrijgave/checksum komt overeen',
    ] as $key=>$label) {
      $form[$key]=['#type'=>'checkbox','#title'=>$this->t($label)];
    }
    $form['note']=['#type'=>'textarea','#title'=>$this->t('Ontvangstnotitie'),'#description'=>$this->t('Bij een afkeur verplicht de afwijking, aantallen en vervolgstap beschrijven.')];
    $form['actions']=['#type'=>'actions'];
    $form['actions']['submit']=['#type'=>'submit','#value'=>$this->t('Ontvangstcontrole vastleggen'),'#button_type'=>'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $allOk=TRUE;
    foreach (['quantity_ok','dimensions_ok','specification_ok','damage_free','checksum_ok'] as $key) $allOk=$allOk && (bool)$form_state->getValue($key);
    if (!$allOk && trim((string)$form_state->getValue('note'))==='') $form_state->setErrorByName('note',$this->t('Beschrijf de afwijking en vervolgstap bij een afgekeurde levering.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $inspection=[];
      foreach (['quantity_ok','dimensions_ok','specification_ok','damage_free','checksum_ok'] as $key) $inspection[$key]=(bool)$form_state->getValue($key);
      $inspection['note']=(string)$form_state->getValue('note');
      $this->orders->receive($this->orderId,$inspection,$this->currentUser());
      $accepted=!in_array(FALSE,array_values(array_intersect_key($inspection,array_flip(['quantity_ok','dimensions_ok','specification_ok','damage_free','checksum_ok']))),TRUE);
      $this->messenger()->addStatus($accepted ? $this->t('Levering akkoord. Bronobjecten zijn op geleverd gezet.') : $this->t('Levering heeft een ontvangstafwijking en is niet vrijgegeven voor montage.'));
      $form_state->setRedirect('brebo_glass.position_overview');
    } catch (\Throwable $e) { $this->messenger()->addError($e->getMessage()); }
  }
}
