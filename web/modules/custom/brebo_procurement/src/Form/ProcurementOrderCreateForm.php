<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement\Form;

use Drupal\brebo_procurement\Service\ProcurementOrderManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ProcurementOrderCreateForm extends ConfirmFormBase {
  private int $requestId = 0;

  public function __construct(
    private readonly Connection $database,
    private readonly ProcurementOrderManager $orders,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_procurement.order_manager'));
  }

  public function getFormId(): string { return 'brebo_procurement_order_create_form'; }
  public function getQuestion(): string { return (string) $this->t('Geselecteerde offerte omzetten naar bestelling?'); }
  public function getCancelUrl(): Url { return Url::fromRoute('brebo_procurement.offer_workbench', ['request_id'=>$this->requestId]); }
  public function getConfirmText(): string { return (string) $this->t('Bestelling plaatsen'); }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $request_id = NULL): array {
    $this->requestId = (int) $request_id;
    $request = $this->database->select('brebo_procurement_request','r')->fields('r')->condition('id',$this->requestId)->execute()->fetchAssoc();
    if (!$request) throw new \InvalidArgumentException('Leveranciersaanvraag bestaat niet.');
    $offer = $this->database->select('brebo_procurement_offer','o')->fields('o')->condition('request_id',$this->requestId)->condition('status','selected')->execute()->fetchAssoc();
    if (!$offer) {
      $form['blocked']=['#markup'=>'<p>Selecteer eerst een technisch akkoord bevonden offerte.</p>'];
      return $form;
    }
    $form['summary']=['#type'=>'item','#title'=>$this->t('Geselecteerde leverancier'),'#markup'=>$this->t('@supplier · @amount @currency · levering @date', ['@supplier'=>$offer['supplier_name'],'@amount'=>number_format((float)$offer['quoted_total'],2,',','.'),'@currency'=>$offer['currency'],'@date'=>$offer['delivery_date'] ?: ($request['requested_delivery_date'] ?: '-')])];
    return parent::buildForm($form,$form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $orderId=$this->orders->createFromSelectedOffer($this->requestId,$this->currentUser());
      $this->messenger()->addStatus($this->t('Bestelling @id is geplaatst en bronobjecten zijn op besteld gezet.', ['@id'=>$orderId]));
      $form_state->setRedirect('brebo_procurement.order_receive',['order_id'=>$orderId]);
    } catch (\Throwable $e) { $this->messenger()->addError($e->getMessage()); }
  }
}
