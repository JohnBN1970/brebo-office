<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\user\Entity\Role;

/** Idempotently provisions the shared HTML format used for BREBO mail. */
final class MailEditorProvisioner {

  public function __construct(private readonly ModuleHandlerInterface $moduleHandler) {}

  public function ensure(): void {
    if (!$this->moduleHandler->moduleExists('editor') || !$this->moduleHandler->moduleExists('ckeditor5')) {
      throw new \RuntimeException('Drupal Text Editor en CKEditor 5 zijn nog niet geactiveerd.');
    }

    // Keep the structural markup and inline presentation attributes that real
    // HTML email relies on. Active content and document-level style blocks are
    // deliberately not allowed in the shared Office DOM.
    $allowedHtml = '<p class style align dir> <br> <strong> <b> <em> <i> <u> <a href title target rel style> <ul class style> <ol start reversed class style> <li class style> <blockquote class style> <h1 class style align> <h2 class style align> <h3 class style align> <h4 class style align> <h5 class style align> <h6 class style align> <pre class style> <code> <hr class style> <div id class style align dir> <span id class style dir> <table id class style width border cellspacing cellpadding align dir role> <thead class style> <tbody class style> <tfoot class style> <tr id class style align valign> <th id class style width height align valign colspan rowspan> <td id class style width height align valign colspan rowspan> <img src alt title width height border class style align> <center class style> <font color face size> <small class style> <sub> <sup>';

    $format = FilterFormat::load('brebo_mail_html');
    if (!$format) {
      $format = FilterFormat::create([
        'uuid' => '89b3ce59-814f-4edf-b361-72f0e8ac6d54',
        'format' => 'brebo_mail_html',
        'name' => 'BREBO HTML-mail',
        'weight' => 0,
      ]);
    }
    $format->setFilterConfig('filter_html', [
      'id' => 'filter_html',
      'provider' => 'filter',
      'status' => TRUE,
      'weight' => -10,
      'settings' => [
        'allowed_html' => $allowedHtml,
        'filter_html_help' => FALSE,
        'filter_html_nofollow' => FALSE,
      ],
    ]);
    $format->setFilterConfig('filter_htmlcorrector', [
      'id' => 'filter_htmlcorrector',
      'provider' => 'filter',
      'status' => TRUE,
      'weight' => 10,
      'settings' => [],
    ]);
    $format->save();

    if (!Editor::load('brebo_mail_html')) {
      Editor::create([
        'format' => 'brebo_mail_html',
        'editor' => 'ckeditor5',
        'settings' => [
          'toolbar' => [
            'items' => [
              'bold', 'italic', 'underline', '|', 'link', '|',
              'bulletedList', 'numberedList', '|', 'blockQuote', 'heading',
              '|', 'code', '|', 'undo', 'redo',
            ],
          ],
          'plugins' => [
            'ckeditor5_heading' => [
              'enabled_headings' => ['heading2', 'heading3', 'heading4'],
            ],
            'ckeditor5_list' => [
              'properties' => ['reversed' => FALSE, 'startIndex' => TRUE],
              'multiBlock' => TRUE,
            ],
          ],
        ],
        'image_upload' => ['status' => FALSE],
      ])->save();
    }

    $permission = 'use text format brebo_mail_html';
    foreach (['administrator', 'brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_kwaliteitsmanager'] as $roleId) {
      $role = Role::load($roleId);
      if ($role && !$role->hasPermission($permission)) {
        $role->grantPermission($permission)->save();
      }
    }
  }

}
