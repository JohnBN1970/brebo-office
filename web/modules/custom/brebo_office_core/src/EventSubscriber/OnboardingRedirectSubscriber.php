<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\brebo_office_core\Service\AdministrationAccessManager;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Sends newly released users through the welcome onboarding once. */
final class OnboardingRedirectSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly AdministrationAccessManager $access,
  ) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onRequest', 25]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || $this->currentUser->isAnonymous()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->getMethod() !== 'GET' || $request->isXmlHttpRequest()) {
      return;
    }
    $route = (string) $request->attributes->get('_route', '');
    if ($route === '' || str_starts_with($route, 'brebo_office_core.welcome_onboarding') || str_starts_with($route, 'brebo_office_core.onboarding_tour_progress') || str_starts_with($route, 'user.logout') || str_starts_with($route, 'user.login')) {
      return;
    }

    $user = User::load((int) $this->currentUser->id());
    if ($user === NULL || !$this->access->onboardingRequired($user)) {
      return;
    }
    if ($this->access->availableAdministrations($user) === []) {
      return;
    }

    $event->setResponse(new TrustedRedirectResponse(Url::fromRoute('brebo_office_core.welcome_onboarding')->toString()));
  }

}
