<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\CollectionDebtorProfileRepository;
use Drupal\brebo_finance\Service\SalesInvoiceDebtorResolver;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Edits structured legal/collection data for the canonical debtor relation. */
final class CollectionDebtorProfileForm extends FormBase {

  public function __construct(
    private readonly SalesInvoiceDebtorResolver $resolver,
    private readonly CollectionDebtorProfileRepository $profiles,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      new SalesInvoiceDebtorResolver($container->get('database'), $container->get('keyvalue'), $container->get('entity_type.manager')),
      new CollectionDebtorProfileRepository($container->get('keyvalue')),
    );
  }

  public function getFormId(): string { return 'brebo_finance_collection_debtor_profile_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $invoice = NULL): array {
    if ($invoice === NULL || $invoice <= 0) throw new \InvalidArgumentException('Verkoopfactuur is verplicht.');
    $relation = $this->resolver->resolve($invoice);
    $organizationId = (int) $relation['organization_id'];
    $profile = $this->profiles->get($organizationId);
    $address = is_array($profile['address'] ?? NULL) ? $profile['address'] : [];
    $form_state->set('invoice_id', $invoice); $form_state->set('organization_id', $organizationId);
    $form['summary'] = ['#markup' => '<p><strong>Debiteur:</strong> ' . htmlspecialchars((string) $relation['name']) . '<br><strong>E-mail:</strong> ' . htmlspecialchars((string) $relation['email']) . '</p>'];
    $form['type'] = ['#type' => 'select', '#title' => $this->t('Debiteurtype'), '#options' => ['business' => $this->t('Zakelijk'), 'individual' => $this->t('Particulier')], '#default_value' => (string) ($profile['type'] ?? 'business')];
    $form['company_name'] = ['#type' => 'textfield', '#title' => $this->t('Bedrijfsnaam'), '#default_value' => (string) ($profile['company_name'] ?? $relation['name'])];
    $form['first_name'] = ['#type' => 'textfield', '#title' => $this->t('Voornaam'), '#default_value' => (string) ($profile['first_name'] ?? '')];
    $form['last_name'] = ['#type' => 'textfield', '#title' => $this->t('Achternaam'), '#default_value' => (string) ($profile['last_name'] ?? '')];
    $form['customer_number'] = ['#type' => 'textfield', '#title' => $this->t('Relatie-/klantnummer'), '#default_value' => (string) ($profile['customer_number'] ?? '')];
    $form['street'] = ['#type' => 'textfield', '#title' => $this->t('Straat'), '#required' => TRUE, '#default_value' => (string) ($address['street'] ?? '')];
    $form['house_number'] = ['#type' => 'textfield', '#title' => $this->t('Huisnummer'), '#required' => TRUE, '#default_value' => (string) ($address['house_number'] ?? '')];
    $form['postal_code'] = ['#type' => 'textfield', '#title' => $this->t('Postcode'), '#required' => TRUE, '#default_value' => (string) ($address['postal_code'] ?? '')];
    $form['city'] = ['#type' => 'textfield', '#title' => $this->t('Plaats'), '#required' => TRUE, '#default_value' => (string) ($address['city'] ?? '')];
    $form['country_code'] = ['#type' => 'textfield', '#title' => $this->t('Landcode'), '#required' => TRUE, '#maxlength' => 2, '#default_value' => (string) ($address['country_code'] ?? 'NL')];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Incassogegevens opslaan'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ((string) $form_state->getValue('type') === 'individual' && trim((string) $form_state->getValue('last_name')) === '') $form_state->setErrorByName('last_name', $this->t('Achternaam is verplicht voor een particuliere debiteur.'));
    if ((string) $form_state->getValue('type') === 'business' && trim((string) $form_state->getValue('company_name')) === '') $form_state->setErrorByName('company_name', $this->t('Bedrijfsnaam is verplicht voor een zakelijke debiteur.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->profiles->save((int) $form_state->get('organization_id'), [
      'type' => (string) $form_state->getValue('type'), 'company_name' => (string) $form_state->getValue('company_name'), 'first_name' => (string) $form_state->getValue('first_name'), 'last_name' => (string) $form_state->getValue('last_name'), 'customer_number' => (string) $form_state->getValue('customer_number'),
      'street' => (string) $form_state->getValue('street'), 'house_number' => (string) $form_state->getValue('house_number'), 'postal_code' => (string) $form_state->getValue('postal_code'), 'city' => (string) $form_state->getValue('city'), 'country_code' => (string) $form_state->getValue('country_code'),
    ], (int) $this->currentUser()->id());
    $this->messenger()->addStatus($this->t('Gestructureerde incassogegevens zijn opgeslagen bij deze centrale debiteurrelatie.'));
    $form_state->setRedirect('brebo_finance.receivables_bulk');
  }
}
