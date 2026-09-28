<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class MercadoPagoGatewayClient extends AbstractGatewayClient
{
    public function gatewayName(): string
    {
        return 'mercado_pago';
    }

    public function validateCredentials(array $credentials): array
    {
        $response = $this->http->request('GET', 'https://api.mercadopago.com/users/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . ($credentials['access_token'] ?? ''),
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Nao foi possivel validar o Access Token do Mercado Pago.');

        return [
            'connected' => true,
            'account_label' => (string) ($json['nickname'] ?? $json['email'] ?? 'Mercado Pago'),
        ];
    }

    public function createPixPayment(array $credentials, array $sale, array $user): array
    {
        $response = $this->http->request('POST', 'https://api.mercadopago.com/v1/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials['access_token'],
                'X-Idempotency-Key' => $this->saleReference($sale),
            ],
            'json' => [
                'transaction_amount' => (float) $sale['total_amount'],
                'description' => $this->saleDescription($sale),
                'payment_method_id' => 'pix',
                'external_reference' => $this->saleReference($sale),
                'payer' => [
                    'email' => $user['email'] ?? ('cliente+' . $sale['id'] . '@pix.local'),
                    'first_name' => $user['name'] ?? 'Cliente',
                ],
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Falha ao gerar PIX no Mercado Pago.');
        $point = $json['point_of_interaction']['transaction_data'] ?? [];

        return [
            'transaction_id' => (string) ($json['id'] ?? ''),
            'external_reference' => (string) ($json['external_reference'] ?? $this->saleReference($sale)),
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'pending')),
            'qr_code_image' => isset($point['qr_code_base64']) ? 'data:image/png;base64,' . $point['qr_code_base64'] : null,
            'pix_copy_paste' => $point['qr_code'] ?? null,
            'expires_at' => $json['date_of_expiration'] ?? null,
            'provider_payload' => $json,
        ];
    }

    public function fetchPayment(array $credentials, string $transactionId): array
    {
        $response = $this->http->request('GET', 'https://api.mercadopago.com/v1/payments/' . rawurlencode($transactionId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials['access_token'],
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Falha ao consultar pagamento no Mercado Pago.');

        return [
            'transaction_id' => (string) ($json['id'] ?? $transactionId),
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'pending')),
            'paid_at' => $json['date_approved'] ?? null,
            'provider_payload' => $json,
        ];
    }

    public function extractWebhookReference(array $payload): ?array
    {
        $transactionId = $payload['data']['id']
            ?? $payload['id']
            ?? $payload['resource']['id']
            ?? null;

        if (!$transactionId && !empty($payload['resource']) && is_string($payload['resource'])) {
            $path = parse_url($payload['resource'], PHP_URL_PATH) ?: '';
            $transactionId = $path !== '' ? basename($path) : null;
        }

        if (!$transactionId) {
            return null;
        }

        return [
            'transaction_id' => (string) $transactionId,
            'external_reference' => $payload['external_reference'] ?? null,
        ];
    }
}
