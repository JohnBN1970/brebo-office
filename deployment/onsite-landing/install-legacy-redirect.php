<?php
declare(strict_types=1);
// Compatibility for already-issued invitations. HTTP redirects retain the
// original #activation fragment without ever sending its token to the server.
if (PHP_SAPI !== 'cli') exit(1);
$root=realpath(getenv('HOME').'/domains/brebobv.nl/public_html');
$path=$root.'/office-test/index.php';
if (!$root || is_link($path) || realpath($path)!==$path) throw new RuntimeException('Unexpected Office-test router path');
$source=file_get_contents($path);
$block= <<<'CODE'

// OnSite invitation landing v1: preserve existing fragment-based invitations.
if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','HEAD'], true)
    && parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/office-test/inzet/onsite/activatie-uitgeven') {
    header('Cache-Control: private, no-store');
    header('Referrer-Policy: no-referrer');
    header('Location: /onsite/', true, 302);
    exit;
}

CODE;
if (str_contains($source,'// OnSite invitation landing v1:')) {
    if (!str_contains($source,trim($block))) throw new RuntimeException('Existing redirect differs');
    echo "LEGACY_INVITATION_REDIRECT_ALREADY_INSTALLED\n";
    exit;
}
if (!preg_match('/\A<\?php\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $source, $match)) throw new RuntimeException('Router preamble differs; refusing edit');
$backup=__DIR__.'/office-test-index.before.php';
if (file_exists($backup)) throw new RuntimeException('Backup already exists');
file_put_contents($backup,$source,LOCK_EX);chmod($backup,0600);
$next=__DIR__.'/office-test-index.next.php';
file_put_contents($next,$match[0]."\n".$block.substr($source,strlen($match[0])),LOCK_EX);chmod($next,0644);
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($next),$output,$status);
if ($status!==0) throw new RuntimeException('Updated router syntax check failed');
if (hash_file('sha256',$path)!==hash('sha256',$source)) throw new RuntimeException('Router changed during installation');
if (!rename($next,$path)) throw new RuntimeException('Router installation failed');
if (function_exists('opcache_invalidate')) opcache_invalidate($path,true);
echo "LEGACY_INVITATIONS_NOW_USE_PROTECTED_LANDING\n";
