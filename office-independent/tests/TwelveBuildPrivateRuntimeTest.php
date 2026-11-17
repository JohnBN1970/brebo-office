<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildClient.php';
require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildAuthentication.php';
require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildHttpTransport.php';
require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildPrivateConfig.php';
require_once __DIR__ . '/../src/Integrations/TwelveBuild/TwelveBuildFactory.php';

use Brebo\Office\Integrations\TwelveBuild\TwelveBuildPrivateConfig;
use Brebo\Office\Integrations\TwelveBuild\TwelveBuildFactory;

$path = tempnam(sys_get_temp_dir(), '12build-config-');
if ($path === false) {
    throw new RuntimeException('Unable to create fixture.');
}
try {
    file_put_contents($path, '<?php return ' . var_export([
        'login' => 'fixture@example.invalid',
        'api_key' => 'fixture-key',
        'base_url' => 'https://example.invalid/api',
    ], true) . ';');
    chmod($path, 0600);
    $config = TwelveBuildPrivateConfig::load($path);
    $client = TwelveBuildFactory::fromPrivateConfig($path);
    if (!is_object($client)) {
        throw new RuntimeException('Connector factory failed.');
    }
    [$build, $parse] = TwelveBuildFactory::authCallbacks($config);
    $request = $build('fixture-key');
    if ($request['json']['login'] !== 'fixture@example.invalid' || $request['json']['apiKey'] !== 'fixture-key') {
        throw new RuntimeException('12Build authentication payload invalid.');
    }
    $token = $parse(['token' => 'fixture-token']);
    if ($token['token'] !== 'fixture-token') {
        throw new RuntimeException('Token parsing failed.');
    }
    try {
        $parse(['error' => 'unauthorized']);
        throw new RuntimeException('Missing token accepted.');
    } catch (UnexpectedValueException $expected) {
    }
} finally {
    unlink($path);
}
echo "12Build private runtime fixture tests passed\n";
