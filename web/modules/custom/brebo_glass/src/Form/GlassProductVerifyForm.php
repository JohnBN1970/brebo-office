<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_glass\Service\GlassProductRepository;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Separately verifies a draft glass product with an audit record.
 */
final class GlassProductVerifyForm extends ConfirmFormBase {

  private int $productId;
  private array $product;

  public function __construct(private readonly GlassProductRepository $repository) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_glass.product_repository'));
  }

  public function getFormId(): string {
    return 'brebo_glass_product_verify_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $product_id = NULL): array {
    $this->productId = (int) $product_id;
    $this->product = $this->repository->find($this->productId) ?? [];
    if ($this->product === []) {
      throw new \InvalidArgumentException('Glasproduct bestaat niet.');
    }
    $form['verification_note'] = ['#type' => 'textarea', '#title' => $this->t('Verificatiemotivatie'), '#required' => TRUE, '#description' => $this->t('Beschrijf welke bron, berekening en productdocumentatie inhoudelijk zijn gecontroleerd.')];
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string {
    return (string) $this->t('Product @product technisch verifiëren en activeren?', ['@product' => $this->product['label'] ?? '#' . $this->productId]);
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_glass.product_overview');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->repository->verify($this->productId, (int) $this->currentUser()->id(), (string) $form_state->getValue('verification_note'));
    $this->messenger()->addStatus($this->t('Product geverifieerd en beschikbaar voor automatische glaskeuze.'));
    $form_state->setRedirect('brebo_glass.product_overview');
  }

}
