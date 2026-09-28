<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\Http\HttpClient;

abstract class AbstractGatewayClient implements GatewayClientInterface
{
    public function __construct(protected HttpClient $http)
    {
    }

    protected function ensureSuccess(array $response, string $fallbackMessage): array
    {
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return $response['json'] ?? [];
        }

        $message = $fallbackMessage;
        if (is_array($response['json'])) {
            $message = (string) ($response['json']['message']
                ?? $response['json']['error']
                ?? $response['json']['errors'][0]['message']
                ?? $fallbackMessage);
        }

        throw new \RuntimeException($message);
    }

    protected function saleDescription(array $sale): string
    {
        return 'Venda #' . (int) $sale['id'] . ' - ' . app_name();
    }

    protected function saleReference(array $sale): string
    {
        return 'sale-' . (int) $sale['id'] . '-user-' . (int) $sale['user_id'];
    }

    protected function normalizeStatus(string $status): string
    {
        $normalized = strtolower($status);

        return match ($normalized) {
            'approved', 'paid', 'succeeded', 'received', 'confirmed', 'completed' => 'paid',
            'cancelled', 'canceled' => 'cancelled',
            'expired' => 'expired',
            'failed', 'refused' => 'failed',
            default => 'pending',
        };
    }
}
