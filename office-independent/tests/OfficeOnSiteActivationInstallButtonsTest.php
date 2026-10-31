<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeActivationPage.php';

use Brebo\Office\Inzet\OnSiteOfficeActivationPage;

$assert = static function (bool $ok, string $message): void {
  if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
};

putenv('OFFICE_ONSITE_IOS_INSTALL_URL');
putenv('OFFICE_ONSITE_ANDROID_INSTALL_URL');
$page = OnSiteOfficeActivationPage::landing();
$assert(str_contains($page, 'iPhone / iOS'), 'iOS choice missing');
$assert(str_contains($page, 'Android'), 'Android choice missing');
$assert(substr_count($page, 'Downloadlink volgt') === 2, 'Missing links must be unavailable');
$assert(str_contains($page, 'brebo-onsite://activate?token='), 'Activation deep link missing');
$assert(!str_contains($page, 'apps.apple.com'), 'Must not invent App Store URL');
$assert(!str_contains($page, 'play.google.com'), 'Must not invent Play Store URL');

putenv('OFFICE_ONSITE_IOS_INSTALL_URL=https://example.org/ios');
putenv('OFFICE_ONSITE_ANDROID_INSTALL_URL=https://example.org/android');
$page = OnSiteOfficeActivationPage::landing();
$assert(str_contains($page, 'https://example.org/ios'), 'Configured iOS URL missing');
$assert(str_contains($page, 'https://example.org/android'), 'Configured Android URL missing');
$assert(!str_contains($page, 'Downloadlink volgt'), 'Configured links should be clickable');

putenv('OFFICE_ONSITE_ANDROID_INSTALL_URL=javascript:alert(1)');
$page = OnSiteOfficeActivationPage::landing();
$assert(str_contains($page, 'Downloadlink volgt'), 'Unsafe Android URL should not be linked');
$assert(!str_contains($page, 'href="javascript:'), 'Unsafe URL in href');

echo "PASS: OnSite activation platform install buttons\n";
