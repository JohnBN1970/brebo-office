<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a version-bound, print-friendly commercial offer.
 */
final class OfferPreviewController extends ControllerBase {

  public function view(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_offer_version') {
      throw new NotFoundHttpException();
    }
    if (!$node->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $post_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_offer_post')
      ->condition('field_brebo_offer_version_ref.target_id', $node->id())
      ->sort('field_brebo_offer_post_seq', 'ASC')
      ->execute();
    $groups = ['Basisaanbieding' => [], 'Stelpost' => [], 'Verrekenpost' => [], 'Optie' => []];
    $base_total = 0.0;
    $option_total = 0.0;
    $vat_total = 0.0;
    foreach ($storage->loadMultiple($post_ids) as $post) {
      if (!$post instanceof NodeInterface || !$post->access('view')) {
        continue;
      }
      $type = (string) ($post->get('field_brebo_offer_post_type')->value ?? 'Basisaanbieding');
      $amount = (float) ($post->get('field_brebo_offer_amount')->value ?? 0);
      $included = (bool) ($post->get('field_brebo_in_offer_total')->value ?? FALSE);
      $rate = (float) ($post->get('field_brebo_vat_rate')->value ?? 0);
      $groups[$type][] = [
        'description' => (string) ($post->get('field_brebo_offer_post_desc')->value ?? $post->label()),
        'quantity' => (string) ($post->get('field_brebo_offer_quantity')->value ?? ''),
        'unit' => (string) ($post->get('field_brebo_offer_unit')->value ?? ''),
        'unit_price' => (float) ($post->get('field_brebo_offer_unit_price')->value ?? 0),
        'amount' => $amount,
      ];
      if ($included) {
        $base_total += $amount;
        if ((string) ($post->get('field_brebo_vat_treatment')->value ?? '') === 'Belast') {
          $vat_total += $amount * $rate / 100;
        }
      }
      elseif ($type === 'Optie') {
        $option_total += $amount;
      }
    }

    $offer_number = $this->value($node, 'field_brebo_offer_number');
    $version = $this->value($node, 'field_brebo_offer_version');
    $valid_until = $this->value($node, 'field_brebo_valid_until');
    $layout = $this->value($node, 'field_brebo_offer_layout');
    $vat_treatment = $this->value($node, 'field_brebo_vat_default');
    $g_on = (bool) ($node->get('field_brebo_g_account_on')->value ?? FALSE);
    $g_pct = (float) ($node->get('field_brebo_g_account_pct')->value ?? 0);

    $html = '<article class="brebo-offer">';
    $html .= '<div class="brebo-offer-actions"><button type="button" onclick="window.print()">Afdrukken / Opslaan als PDF</button></div>';
    $html .= '<header><div><h1>BREBO</h1><p>Onderhoud · Renovatie · Verduurzaming</p></div><div class="meta"><strong>Offerte ' . Html::escape($offer_number) . '</strong><br>Versie ' . Html::escape($version) . '<br>Layout: ' . Html::escape($layout);
    if ($valid_until !== '') {
      $html .= '<br>Geldig tot: ' . Html::escape($valid_until);
    }
    $html .= '</div></header>';
    $html .= $this->section('Aanbieding', $this->value($node, 'field_brebo_offer_scope'));
    foreach ($groups as $type => $rows) {
      if (!$rows) {
        continue;
      }
      $html .= '<section><h2>' . Html::escape($type) . '</h2><table><thead><tr><th>Omschrijving</th><th>Aantal</th><th>Eenheid</th><th>Eenheidsprijs</th><th>Bedrag</th></tr></thead><tbody>';
      foreach ($rows as $row) {
        $html .= '<tr><td>' . Html::escape($row['description']) . '</td><td>' . Html::escape($row['quantity']) . '</td><td>' . Html::escape($row['unit']) . '</td><td class="money">' . $this->money($row['unit_price']) . '</td><td class="money">' . $this->money($row['amount']) . '</td></tr>';
      }
      $html .= '</tbody></table></section>';
    }
    $html .= '<section class="totals"><table><tr><th>Aanneemsom excl. btw</th><td>' . $this->money($base_total) . '</td></tr>';
    if ($vat_treatment === 'Belast') {
      $html .= '<tr><th>Btw</th><td>' . $this->money($vat_total) . '</td></tr><tr class="grand"><th>Totaal incl. btw</th><td>' . $this->money($base_total + $vat_total) . '</td></tr>';
    }
    else {
      $html .= '<tr><th>Btw-behandeling</th><td>' . Html::escape($vat_treatment) . '</td></tr>';
    }
    if ($option_total > 0) {
      $html .= '<tr><th>Opties excl. btw (niet inbegrepen)</th><td>' . $this->money($option_total) . '</td></tr>';
    }
    $html .= '</table></section>';
    if ($g_on) {
      $html .= '<section><h2>G-rekening</h2><p>' . number_format($g_pct, 2, ',', '.') . '% van de ' . Html::escape(mb_strtolower($this->value($node, 'field_brebo_g_account_base'))) . ' wordt op de overeengekomen G-rekening betaald. Het rekeningnummer wordt separaat beveiligd verstrekt.</p></section>';
    }
    $html .= $this->section('Uitsluitingen', $this->value($node, 'field_brebo_exclusions'));
    $html .= $this->section('Voorwaarden', $this->value($node, 'field_brebo_work_terms'));
    $html .= '<footer>BREBO · versiegebonden offerte ' . Html::escape($offer_number) . ' v' . Html::escape($version) . '</footer></article>';

    return [
      '#type' => 'inline_template',
      '#template' => '<style>{{ css|raw }}</style>{{ content|raw }}',
      '#context' => ['css' => $this->css(), 'content' => $html],
      '#cache' => ['tags' => $node->getCacheTags(), 'contexts' => ['user.permissions']],
    ];
  }

