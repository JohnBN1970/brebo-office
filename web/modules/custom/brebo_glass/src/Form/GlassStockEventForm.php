<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\brebo_glass\Service\GlassAvailabilityService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Minimal field form for events Office cannot infer itself. */
final class GlassStockEventForm extends FormBase {
  public function __construct(private readonly GlassAvailabilityService $availability) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_glass.availability'));
  }

  public function getFormId(): string { return 'brebo_glass_stock_event_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $position_id = NULL, ?string $event_type = NULL): array {
    if (!$position_id || !in_array($event_type, ['delivered', 'damaged'], TRUE)) {
      throw new \InvalidArgumentException('Ongeldige glasregistratie.');
    }
    $form_state->set('position_id', $position_id);
    $form_state->set('event_type', $event_type);
    $isDamage = $event_type === 'damaged';
    $form['intro'] = ['#markup' => '<p>' . ($isDamage ? $this->t('Registreer alleen wat werkelijk beschadigd of gebroken is.') : $this->t('Registreer alleen wat werkelijk op het project is ontvangen.')) . '</p>'];
    $form['quantity'] = [
      '#type' => 'number', '#title' => $this->t('Aantal'), '#default_value' => 1,
      '#min' => 0.01, '#step' => 0.01, '#required' => TRUE,
    ];
    if ($isDamage) {
      $form['cause'] = [
        '#type' => 'select', '#title' => $this->t('Waar ontdekt / oorzaak'), '#required' => TRUE,
        '#options' => ['transport' => $this->t('Transport'), 'storage' => $this->t('Op de bok / opslag'), 'installation' => $this->t('Montage'), 'unknown' => $this->t('Onbekend')],
      ];
    }
    $form['reference'] = ['#type' => 'textfield', '#title' => $isDamage ? $this->t('Referentie (optioneel)') : $this->t('Leverbon (optioneel)'), '#maxlength' => 128];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $isDamage ? $this->t('Glasbreuk registreren') : $this->t('Levering registreren'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $eventType = (string) $form_state->get('event_type');
    $cause = $eventType === 'damaged' ? (string) $form_state->getValue('cause') : NULL;
    $this->availability->recordForPosition(
      (int) $form_state->get('position_id'), $eventType, (float) $form_state->getValue('quantity'),
      (string) $form_state->getValue('reference'), $cause, (int) $this->currentUser()->id()
    );
    $this->messenger()->addStatus($eventType === 'damaged' ? $this->t('Glasbreuk is geregistreerd; projectbeschikbaarheid wordt hiermee verlaagd.') : $this->t('Werkelijk ontvangen glas is geregistreerd.'));
    $form_state->setRedirect('brebo_glass.position_overview');
  }
}
