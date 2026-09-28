<?php

declare(strict_types=1);

namespace App\Services\Payments;

interface GatewayClientInterface
{
    public function gatewayName(): string;

    public function validateCredentials(array $credentials): array;

    public function createPixPayment(array $credentials, array $sale, array $user): array;

    public function fetchPayment(array $credentials, string $transactionId): array;

    public function extractWebhookReference(array $payload): ?array;
}
