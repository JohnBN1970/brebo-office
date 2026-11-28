<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

/** Worker-only provider. Resolver must read secrets from protected external storage. */
final class OfficeDossierArchiveKeyring
{
    public function __construct(private readonly string $activeVersion, private readonly \Closure $resolve)
    {
        self::validateVersion($activeVersion);
    }

    public function activeVersion(): string
    {
        return $this->activeVersion;
    }

    public function keyFor(string $version): string
    {
        self::validateVersion($version);
        $encoded=($this->resolve)($version);
        if (!is_string($encoded)) throw new \RuntimeException('Archive key unavailable');
        $key=base64_decode($encoded,true);
        if ($key===false || strlen($key)!==32 || base64_encode($key)!==$encoded) throw new \RuntimeException('Invalid archive key');
        return $key;
    }

    private static function validateVersion(string $version): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$version)) throw new \InvalidArgumentException('Invalid archive key version');
    }
}
