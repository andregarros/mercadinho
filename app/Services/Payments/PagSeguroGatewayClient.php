<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class PagSeguroGatewayClient extends AbstractGatewayClient
{
    public function gatewayName(): string
    {
        return 'pagseguro';
    }

    public function validateCredentials(array $credentials): array
    {
        $response = $this->http->request('GET', 'https://api.pagseguro.com/public-keys', [
            'headers' => [
                'Authorization' => 'Bearer ' . ($credentials['token'] ?? ''),
            ],
        ]);

        $this->ensureSuccess($response, 'Nao foi possivel validar o token do PagSeguro.');

        return [
            'connected' => true,
            'account_label' => (string) ($credentials['email'] ?? 'PagSeguro'),
        ];
    }

    public function createPixPayment(array $credentials, array $sale, array $user): array
    {
        $response = $this->http->request('POST', 'https://api.pagseguro.com/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials['token'],
                'x-idempotency-key' => $this->saleReference($sale),
            ],
            'json' => [
                'reference_id' => $this->saleReference($sale),
                'customer' => [
                    'name' => $user['name'] ?? 'Cliente Balcao',
                    'email' => $credentials['email'] ?? ($user['email'] ?? 'cliente@pix.local'),
                ],
                'items' => [[
                    'reference_id' => 'sale-' . (int) $sale['id'],
                    'name' => $this->saleDescription($sale),
                    'quantity' => 1,
                    'unit_amount' => (int) round(((float) $sale['total_amount']) * 100),
                ]],
                'qr_codes' => [[
                    'amount' => [
                        'value' => (int) round(((float) $sale['total_amount']) * 100),
                    ],
                ]],
                'notification_urls' => [
                    absolute_url('/payments/webhook/pagseguro'),
                ],
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Falha ao gerar PIX no PagSeguro.');
        $qrCode = $json['qr_codes'][0] ?? [];
        $texts = $qrCode['texts'][0] ?? [];
        $links = $qrCode['links'] ?? [];
        $imageLink = null;

        foreach ($links as $link) {
            if (($link['rel'] ?? '') === 'QRCODE.PNG') {
                $imageLink = $link['href'];
                break;
            }
        }

        return [
            'transaction_id' => (string) ($json['id'] ?? ''),
            'external_reference' => (string) ($json['reference_id'] ?? $this->saleReference($sale)),
            'status' => 'pending',
            'qr_code_image' => $imageLink,
            'pix_copy_paste' => $texts['text'] ?? null,
            'expires_at' => $qrCode['expiration_date'] ?? null,
            'provider_payload' => $json,
        ];
    }

    public function fetchPayment(array $credentials, string $transactionId): array
    {
        $response = $this->http->request('GET', 'https://api.pagseguro.com/orders/' . rawurlencode($transactionId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials['token'],
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Falha ao consultar pagamento no PagSeguro.');
        $charges = $json['charges'][0] ?? [];
        $status = (string) ($charges['status'] ?? $json['status'] ?? 'WAITING');

        return [
            'transaction_id' => (string) ($json['id'] ?? $transactionId),
            'status' => $this->normalizeStatus($status),
            'paid_at' => $charges['paid_at'] ?? null,
            'provider_payload' => $json,
        ];
    }

    public function extractWebhookReference(array $payload): ?array
    {
        $transactionId = $payload['id'] ?? null;
        if (!$transactionId) {
            return null;
        }

        return [
            'transaction_id' => (string) $transactionId,
            'external_reference' => $payload['reference_id'] ?? null,
        ];
    }
}
