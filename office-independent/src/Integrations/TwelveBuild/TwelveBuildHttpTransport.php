<?php
declare(strict_types=1);

namespace Brebo\Office\Integrations\TwelveBuild;

/**
 * HTTP transport for TwelveBuildClient. Never log request bodies, tokens or keys.
 * Returns decoded JSON and throws on non-2xx responses.
 */
final class TwelveBuildHttpTransport
{
    public function __invoke(string $url, array $request, array $headers): array
    {
        if (!str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('HTTPS is required.');
        }
        $method = strtoupper((string) ($request['method'] ?? 'GET'));
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new \InvalidArgumentException('Unsupported method.');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Unable to initialize 12Build request.');
        }
        $httpHeaders = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            if ($name !== 'X-Auth-Token' || !is_string($value) || preg_match('/[\r\n]/', $value)) {
                curl_close($curl);
                throw new \InvalidArgumentException('Unsupported request header.');
            }
            $httpHeaders[] = $name . ': ' . $value;
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if (array_key_exists('json', $request)) {
            if ($method !== 'POST') {
                curl_close($curl);
                throw new \InvalidArgumentException('JSON body requires POST.');
            }
            $payload = json_encode($request['json'], JSON_THROW_ON_ERROR);
            $options[CURLOPT_POSTFIELDS] = $payload;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_errno($curl);
        curl_close($curl);
        if ($body === false || $error !== 0) {
            throw new \RuntimeException('12Build network request failed.');
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('12Build HTTP status ' . $status . '.');
        }
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('12Build returned an unexpected JSON payload.');
        }
        return $decoded;
    }
}
