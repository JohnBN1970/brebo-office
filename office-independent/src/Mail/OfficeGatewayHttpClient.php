<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use Brebo\Mail\Contract\GatewayHttpClientInterface;
use Brebo\Mail\Domain\GatewayRequestSignature;
use RuntimeException;

/** Native Office transport for the isolated local gateway. */
final class OfficeGatewayHttpClient implements GatewayHttpClientInterface {
    public function post(string $path, array $payload): array {
        return $this->request('POST', $path, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function get(string $path): array {
        return $this->request('GET', $path, '');
    }

    private function request(string $method, string $path, string $body): array {
        $allowed = $method === 'GET' ? ['/v1/health'] : ['/v1/domains', '/v1/mailboxes', '/v1/aliases'];
        if (!in_array($path, $allowed, true)) {
            throw new RuntimeException('Onbekende gatewayroute.');
        }
        $url = rtrim((string) getenv('BREBO_MAIL_GATEWAY_URL'), '/');
        $key = (string) getenv('BREBO_MAIL_GATEWAY_KEY_ID');
        $secret = (string) getenv('BREBO_MAIL_GATEWAY_SECRET');
        if ($url !== 'http://127.0.0.1:8088' || $key === '' || $secret === '') {
            throw new RuntimeException('Lokale mailgatewayconfiguratie ontbreekt.');
        }
        $signature = GatewayRequestSignature::sign($key, $secret, time(), $method, $path, $body);
        $headers = [];
        foreach ($signature->headers() + ['Accept' => 'application/json', 'Content-Type' => 'application/json'] as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        $ch = curl_init($url . $path);
        if ($ch === false) {
            throw new RuntimeException('Mailgatewayverbinding niet beschikbaar.');
        }
        try {
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROXY => '',
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 10,
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if (!is_string($response) || $status < 200 || $status >= 300) {
                throw new RuntimeException('Mailgatewayverzoek mislukt (HTTP ' . $status . ').');
            }
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new RuntimeException('Ongeldig mailgatewayantwoord.');
            }
            return $decoded;
        } finally {
            curl_close($ch);
        }
    }
}
