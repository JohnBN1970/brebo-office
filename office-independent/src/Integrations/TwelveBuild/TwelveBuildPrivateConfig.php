<?php
declare(strict_types=1);

namespace Brebo\Office\Integrations\TwelveBuild;

/**
 * Read 12Build credentials from a private Hostinger config file.
 * Never place this file in the deployment tree, public webroot or repository.
 */
final class TwelveBuildPrivateConfig
{
    public static function load(string $path): array
    {
        if ($path === '' || !str_starts_with($path, '/') || !is_file($path) || is_link($path)) {
            throw new \RuntimeException('12Build private configuration unavailable.');
        }
        $permissions = fileperms($path);
        if ($permissions === false || ($permissions & 0007) !== 0) {
            throw new \RuntimeException('12Build private configuration permissions are unsafe.');
        }
        $config = require $path;
        if (!is_array($config)) {
            throw new \RuntimeException('Invalid 12Build private configuration.');
        }
        foreach (['login', 'api_key', 'base_url'] as $field) {
            if (!isset($config[$field]) || !is_string($config[$field]) || trim($config[$field]) === '') {
                throw new \RuntimeException('Missing 12Build private configuration field: ' . $field);
            }
        }
        if (!str_starts_with($config['base_url'], 'https://')) {
            throw new \RuntimeException('12Build API base URL must use HTTPS.');
        }
        return $config;
    }
}
