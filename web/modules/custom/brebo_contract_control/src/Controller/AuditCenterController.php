<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Controller;

use Drupal\brebo_contract_control\Service\AuditPackageGenerator;
use Drupal\brebo_contract_control\Service\AuditPackageVerificationService;
use Drupal\brebo_contract_control\Service\AuditReadinessEngine;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class AuditCenterController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly AuditReadinessEngine $readiness,
    private readonly AuditPackageGenerator $generator,
    private readonly AuditPackageVerificationService $verification,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_contract_control.audit_readiness'),
      $container->get('brebo_contract_control.audit_package_generator'),
      $container->get('brebo_contract_control.audit_package_verification'),
    );
  }

  public function overview(): array {
    $scopes = $this->database->select('brebo_policy_rule', 'p')
      ->fields('p', ['scope'])
      ->condition('status', 'active')
      ->distinct()
      ->orderBy('scope')
      ->execute()->fetchCol();

    $cards = [];
    foreach ($scopes as $scope) {
      $assessment = $this->readiness->assess((string) $scope);
      $packages = $this->database->select('brebo_audit_package', 'a')
        ->fields('a', ['id', 'package_ref', 'readiness_pct', 'status', 'package_hash', 'generated_at'])
        ->condition('scope', (string) $scope)
        ->orderBy('generated_at', 'DESC')
        ->range(0, 5)
        ->execute()->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($packages as &$package) {
        $verify = $this->verification->verify((int) $package['id']);
        $package['integrity_status'] = $verify['status'] ?? 'unknown';
      }
      unset($package);

      $missing = [];
      foreach ((array) ($assessment['checks'] ?? []) as $check) {
        if (!($check['ready'] ?? FALSE)) {
          $missing[] = [
            'policy' => $check['policy_code'] ?? '',
            'title' => $check['title'] ?? '',
            'missing' => implode(', ', (array) ($check['missing'] ?? [])),
          ];
        }
      }

      $cards[] = [
        '#type' => 'details',
        '#title' => (string) $scope . ' — ' . (string) ($assessment['readiness_pct'] ?? 0) . '%',
        '#open' => TRUE,
        'summary' => [
          '#markup' => '<p><strong>Status:</strong> ' . $this->t((string) ($assessment['status'] ?? 'unknown')) . ' &nbsp; <strong>Controles:</strong> ' . (int) ($assessment['audit_ready_controls'] ?? 0) . '/' . (int) ($assessment['required_controls'] ?? 0) . '</p>',
        ],
        'generate' => [
          '#type' => 'link',
          '#title' => $this->t('Auditpakket genereren'),
          '#url' => Url::fromRoute('brebo_contract_control.audit_generate', ['scope' => $scope]),
          '#attributes' => ['class' => ['button', 'button--primary'], 'data-method' => 'post'],
        ],
        'missing' => [
          '#type' => 'table',
          '#caption' => $this->t('Ontbrekend bewijs / openstaande punten'),
          '#header' => [$this->t('Policy'), $this->t('Titel'), $this->t('Ontbreekt')],
          '#rows' => array_map(static fn(array $row): array => [$row['policy'], $row['title'], $row['missing']], $missing),
          '#empty' => $this->t('Geen ontbrekende punten.'),
        ],
        'history' => [
          '#type' => 'table',
          '#caption' => $this->t('Recente auditpakketten'),
          '#header' => [$this->t('Referentie'), $this->t('Readiness'), $this->t('Status'), $this->t('Integriteit'), $this->t('Datum')],
          '#rows' => array_map(static fn(array $row): array => [
            $row['package_ref'],
            $row['readiness_pct'] . '%',
            $row['status'],
            $row['integrity_status'],
            date('d-m-Y H:i', (int) $row['generated_at']),
          ], $packages),
          '#empty' => $this->t('Nog geen auditpakketten gegenereerd.'),
        ],
      ];
    }

    return [
      '#type' => 'container',
      'intro' => ['#markup' => '<p>Audit readiness, ontbrekend bewijs, pakketgeschiedenis en integriteitscontrole in één overzicht.</p>'],
      'scopes' => $cards ?: ['#markup' => '<p>Er zijn nog geen actieve policy-scopes om te beoordelen.</p>'],
    ];
  }

  public function generate(string $scope): RedirectResponse {
    $result = $this->generator->generate($scope, (int) $this->currentUser()->id());
    if ($result['generated'] ?? FALSE) {
      $this->messenger()->addStatus($this->t('Auditpakket @ref is bevroren en geregistreerd.', ['@ref' => $result['package_ref'] ?? '']));
    }
    else {
      $this->messenger()->addWarning($this->t('Auditpakket kon niet worden gegenereerd: dossier is nog niet voldoende audit-ready.'));
    }
    return new RedirectResponse(Url::fromRoute('brebo_contract_control.audit_center')->toString());
  }
}
