<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Sale;
use App\Models\User;

final class PaymentManager
{
    private PaymentGateway $gatewayModel;
    private Payment $paymentModel;
    private Sale $saleModel;
    private User $userModel;

    public function __construct()
    {
        $this->gatewayModel = new PaymentGateway();
        $this->paymentModel = new Payment();
        $this->saleModel = new Sale();
        $this->userModel = new User();
    }

    public function gatewayOptions(): array
    {
        return GatewayFactory::supportedGateways();
    }

    public function gatewaysForUser(int $userId): array
    {
        return $this->gatewayModel->listByUser($userId);
    }

    public function connectGateway(int $userId, string $gatewayName, array $credentials): array
    {
        try {
            $client = GatewayFactory::make($gatewayName);
            $validation = $client->validateCredentials($credentials);

            $this->gatewayModel->saveGateway($userId, $gatewayName, $credentials, true, null);

            return $validation;
        } catch (\Throwable $exception) {
            $this->gatewayModel->saveGateway($userId, $gatewayName, $credentials, false, $exception->getMessage());
            throw $exception;
        }
    }

    public function disconnectGateway(int $userId, string $gatewayName): void
    {
        $this->gatewayModel->disconnect($userId, $gatewayName);
    }

    public function createPixPaymentForSale(int $saleId, int $userId): array
    {
        $gateway = $this->gatewayModel->findActiveByUser($userId);
        if (!$gateway) {
            throw new \RuntimeException('Configure seu PIX para receber pagamentos.');
        }

        $sale = $this->saleModel->findWithItems($saleId, $userId);
        $user = $this->userModel->findById($userId);

        if (!$sale || !$user) {
            throw new \RuntimeException('Venda ou usuario nao encontrado para gerar o PIX.');
        }

        $existing = $this->paymentModel->findBySaleId($saleId, $userId);
        if ($existing) {
            return $existing;
        }

        $client = GatewayFactory::make($gateway['gateway_name']);
        $pix = $client->createPixPayment($gateway['credentials'], $sale, $user);

        $paymentId = $this->paymentModel->create([
            'user_id' => $userId,
            'sale_id' => $saleId,
            'gateway' => $gateway['gateway_name'],
            'transaction_id' => $pix['transaction_id'],
            'external_reference' => $pix['external_reference'] ?? null,
            'amount' => $sale['total_amount'],
            'status' => $pix['status'] ?? 'pending',
            'qr_code_image' => $pix['qr_code_image'] ?? null,
            'pix_copy_paste' => $pix['pix_copy_paste'] ?? null,
            'provider_payload' => $pix['provider_payload'] ?? null,
            'paid_at' => $pix['status'] === 'paid' ? date('Y-m-d H:i:s') : null,
            'expires_at' => $this->normalizeDateTime($pix['expires_at'] ?? null),
        ]);

        if (($pix['status'] ?? 'pending') === 'paid') {
            $this->saleModel->markAsPaid($saleId, $pix['transaction_id'], date('Y-m-d H:i:s'));
        }

        $created = $this->paymentModel->findBySaleId($saleId, $userId);
        if (!$created) {
            throw new \RuntimeException('Nao foi possivel recuperar o pagamento PIX criado.');
        }

        $created['id'] = $paymentId;
        return $created;
    }

    public function reconcileWebhook(string $gatewayName, array $payload): void
    {
        $client = GatewayFactory::make($gatewayName);
        $reference = $client->extractWebhookReference($payload);

        if (!$reference) {
            return;
        }

        $payment = null;
        if (!empty($reference['transaction_id'])) {
            $payment = $this->paymentModel->findByGatewayAndTransaction($gatewayName, $reference['transaction_id']);
        }

        if (!$payment && !empty($reference['external_reference'])) {
            $payment = $this->paymentModel->findByExternalReference($gatewayName, $reference['external_reference']);
        }

        if (!$payment) {
            return;
        }

        $gateway = $this->gatewayModel->findByUserAndGateway((int) $payment['user_id'], $gatewayName);
        if (!$gateway) {
            return;
        }

        $remote = $client->fetchPayment($gateway['credentials'], (string) $payment['transaction_id']);

        $this->paymentModel->updateStatus(
            (int) $payment['id'],
            $remote['status'],
            $this->normalizeDateTime($remote['paid_at'] ?? null),
            $remote['provider_payload'] ?? null
        );

        if ($remote['status'] === 'paid') {
            $this->saleModel->markAsPaid((int) $payment['sale_id'], (string) $payment['transaction_id'], $this->normalizeDateTime($remote['paid_at'] ?? date('Y-m-d H:i:s')));
        }
    }

    public function refreshSalePaymentStatus(int $saleId, int $userId): ?array
    {
        $payment = $this->paymentModel->findBySaleId($saleId, $userId);
        if (!$payment) {
            return null;
        }

        if (in_array((string) $payment['status'], ['paid', 'failed', 'cancelled', 'expired'], true)) {
            return $payment;
        }

        $gateway = $this->gatewayModel->findByUserAndGateway($userId, (string) $payment['gateway']);
        if (!$gateway) {
            return $payment;
        }

        $client = GatewayFactory::make((string) $payment['gateway']);
        $remote = $client->fetchPayment($gateway['credentials'], (string) $payment['transaction_id']);

        $this->paymentModel->updateStatus(
            (int) $payment['id'],
            $remote['status'],
            $this->normalizeDateTime($remote['paid_at'] ?? null),
            $remote['provider_payload'] ?? null
        );

        if ($remote['status'] === 'paid') {
            $this->saleModel->markAsPaid(
                (int) $payment['sale_id'],
                (string) $payment['transaction_id'],
                $this->normalizeDateTime($remote['paid_at'] ?? date('Y-m-d H:i:s'))
            );
        }

        return $this->paymentModel->findBySaleId($saleId, $userId);
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
}
