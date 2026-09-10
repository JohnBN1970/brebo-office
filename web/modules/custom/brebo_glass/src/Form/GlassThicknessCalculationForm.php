<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\brebo_glass\Service\GlassCalculationRouteResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Professional toolbox entry point for glass thickness calculations.
 */
final class GlassThicknessCalculationForm extends FormBase {

  public function __construct(private readonly GlassCalculationRouteResolver $routeResolver) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_glass.calculation_route_resolver'));
  }

  public function getFormId(): string {
    return 'brebo_glass_thickness_calculation';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#markup' => '<p><strong>Glasdikteberekening</strong> — professioneel BREBO-gereedschap. De tool kiest eerst de technisch toegestane rekenroute. Een resultaat is pas productievrijgegeven nadat de gebruikte rekenmethode en brondata voor het betreffende domein zijn gevalideerd.</p>',
    ];
    $form['width_mm'] = ['#type' => 'number', '#title' => 'Glasbreedte (mm)', '#min' => 1, '#required' => TRUE];
    $form['height_mm'] = ['#type' => 'number', '#title' => 'Glashoogte (mm)', '#min' => 1, '#required' => TRUE];
    $form['glass_type'] = [
      '#type' => 'select', '#title' => 'Glastype', '#required' => TRUE,
      '#options' => [
        'float' => 'Floatglas', 'pvb_laminated' => 'PVB-gelaagd glas', 'tempered' => 'Thermisch gehard glas',
        'heat_strengthened' => 'Thermisch versterkt glas', 'insulating_double' => 'Isolerend dubbelglas', 'insulating_triple' => 'Triple beglazing',
      ],
    ];
    $form['orientation_deg'] = ['#type' => 'number', '#title' => 'Hoek t.o.v. horizontaal (graden)', '#default_value' => 90, '#min' => 0, '#max' => 180, '#required' => TRUE];
    $form['support'] = [
      '#type' => 'select', '#title' => 'Oplegging / inklemming', '#required' => TRUE,
      '#options' => ['two_sided' => '2-zijdig', 'three_sided' => '3-zijdig', 'four_sided' => '4-zijdig', 'clamped' => 'Ingeklemd'],
    ];
    $form['application'] = [
      '#type' => 'select', '#title' => 'Toepassing', '#required' => TRUE,
      '#options' => [
        'standard' => 'Gevel / standaard', 'door' => 'Deur', 'adjacent_door' => 'Naast deur', 'low_level' => 'Laag geplaatst',
        'wet_area' => 'Natte ruimte', 'ceiling' => 'Plafond', 'overhead' => 'Dak / bovenhoofdse beglazing', 'fall_protection' => 'Hoogteverschil / doorvalveilig',
        'fire_separation' => 'Brand-/rookscheiding',
      ],
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => 'Bepaal rekenroute', '#button_type' => 'primary'];

    if ($result = $form_state->get('route_result')) {
      $form['result'] = [
        '#type' => 'details', '#title' => 'Technische beoordeling', '#open' => TRUE,
        'status' => ['#markup' => '<p><strong>Status:</strong> ' . $this->t('@status', ['@status' => $result['status']]) . '</p>'],
        'route' => ['#markup' => '<p><strong>Rekenroute:</strong> ' . $this->t('@route', ['@route' => $result['route']]) . '</p>'],
        'reason' => ['#markup' => '<p>' . $this->t('@reason', ['@reason' => implode(' ', $result['reasons'])]) . '</p>'],
        'release' => ['#markup' => '<p><strong>Productievrijgave:</strong> NEE</p>'],
      ];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $input = [];
    foreach (['width_mm', 'height_mm', 'glass_type', 'orientation_deg', 'support', 'application'] as $key) {
      $input[$key] = $form_state->getValue($key);
    }
    $form_state->set('route_result', $this->routeResolver->resolve($input));
    $form_state->setRebuild();
  }

}
