<?php
declare(strict_types=1);

namespace Brebo\Office\Integrations\TwelveBuild;

/**
 * Read-only invitation API boundary. Parsing and CRM persistence are separate.
 * The caller supplies the official invitation/list request payload.
 */
final class TwelveBuildInvitations
{
    private TwelveBuildClient $client;
    /** @var callable */
    private $authRequest;
    /** @var callable */
    private $authParse;

    public function __construct(TwelveBuildClient $client, callable $authRequest, callable $authParse)
    {
        $this->client = $client;
        $this->authRequest = $authRequest;
        $this->authParse = $authParse;
    }

    public function list(array $payload): array
    {
        return $this->client->request('/invitation/list', [
            'method' => 'POST',
            'json' => $payload,
        ], $this->authRequest, $this->authParse);
    }

    public function communication(string $id): array
    {
        return $this->client->request('/invitation/' . $this->safeId($id) . '/communication', [
            'method' => 'GET',
        ], $this->authRequest, $this->authParse);
    }

    public function projectDocuments(string $id): array
    {
        return $this->client->request('/invitation/' . $this->safeId($id) . '/projectDocuments', [
            'method' => 'GET',
        ], $this->authRequest, $this->authParse);
    }

    private function safeId(string $id): string
    {
        if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid 12Build invitation ID.');
        }
        return $id;
    }
}
