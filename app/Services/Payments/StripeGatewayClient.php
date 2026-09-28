<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class StripeGatewayClient extends AbstractGatewayClient
{
    public function gatewayName(): string
    {
        return 'stripe';
    }

    public function validateCredentials(array $credentials): array
    {
        $response = $this->http->request('GET', 'https://api.stripe.com/v1/account', [
            'headers' => [
                'Authorization' => 'Bearer ' . ($credentials['secret_key'] ?? ''),
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Nao foi possivel validar a Secret Key da Stripe.');

        return [
            'connected' => true,
            'account_label' => (string) ($json['business_profile']['name'] ?? $json['email'] ?? 'Stripe'),
        ];
    }

    public function createPixPayment(array $credentials, array $sale, array $user): array
    {
        throw new \RuntimeException('A cobranca PIX da Stripe exige um fluxo de confirmacao adicional. Integre esse gateway apenas se precisar de operacao internacional.');
    }

    public function fetchPayment(array $credentials, string $transactionId): array
    {
        $response = $this->http->request('GET', 'https://api.stripe.com/v1/payment_intents/' . rawurlencode($transactionId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials['secret_key'],
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Falha ao consultar pagamento na Stripe.');

        return [
            'transaction_id' => (string) ($json['id'] ?? $transactionId),
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'requires_action')),
            'paid_at' => isset($json['created']) ? date('Y-m-d H:i:s', (int) $json['created']) : null,
            'provider_payload' => $json,
        ];
    }

    public function extractWebhookReference(array $payload): ?array
    {
        $object = $payload['data']['object'] ?? [];
        $transactionId = $object['id'] ?? null;
        if (!$transactionId) {
            return null;
        }

        return [
            'transaction_id' => (string) $transactionId,
            'external_reference' => $object['metadata']['external_reference'] ?? null,
        ];
    }
}
