<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\brebo_article\Service\Sales005Importer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Upload form for supplier catalogues.
 */
final class ArticleImportForm extends FormBase {

  /**
   * The SALES005 importer.
   */
  protected Sales005Importer $importer;

  public function __construct(Sales005Importer $importer) {
    $this->importer = $importer;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_article.sales005_importer'));
  }

  public function getFormId(): string {
    return 'brebo_article_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#markup' => '<p><strong>Leverancierscatalogus importeren</strong><br>Upload een Ketenstandaard SALES005-bestand als XML of ZIP. Een bestaande bronhash wordt niet opnieuw geïmporteerd en bestaande calculatieprijzen blijven ongewijzigd.</p>',
    ];
    $form['catalogue'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('SALES005-catalogus'),
      '#required' => TRUE,
      '#upload_location' => 'private://brebo-article-imports/',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'xml zip'],
      ],
      '#description' => $this->t('Toegestaan: XML of ZIP. De serverlimiet voor uploads blijft van toepassing.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Controleren en importeren'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fids = $form_state->getValue('catalogue');
    $file = !empty($fids[0]) ? File::load((int) $fids[0]) : NULL;
    if ($file === NULL) {
      $this->messenger()->addError($this->t('Het bronbestand kon niet worden geladen.'));
      return;
    }
    try {
      $result = $this->importer->import($file->getFileUri(), $file->getFilename());
      $file->setPermanent();
      $file->save();
      if ($result['status'] === 'already_imported') {
        $this->messenger()->addWarning($this->t('Deze catalogus was al geïmporteerd (import @id, @count artikelen).', ['@id' => $result['import_id'], '@count' => $result['records']]));
      }
      else {
        $this->messenger()->addStatus($this->t('Import @id gereed: @records artikelen, @created nieuw, @updated bijgewerkt en @prices prijsregels.', [
          '@id' => $result['import_id'],
          '@records' => $result['records'],
          '@created' => $result['articles_created'],
          '@updated' => $result['articles_updated'],
          '@prices' => $result['prices'],
        ]));
      }
    }
    catch (\Throwable $exception) {
      $this->getLogger('brebo_article')->error('SALES005-import mislukt: @message', ['@message' => $exception->getMessage()]);
      $this->messenger()->addError($this->t('Import afgebroken zonder gedeeltelijke wijzigingen: @message', ['@message' => $exception->getMessage()]));
    }
  }

}
