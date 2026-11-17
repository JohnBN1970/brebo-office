<?php
declare(strict_types=1);
namespace Brebo\Office\Intake;
final class WebsiteOfficeLoginFormRenderer {
  public function render(string $csrf,bool $error): string {
    $action=htmlspecialchars(OfficeMount::route('inloggen'),ENT_QUOTES,'UTF-8');
    $token=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8');
    $message=$error?'<p role="alert">Ongeldige aanmeldgegevens.</p>':'';
    return '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BREBO Office - aanmelden</title></head><body><main><h1>BREBO Office</h1>'.$message.'<form method="post" action="'.$action.'"><input type="hidden" name="csrf_token" value="'.$token.'"><label>Gebruikersnaam <input name="username" autocomplete="username" required></label><label>Wachtwoord <input name="password" type="password" autocomplete="current-password" required></label><button type="submit">Aanmelden</button></form></main></body></html>';
  }
}
