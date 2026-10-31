<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;
use RuntimeException;

/**
 * Private immutable attachment store. The root MUST be outside the webroot.
 * Callers persist the returned metadata in Office before advancing IMAP cursors.
 */
final class OfficeAttachmentVault
{
    public function __construct(private readonly string $root)
    {
        if ($root === '' || !is_dir($root) || is_link($root)
            || !is_writable($root)) {
            throw new InvalidArgumentException('Private attachment root unavailable.');
        }
    }

    /** @return array{sha256:string,bytes:int,path:string} */
    public function put(string $bytes): array
    {
        $size = strlen($bytes);
        if ($size < 1 || $size > 15 * 1024 * 1024) {
            throw new InvalidArgumentException('Invalid attachment size.');
        }
        $hash = hash('sha256', $bytes);
        $directory = rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . substr($hash, 0, 2);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Attachment directory unavailable.');
        }
        if (is_link($directory)) {
            throw new RuntimeException('Unsafe attachment directory.');
        }
        $path = $directory . DIRECTORY_SEPARATOR . $hash;
        if (is_link($path)) {
            throw new RuntimeException('Unsafe attachment target.');
        }
        if (!is_file($path)) {
            $temp = tempnam($directory, '.upload-');
            if ($temp === false) {
                throw new RuntimeException('Temporary attachment unavailable.');
            }
            try {
                chmod($temp, 0600);
                if (file_put_contents($temp, $bytes, LOCK_EX) !== $size
                    || !rename($temp, $path)) {
                    throw new RuntimeException('Attachment persistence failed.');
                }
            } finally {
                if (is_file($temp)) unlink($temp);
            }
        }
        if (hash_file('sha256', $path) !== $hash) {
            throw new RuntimeException('Attachment checksum mismatch.');
        }
        return ['sha256' => $hash, 'bytes' => $size, 'path' => $path];
    }
}
