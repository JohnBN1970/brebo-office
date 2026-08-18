<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_glass\Service\GlassProductRepository;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds an unverified product candidate to the BREBO glass catalog.
 */
final class GlassProductForm extends FormBase {

  public function __construct(private readonly GlassProductRepository $repository) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_glass.product_repository'));
  }

  public function getFormId(): string {
    return 'brebo_glass_product_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['notice'] = ['#markup' => '<p><strong>' . $this->t('Een nieuw product blijft concept totdat een bevoegde beoordelaar de brongegevens afzonderlijk verifieert.') . '</strong></p>'];
    $form['product_code'] = ['#type' => 'textfield', '#title' => $this->t('Productcode'), '#maxlength' => 64, '#required' => TRUE];
    $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Productnaam'), '#maxlength' => 255, '#required' => TRUE];
    $form['glass_type'] = ['#type' => 'select', '#title' => $this->t('Glastype'), '#options' => ['single' => $this->t('Enkelglas'), 'insulating' => $this->t('Isolatieglas'), 'laminated' => $this->t('Gelaagd glas'), 'tempered' => $this->t('Gehard glas'), 'fire_resistant' => $this->t('Brandwerend glas')], '#required' => TRUE];
    $form['composition'] = ['#type' => 'textfield', '#title' => $this->t('Opbouw'), '#maxlength' => 128, '#required' => TRUE];
    $form['wind_resistance_kpa'] = ['#type' => 'number', '#title' => $this->t('Opgegeven windweerstand (kPa)'), '#min' => 0.001, '#step' => 0.001, '#required' => TRUE];
    $form['max_width_mm'] = ['#type' => 'number', '#title' => $this->t('Maximale breedte (mm)'), '#min' => 1, '#required' => TRUE];
    $form['max_height_mm'] = ['#type' => 'number', '#title' => $this->t('Maximale hoogte (mm)'), '#min' => 1, '#required' => TRUE];
    $form['weight_kg_m2'] = ['#type' => 'number', '#title' => $this->t('Gewicht (kg/m²)'), '#min' => 0.01, '#step' => 0.01, '#required' => TRUE];
    $form['safety_class'] = ['#type' => 'textfield', '#title' => $this->t('Letselveiligheidsklasse'), '#maxlength' => 64];
    $form['fire_class'] = ['#type' => 'textfield', '#title' => $this->t('Brandklasse'), '#maxlength' => 64];
    $form['source_reference'] = ['#type' => 'textfield', '#title' => $this->t('Technische bron'), '#maxlength' => 255, '#required' => TRUE];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Product als concept opslaan'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = [];
    foreach (['product_code', 'label', 'glass_type', 'composition', 'wind_resistance_kpa', 'max_width_mm', 'max_height_mm', 'weight_kg_m2', 'safety_class', 'fire_class', 'source_reference'] as $key) {
      $values[$key] = $form_state->getValue($key);
    }
    $values['verified'] = 0;
    $values['active'] = 0;
    $this->repository->insert($values);
    $this->messenger()->addStatus($this->t('Glasproduct als concept opgeslagen; technische verificatie is nog verplicht.'));
    $form_state->setRedirect('brebo_glass.product_add');
  }

}
