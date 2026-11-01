<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeActivationPage.php';
use Brebo\Office\Inzet\OnSiteOfficeActivationPage;
$assert = static function(bool $ok, string $message): void {
  if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
};
putenv('OFFICE_ONSITE_IOS_INSTALL_URL');
$page = OnSiteOfficeActivationPage::landing();
$assert(str_contains($page, 'iPhone / iOS'), 'iOS option missing');
$assert(str_contains($page, 'Android-testapp veilig downloaden'), 'Android secure download missing');
$assert(str_contains($page, 'method="post"'), 'Android must POST token, not expose it in URL');
$assert(str_contains($page, 'name="activation_token"'), 'Token field missing');
$assert(str_contains($page, 'downloadButton.disabled = false'), 'Valid token must enable download');
$assert(str_contains($page, 'brebo-onsite://activate?token='), 'iOS activation deep link changed');
$assert(!str_contains($page, 'play.google.com'), 'Must not invent Play Store link');
putenv('OFFICE_ONSITE_IOS_INSTALL_URL=https://example.org/ios');
$page = OnSiteOfficeActivationPage::landing();
$assert(str_contains($page, 'https://example.org/ios'), 'iOS URL missing');
$assert(str_contains($page, 'Android-testapp veilig downloaden'), 'Android download must remain available');
$assert(str_contains(OnSiteOfficeActivationPage::landingCsp(), "form-action 'self'"), 'CSP must restrict form to same origin');
echo "PASS: OnSite secure Android download landing\n";
