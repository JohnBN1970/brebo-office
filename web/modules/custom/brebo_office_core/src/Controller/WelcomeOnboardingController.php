<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\brebo_office_core\Service\AdministrationAccessManager;
use Drupal\brebo_office_core\Service\OnboardingTourManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** First-login welcome page for released Office users. */
final class WelcomeOnboardingController extends ControllerBase {

  public function __construct(
    private readonly AdministrationAccessManager $access,
    private readonly OnboardingTourManager $tours,
    private readonly CsrfTokenGenerator $csrf,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_office_core.administration_access_manager'),
      $container->get('brebo_office_core.onboarding_tour_manager'),
      $container->get('csrf_token'),
    );
  }

  public function page(): array {
    if ($this->currentUser()->isAnonymous()) {
      throw new AccessDeniedHttpException();
    }
    $user = User::load((int) $this->currentUser()->id());
    if ($user === NULL) {
      throw new AccessDeniedHttpException();
    }
    $administrations = $this->access->availableAdministrations($user);
    if ($administrations === []) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-welcome']],
        'title' => ['#markup' => '<h1>Welkom bij Office</h1>'],
        'pending' => ['#markup' => '<div class="messages messages--warning"><strong>Je account is aangemaakt, maar nog niet vrijgegeven voor een administratie.</strong><br>Na vrijgave krijg je toegang tot de toegewezen administratie(s).</div>'],
      ];
    }

    $names = array_map(static fn(array $a): string => (string) ($a['trade_name'] ?? $a['legal_name'] ?? $a['code'] ?? 'Administratie'), $administrations);
    $tourId = 'welcome_core';
    $state = $this->tours->state($user, $tourId);
    $progressUrl = Url::fromRoute('brebo_office_core.onboarding_tour_progress', [], [
      'query' => ['token' => $this->csrf->get('brebo_guided_tour')],
    ])->toString();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-welcome']],
      '#attached' => [
        'library' => ['brebo_office_core/guided-tour'],
        'drupalSettings' => [
          'breboGuidedTour' => [
            'tourId' => $tourId,
            'currentStep' => (int) ($state['step'] ?? 0),
            'progressUrl' => $progressUrl,
            'steps' => [
              ['selector' => '.brebo-welcome__intro', 'title' => 'Welkom bij Office', 'text' => 'Office brengt projecten, documenten, financiën en bedrijfssturing samen in één werkomgeving.'],
              ['selector' => '.brebo-welcome__administrations', 'title' => 'Jouw administraties', 'text' => 'Je ziet alleen administraties waarvoor jouw toegang expliciet is vrijgegeven. Eén account kan toegang hebben tot meerdere administraties.'],
              ['selector' => '.brebo-welcome__roles', 'title' => 'Rol en toegang', 'text' => 'Je rol bepaalt wat je mag doen. De administratiekoppeling bepaalt binnen welke administratie dat geldt.'],
              ['selector' => '.brebo-welcome__projects', 'title' => 'Projecten', 'text' => 'Projecten erven hun administratie. Offertes, contracten, facturen en documenten volgen daarna automatisch dezelfde context.'],
              ['selector' => '.brebo-welcome__finish', 'title' => 'Klaar om te starten', 'text' => 'Na deze rondleiding is je basis-onboarding voltooid. Functies kunnen later eigen korte rondleidingen tonen wanneer dat relevant is.'],
            ],
          ],
        ],
      ],
      'intro' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-welcome__intro']],
        'heading' => ['#markup' => '<h1>Welkom bij Office</h1>'],
        'text' => ['#markup' => '<p>Je account is vrijgegeven. We laten kort zien hoe jouw toegang is opgebouwd.</p>'],
      ],
      'administrations' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-welcome__administrations']],
        'heading' => ['#markup' => '<h2>Jouw administraties</h2>'],
        'list' => ['#theme' => 'item_list', '#items' => array_values($names)],
      ],
      'roles' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-welcome__roles']],
        'heading' => ['#markup' => '<h2>Toegang op maat</h2>'],
        'text' => ['#markup' => '<p>Office combineert je functionele rol met de administraties waarvoor je bent vrijgegeven.</p>'],
      ],
      'projects' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-welcome__projects']],
        'heading' => ['#markup' => '<h2>Alles volgt de projectcontext</h2>'],
        'text' => ['#markup' => '<p>Bij een project wordt de administratie bepaald. Afgeleide processen gebruiken vervolgens dezelfde juridische en financiële context.</p>'],
      ],
      'finish' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-welcome__finish']],
        'heading' => ['#markup' => '<h2>Daarna gewoon werken</h2>'],
        'text' => ['#markup' => '<p>Je kunt rondleidingen overslaan en later hervatten. Nieuwe functies kunnen alleen hun eigen korte uitleg tonen.</p>'],
      ],
    ];
  }

}
