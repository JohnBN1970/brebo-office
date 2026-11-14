<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

/**
 * Provider-neutral immutable original binary store.
 * The caller owns authorization, upload validation and document registration.
 */
final class OfficePrivateFileStorage
{
    private string $root;

    public function __construct(string $root)
    {
        if ($root === '' || !str_starts_with($root, '/')) {
            throw new \InvalidArgumentException('absolute_storage_root_required');
        }
        $resolved = realpath($root);
        if ($resolved === false || !is_dir($resolved) || is_link($root)) {
            throw new \RuntimeException('private_storage_root_unavailable');
        }
        $this->root = rtrim($resolved, '/');
        $webroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: null;
        if ($webroot !== null && ($this->root === $webroot || str_starts_with($this->root, rtrim($webroot, '/') . '/'))) {
            throw new \RuntimeException('storage_must_be_outside_webroot');
        }
    }

    /** @return array{storage_provider:string,storage_key:string,sha256:string,size:int} */
    public function put(string $sourcePath): array
    {
        if (!is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath)) {
            throw new \InvalidArgumentException('invalid_source_file');
        }
        $hash = hash_file('sha256', $sourcePath);
        if (!is_string($hash)) {
            throw new \RuntimeException('hash_failed');
        }
        $key = 'originals/' . substr($hash, 0, 2) . '/' . $hash;
        $directory = $this->root . '/originals/' . substr($hash, 0, 2);
        $this->ensureDirectory($this->root . '/originals');
        $this->ensureDirectory($directory);
        $target = $this->root . '/' . $key;
        if (is_link($target)) {
            throw new \RuntimeException('unsafe_storage_target');
        }
        if (!is_file($target)) {
            $temporary = tempnam($directory, '.incoming-');
            if ($temporary === false) {
                throw new \RuntimeException('temporary_file_failed');
            }
            try {
                chmod($temporary, 0600);
                $input = fopen($sourcePath, 'rb');
                $output = fopen($temporary, 'wb');
                if ($input === false || $output === false) {
                    throw new \RuntimeException('open_failed');
                }
                try {
                    if (stream_copy_to_stream($input, $output) === false) {
                        throw new \RuntimeException('copy_failed');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                if (!hash_equals($hash, (string) hash_file('sha256', $temporary))) {
                    throw new \RuntimeException('integrity_check_failed');
                }
                // Atomic hard-link avoids overwriting an existing immutable original.
                if (!@link($temporary, $target) && !is_file($target)) {
                    throw new \RuntimeException('storage_commit_failed');
                }
            } finally {
                @unlink($temporary);
            }
        }
        if (!is_file($target) || is_link($target) || !hash_equals($hash, (string) hash_file('sha256', $target))) {
            throw new \RuntimeException('stored_original_integrity_failed');
        }
        return ['storage_provider' => 'office_private', 'storage_key' => $key,
            'sha256' => $hash, 'size' => (int) filesize($target)];
    }

    /** Resolve only after the Office authorization check has passed. */
    public function authorizedPath(string $key): string
    {
        if (!preg_match('#^originals/([a-f0-9]{2})/([a-f0-9]{64})$#D', $key, $matches)
            || $matches[1] !== substr($matches[2], 0, 2)) {
            throw new \InvalidArgumentException('invalid_storage_key');
        }
        $path = $this->root . '/' . $key;
        $resolved = realpath($path);
        if ($resolved === false || !str_starts_with($resolved, $this->root . '/originals/')
            || !is_file($resolved) || !hash_equals($matches[2], (string) hash_file('sha256', $resolved))) {
            throw new \RuntimeException('original_unavailable');
        }
        return $resolved;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
            throw new \RuntimeException('storage_directory_creation_failed');
        }
        if (is_link($directory)) {
            throw new \RuntimeException('unsafe_storage_directory');
        }
        @chmod($directory, 0700);
    }
}