  private function section(string $title, string $text): string {
    return trim($text) === '' ? '' : '<section><h2>' . Html::escape($title) . '</h2><div class="prose">' . nl2br(Html::escape($text)) . '</div></section>';
  }

  private function value(NodeInterface $node, string $field): string {
    return $node->hasField($field) ? (string) ($node->get($field)->value ?? '') : '';
  }

  private function money(float $amount): string {
    return '€ ' . number_format($amount, 2, ',', '.');
  }

  private function css(): string {
    return '.brebo-offer{max-width:1000px;margin:0 auto;background:#fff;color:#18212b;font:15px/1.55 Arial,sans-serif;padding:36px}.brebo-offer header{display:flex;justify-content:space-between;border-bottom:4px solid #163a5f;padding-bottom:20px;margin-bottom:28px}.brebo-offer h1{color:#163a5f;font-size:42px;margin:0}.brebo-offer h2{color:#163a5f;border-bottom:1px solid #cbd5df;padding-bottom:6px;margin-top:30px}.brebo-offer .meta{text-align:right}.brebo-offer table{width:100%;border-collapse:collapse}.brebo-offer th,.brebo-offer td{padding:9px;border-bottom:1px solid #dce3e9;text-align:left}.brebo-offer .money,.brebo-offer .totals td{text-align:right;white-space:nowrap}.brebo-offer .totals{margin-left:auto;max-width:520px}.brebo-offer .grand{font-size:17px}.brebo-offer footer{margin-top:42px;padding-top:12px;border-top:1px solid #cbd5df;color:#667}.brebo-offer-actions{text-align:right;margin-bottom:20px}.brebo-offer-actions button{background:#163a5f;color:#fff;border:0;padding:11px 18px;border-radius:4px;font-weight:bold}@media print{body{background:#fff}.brebo-offer{max-width:none;padding:0}.brebo-offer-actions,.region-primary-menu,.region-breadcrumb,.tabs{display:none!important}@page{size:A4;margin:15mm}}';
  }

}
