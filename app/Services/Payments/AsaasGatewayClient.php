<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class AsaasGatewayClient extends AbstractGatewayClient
{
    public function gatewayName(): string
    {
        return 'asaas';
    }

    public function validateCredentials(array $credentials): array
    {
        $baseUrl = $this->baseUrl($credentials);
        $response = $this->http->request('GET', $baseUrl . '/v3/myAccount/status', [
            'headers' => [
                'access_token' => $credentials['api_key'] ?? '',
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Nao foi possivel validar a API Key do Asaas.');

        return [
            'connected' => true,
            'account_label' => (string) ($json['commercialInfo']['company'] ?? 'Asaas'),
        ];
    }

    public function createPixPayment(array $credentials, array $sale, array $user): array
    {
        $baseUrl = $this->baseUrl($credentials);
        $headers = ['access_token' => $credentials['api_key']];

        $customerResponse = $this->http->request('POST', $baseUrl . '/v3/customers', [
            'headers' => $headers,
            'json' => [
                'name' => $user['name'] ?? 'Cliente Balcao',
                'email' => $user['email'] ?? null,
                'externalReference' => 'user-' . (int) $sale['user_id'],
                'notificationDisabled' => true,
            ],
        ]);

        $customer = $this->ensureSuccess($customerResponse, 'Falha ao preparar cliente no Asaas.');

        $paymentResponse = $this->http->request('POST', $baseUrl . '/v3/payments', [
            'headers' => $headers,
            'json' => [
                'customer' => $customer['id'] ?? null,
                'billingType' => 'PIX',
                'value' => (float) $sale['total_amount'],
                'dueDate' => date('Y-m-d'),
                'description' => $this->saleDescription($sale),
                'externalReference' => $this->saleReference($sale),
            ],
        ]);

        $payment = $this->ensureSuccess($paymentResponse, 'Falha ao gerar PIX no Asaas.');

        $qrResponse = $this->http->request('GET', $baseUrl . '/v3/payments/' . rawurlencode((string) $payment['id']) . '/pixQrCode', [
            'headers' => $headers,
        ]);

        $qrData = $this->ensureSuccess($qrResponse, 'Falha ao buscar QR Code no Asaas.');

        return [
            'transaction_id' => (string) ($payment['id'] ?? ''),
            'external_reference' => (string) ($payment['externalReference'] ?? $this->saleReference($sale)),
            'status' => $this->normalizeStatus((string) ($payment['status'] ?? 'PENDING')),
            'qr_code_image' => isset($qrData['encodedImage']) ? 'data:image/png;base64,' . $qrData['encodedImage'] : null,
            'pix_copy_paste' => $qrData['payload'] ?? null,
            'expires_at' => $qrData['expirationDate'] ?? null,
            'provider_payload' => [
                'payment' => $payment,
                'pixQrCode' => $qrData,
            ],
        ];
    }

    public function fetchPayment(array $credentials, string $transactionId): array
    {
        $response = $this->http->request('GET', $this->baseUrl($credentials) . '/v3/payments/' . rawurlencode($transactionId), [
            'headers' => [
                'access_token' => $credentials['api_key'],
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Falha ao consultar pagamento no Asaas.');

        return [
            'transaction_id' => (string) ($json['id'] ?? $transactionId),
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'PENDING')),
            'paid_at' => $json['clientPaymentDate'] ?? $json['confirmedDate'] ?? null,
            'provider_payload' => $json,
        ];
    }

    public function extractWebhookReference(array $payload): ?array
    {
        $payment = $payload['payment'] ?? [];
        $transactionId = $payment['id'] ?? null;
        if (!$transactionId) {
            return null;
        }

        return [
            'transaction_id' => (string) $transactionId,
            'external_reference' => $payment['externalReference'] ?? null,
        ];
    }

    private function baseUrl(array $credentials): string
    {
        $environment = $credentials['environment'] ?? 'production';
        return $environment === 'sandbox'
            ? 'https://api-sandbox.asaas.com'
            : 'https://api.asaas.com';
    }
}
