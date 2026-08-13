<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Embeds the generic document dossier on BREBO project/building nodes.
 */
final class NodeDocumentDossierController extends DocumentContextDossierController {

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_document_data.revision_series'),
    );
  }

  public function nodeView(NodeInterface $node): array {
    $contextType = match ($node->bundle()) {
      'brebo_project' => 'project',
      'brebo_building' => 'building',
      default => '',
    };

    if ($contextType === '') {
      return ['#markup' => ''];
    }

    $build = $this->view($contextType, (int) $node->id());
    $build['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => 'Documenten - ' . $node->label(),
      '#weight' => -20,
    ];
    return $build;
  }

  public function access(NodeInterface $node, AccountInterface $account): AccessResult {
    if (!in_array($node->bundle(), ['brebo_project', 'brebo_building'], TRUE)) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    return $node->access('view', $account, TRUE)->addCacheableDependency($node);
  }

}
