<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Data-free Finance presentation screen. Never renders provider, invoice,
 * customer, bank account or financial records.
 */
final class OfficeFinancePresentationView
{
    public static function render(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>FinanceCenter — Presentatiemodus</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#162639;font:16px system-ui,sans-serif}
header{background:#162639;color:white;padding:24px clamp(18px,4vw,60px)}
main{max-width:1200px;margin:auto;padding:32px 22px}
h1{margin:0 0 8px}p{line-height:1.5}.tag{font-weight:700;color:#8b4b00;background:#fff0b3;padding:10px 14px;border-radius:8px;display:inline-block}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px;margin:28px 0}
article{background:white;border:1px solid #dce4ed;border-radius:12px;padding:22px}
article h2{font-size:17px;margin:0 0 16px}.masked{font-size:28px;font-weight:700;letter-spacing:2px}
nav{display:flex;gap:12px;flex-wrap:wrap;margin:24px 0}nav span{background:#e6edf5;border-radius:7px;padding:10px 14px}
small{color:#536479}
</style>
</head>
<body>
<header><strong>BREBO Office</strong><h1>FinanceCenter</h1><p>Financieel overzicht</p></header>
<main>
<div class="tag" role="status">PRESENTATIEMODUS ACTIEF — geen werkelijke financiële gegevens</div>
<nav aria-label="Finance modules"><span>Overzicht</span><span>Facturen</span><span>Bankzaken</span><span>Betalingen</span><span>Rapportages</span></nav>
<div class="grid">
<article><h2>Beschikbaar banksaldo</h2><div class="masked">€ ••••••</div><small>Afgeschermd</small></article>
<article><h2>Inkomsten</h2><div class="masked">€ ••••••</div><small>Afgeschermd</small></article>
<article><h2>Uitgaven</h2><div class="masked">€ ••••••</div><small>Afgeschermd</small></article>
<article><h2>Openstaande facturen</h2><div class="masked">€ ••••••</div><small>Afgeschermd</small></article>
</div>
<p>Bankrekeningen, transacties, facturen en onderliggende documenten zijn tijdens deze presentatie niet beschikbaar.</p>
<small>Deze demonstratieweergave gebruikt geen live financiële gegevens.</small>
</main></body></html>
HTML;
    }
}
