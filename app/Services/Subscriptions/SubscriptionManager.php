<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\Http\HttpClient;

final class SubscriptionManager
{
    private const PIX_EXPIRATION_MINUTES = 15;

    private SubscriptionPayment $subscriptionModel;
    private User $userModel;
    private HttpClient $http;
    private AppSettingsService $settings;

    public function __construct()
    {
        $this->subscriptionModel = new SubscriptionPayment();
        $this->userModel = new User();
        $this->http = new HttpClient();
        $this->settings = new AppSettingsService();
    }

    public function currentStateForUser(int $userId): array
    {
        $user = $this->userModel->refreshPlanStatus($userId);
        if (!$user) {
            throw new \RuntimeException('Usuario nao encontrado.');
        }

        $this->expireOutdatedPendingPayment($userId);

        return [
            'user' => $user,
            'amount' => $this->settings->subscriptionAmount(),
            'plan_days' => $this->settings->subscriptionDays(),
            'trial_days' => $this->settings->trialDays(),
            'pix_expiration_minutes' => self::PIX_EXPIRATION_MINUTES,
            'latest_payment' => $this->subscriptionModel->findLatestByUser($userId),
            'pending_payment' => $this->subscriptionModel->findPendingByUser($userId),
            'gateway' => (string) config('app.subscription_gateway', 'mercado_pago'),
        ];
    }

    public function createOrGetPendingPayment(int $userId): array
    {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            throw new \RuntimeException('Usuario nao encontrado.');
        }

        $pending = $this->expireOutdatedPendingPayment($userId);
        if ($pending) {
            return $pending;
        }

        $gateway = (string) config('app.subscription_gateway', 'mercado_pago');
        if ($gateway !== 'mercado_pago') {
            throw new \RuntimeException('Gateway de assinatura nao suportado nesta versao.');
        }

        $accessToken = $this->settings->subscriptionAccessToken();
        if ($accessToken === '') {
            throw new \RuntimeException('O admin precisa configurar o token do Mercado Pago para cobrar a assinatura via PIX.');
        }

        $planDays = $this->settings->subscriptionDays();
        $amount = $this->settings->subscriptionAmount();
        $externalReference = $this->subscriptionReference($userId);
        $expiresAt = $this->mercadoPagoExpirationTimestamp();

