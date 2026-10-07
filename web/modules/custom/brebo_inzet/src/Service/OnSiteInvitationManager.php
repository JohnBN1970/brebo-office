<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\OnSiteInstallLinkBuilderInterface;

/**
 * Creates the personal OnSite installation invitation for an Office user.
 */
final class OnSiteInvitationManager {

  public function __construct(
    private readonly OnSiteIdentityResolver $identityResolver,
    private readonly OnSiteActivationManager $activationManager,
    private readonly OnSiteInstallLinkBuilderInterface $installLinkBuilder,
  ) {}

  /**
   * @return array{mobile: string, install_url: string, language: string}
   */
  public function invite(int $uid): array {
    $identity = $this->identityResolver->resolveByUid($uid);
    if ($identity === NULL) {
      throw new \InvalidArgumentException('Alleen actieve gebruikers met een geldig OnSite mobiel nummer kunnen worden uitgenodigd.');
    }

    $mobile = $this->identityResolver->normalizeMobile((string) $identity['mobile']);
    if ($mobile === '') {
      throw new \InvalidArgumentException('Gebruiker heeft geen geldig mobiel nummer.');
    }

    $language = (string) ($identity['language'] ?? 'nl');
    $activationToken = $this->activationManager->issue($uid);
    $installUrl = $this->installLinkBuilder->build($language, $activationToken);

    return [
      'mobile' => $mobile,
      'install_url' => $installUrl,
      'language' => $language,
    ];
  }

}
