<?php

declare(strict_types=1);

namespace App\Services\Http;

final class HttpClient
{
    public function request(string $method, string $url, array $options = []): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new \RuntimeException('Nao foi possivel iniciar a requisicao HTTP.');
        }

        $headers = $options['headers'] ?? [];
        $query = $options['query'] ?? [];
        $body = $options['json'] ?? null;

        if ($query !== []) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }

        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[] = is_int($key) ? $value : $key . ': ' . $value;
        }

        if ($body !== null) {
            $normalizedHeaders[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $normalizedHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => true,
        ]);

        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new \RuntimeException('Nao foi possivel serializar o payload HTTP.');
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }

        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Erro na requisicao HTTP: ' . $message);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $rawBody = substr($rawResponse, $headerSize);

        curl_close($ch);

        $decoded = json_decode($rawBody, true);

        return [
            'status' => $statusCode,
            'headers' => $this->parseHeaders($rawHeaders),
            'body' => $rawBody,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];

        foreach (preg_split("/\r\n|\n|\r/", trim($rawHeaders)) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }
}