        $response = $this->http->request('POST', 'https://api.mercadopago.com/v1/payments', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Idempotency-Key' => $externalReference,
            ],
            'json' => [
                'transaction_amount' => $amount,
                'description' => sprintf('Assinatura %s - %d dias', app_name(), $planDays),
                'payment_method_id' => 'pix',
                'external_reference' => $externalReference,
                'date_of_expiration' => $expiresAt,
                'notification_url' => absolute_url('/subscription/webhook/mercado-pago'),
                'payer' => [
                    'email' => $user['email'],
                    'first_name' => $user['name'],
                ],
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Falha ao gerar o PIX da assinatura.');
        $point = $json['point_of_interaction']['transaction_data'] ?? [];

        $this->subscriptionModel->create([
            'user_id' => $userId,
            'gateway' => $gateway,
            'transaction_id' => (string) ($json['id'] ?? ''),
            'external_reference' => (string) ($json['external_reference'] ?? $externalReference),
            'amount' => $amount,
            'plan_days' => $planDays,
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'pending')),
            'qr_code_image' => isset($point['qr_code_base64']) ? 'data:image/png;base64,' . $point['qr_code_base64'] : null,
            'pix_copy_paste' => $point['qr_code'] ?? null,
            'provider_payload' => $json,
            'paid_at' => null,
            'expires_at' => $this->normalizeDateTime($json['date_of_expiration'] ?? null),
        ]);

        $created = $this->subscriptionModel->findPendingByUser($userId)
            ?? $this->subscriptionModel->findLatestByUser($userId);

        if (!$created) {
            throw new \RuntimeException('Nao foi possivel recuperar a cobranca da assinatura.');
        }

        if (($created['status'] ?? 'pending') === 'paid') {
            $this->activateUserPlan((int) $created['user_id'], (int) $created['plan_days']);
        }

        return $created;
    }

    public function refreshPaymentStatus(int $userId): array
    {
        $payment = $this->expireOutdatedPendingPayment($userId)
            ?? $this->subscriptionModel->findLatestByUser($userId);

        if (!$payment) {
            throw new \RuntimeException('Nenhuma cobranca de assinatura encontrada.');
        }

        if (($payment['status'] ?? 'pending') !== 'pending') {
            return [
                'payment' => $payment,
                'user' => $this->userModel->refreshPlanStatus($userId),
            ];
        }

        $remote = $this->fetchMercadoPagoPayment((string) $payment['transaction_id']);
        $paidAt = $this->normalizeDateTime($remote['paid_at'] ?? null);

        $this->subscriptionModel->updateStatusAndExpiry(
            (int) $payment['id'],
            (string) $remote['status'],
            $remote['expires_at'] ?? null,
            $paidAt,
            $remote['provider_payload'] ?? null
        );

        if (($remote['status'] ?? 'pending') === 'paid') {
            $this->activateUserPlan((int) $payment['user_id'], (int) $payment['plan_days']);
        }

        return [
            'payment' => $this->subscriptionModel->findByGatewayAndTransaction((string) $payment['gateway'], (string) $payment['transaction_id']) ?? $payment,
            'user' => $this->userModel->refreshPlanStatus($userId),
        ];
    }

    public function reconcileWebhook(string $gatewayName, array $payload): void
    {
        if ($gatewayName !== 'mercado_pago') {
            return;
        }

        $transactionId = $payload['data']['id']
            ?? $payload['id']
            ?? $payload['resource']['id']
            ?? null;

        if (!$transactionId && !empty($payload['resource']) && is_string($payload['resource'])) {
            $path = parse_url($payload['resource'], PHP_URL_PATH) ?: '';
            $transactionId = $path !== '' ? basename($path) : null;
        }

        $payment = null;
        if ($transactionId) {
            $payment = $this->subscriptionModel->findByGatewayAndTransaction($gatewayName, (string) $transactionId);
        }

        if (!$payment && !empty($payload['external_reference'])) {
            $payment = $this->subscriptionModel->findByExternalReference($gatewayName, (string) $payload['external_reference']);
        }

        if (!$payment) {
            return;
        }

        $remote = $this->fetchMercadoPagoPayment((string) $payment['transaction_id']);
        $paidAt = $this->normalizeDateTime($remote['paid_at'] ?? null);

        $this->subscriptionModel->updateStatusAndExpiry(
            (int) $payment['id'],
            (string) $remote['status'],
            $remote['expires_at'] ?? null,
            $paidAt,
            $remote['provider_payload'] ?? null
        );

        if (($remote['status'] ?? 'pending') === 'paid') {
            $this->activateUserPlan((int) $payment['user_id'], (int) $payment['plan_days']);
        }
    }

    private function fetchMercadoPagoPayment(string $transactionId): array
    {
        $response = $this->http->request('GET', 'https://api.mercadopago.com/v1/payments/' . rawurlencode($transactionId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->settings->subscriptionAccessToken(),
            ],
        ]);

        $json = $this->ensureSuccess($response, 'Falha ao consultar a assinatura no Mercado Pago.');
        $expiresAt = $this->normalizeDateTime($json['date_of_expiration'] ?? null);
        $status = $this->normalizeStatus((string) ($json['status'] ?? 'pending'));

        if ($status === 'cancelled' && !$this->isExpired($expiresAt) && empty($json['date_approved'])) {
            $status = 'pending';
        }

        return [
            'status' => $status,
            'paid_at' => $json['date_approved'] ?? null,
            'expires_at' => $expiresAt,
            'provider_payload' => $json,
        ];
    }

    private function expireOutdatedPendingPayment(int $userId): ?array
    {
        $pending = $this->subscriptionModel->findPendingByUser($userId);
        if (!$pending) {
            return null;
        }

        $currentAmount = $this->settings->subscriptionAmount();
        $currentPlanDays = $this->settings->subscriptionDays();

        $amountChanged = round((float) ($pending['amount'] ?? 0), 2) !== round($currentAmount, 2);
        $planChanged = (int) ($pending['plan_days'] ?? 0) !== $currentPlanDays;
        $expired = $this->isExpired($pending['expires_at'] ?? null);

        if ($amountChanged || $planChanged || $expired) {
            $this->subscriptionModel->updateStatusAndExpiry(
                (int) $pending['id'],
                'expired',
                $expired ? ($pending['expires_at'] ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s')
            );

            return null;
        }

        return $pending;
    }

    private function activateUserPlan(int $userId, int $planDays): void
    {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return;
        }

        $baseTimestamp = time();
        if (!empty($user['plan_expires_at'])) {
            $currentExpiresAt = strtotime((string) $user['plan_expires_at']);
            if ($currentExpiresAt !== false && $currentExpiresAt > $baseTimestamp) {
                $baseTimestamp = $currentExpiresAt;
            }
        }

        $nextExpiresAt = date('Y-m-d H:i:s', strtotime('+' . $planDays . ' days', $baseTimestamp));
        $this->userModel->updatePlan($userId, 'active', $nextExpiresAt);
    }

    private function subscriptionReference(int $userId): string
    {
        return sprintf('subscription-user-%d-%s', $userId, date('YmdHis'));
    }

    private function ensureSuccess(array $response, string $fallbackMessage): array
    {
        if ($response['status'] >= 200 && $response['status'] < 300 && is_array($response['json'])) {
            return $response['json'];
        }

        $message = $fallbackMessage;
        if (is_array($response['json'])) {
            $message = (string) ($response['json']['message']
                ?? $response['json']['error']
                ?? $response['json']['cause'][0]['description']
                ?? $fallbackMessage);
        }

        throw new \RuntimeException($message);
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'approved', 'paid' => 'paid',
            'cancelled', 'canceled' => 'cancelled',
            'expired' => 'expired',
            'failed', 'refused' => 'failed',
            default => 'pending',
        };
    }

    private function normalizeDateTime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function isExpired(?string $expiresAt): bool
    {
        if (!$expiresAt) {
            return false;
        }

        $timestamp = strtotime($expiresAt);
        return $timestamp !== false && $timestamp <= time();
    }

    private function mercadoPagoExpirationTimestamp(): string
    {
        $timezone = new \DateTimeZone((string) config('app.timezone', 'America/Sao_Paulo'));
        $expiresAt = new \DateTimeImmutable('now', $timezone);
        $expiresAt = $expiresAt->modify('+' . self::PIX_EXPIRATION_MINUTES . ' minutes');

        return $expiresAt->format('Y-m-d\TH:i:s.000P');
    }
}
