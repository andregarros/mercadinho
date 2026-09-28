<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\Payments\PaymentManager;
use Throwable;

final class PaymentController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $manager = new PaymentManager();

        $this->view('payments/index', [
            'gatewayOptions' => $manager->gatewayOptions(),
            'gateways' => $manager->gatewaysForUser((int) auth_user()['id']),
        ]);
    }

    public function connect(): void
    {
        $this->requireAuth();
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        $gateway = trim((string) ($_POST['gateway_name'] ?? ''));
        $credentials = $this->extractCredentials($gateway, $_POST);

        try {
            (new PaymentManager())->connectGateway((int) auth_user()['id'], $gateway, $credentials);
            flash('success', gateway_label($gateway) . ' conectado com sucesso.');
        } catch (Throwable $exception) {
            flash('error', 'Erro ao conectar ' . gateway_label($gateway) . ': ' . $exception->getMessage());
        }

        $this->redirect('/payments');
    }

    public function disconnect(): void
    {
        $this->requireAuth();
        $this->enforceWritablePlan();
        verify_csrf($_POST['csrf_token'] ?? null);

        $gateway = trim((string) ($_POST['gateway_name'] ?? ''));
        (new PaymentManager())->disconnectGateway((int) auth_user()['id'], $gateway);
        flash('success', gateway_label($gateway) . ' desvinculado.');
        $this->redirect('/payments');
    }

    public function status(): void
    {
        $this->requireAuth();

        $saleId = (int) ($_GET['sale_id'] ?? 0);
        $userId = (int) auth_user()['id'];
        $manager = new PaymentManager();

        $payment = (new Payment())->findBySaleId($saleId, $userId);
        $sale = (new Sale())->findWithItems($saleId, $userId);

        if (!$sale || !$payment) {
            $this->json(['success' => false, 'message' => 'Pagamento nao encontrado.'], 404);
        }

        if (($sale['payment_status'] ?? 'pending') !== 'paid' && ($payment['status'] ?? 'pending') === 'pending') {
            try {
                $payment = $manager->refreshSalePaymentStatus($saleId, $userId) ?? $payment;
                $sale = (new Sale())->findWithItems($saleId, $userId) ?? $sale;
            } catch (Throwable) {
                // Mantem o ultimo status local se o gateway estiver temporariamente indisponivel.
            }
        }

        $this->json([
            'success' => true,
            'sale' => [
                'id' => $sale['id'],
                'payment_status' => $sale['payment_status'],
                'paid_at' => $sale['paid_at'],
            ],
            'payment' => $payment,
        ]);
    }

    public function webhookMercadoPago(): void
    {
        $this->handleWebhook('mercado_pago');
    }

    public function webhookPagSeguro(): void
    {
        $this->handleWebhook('pagseguro');
    }

    public function webhookAsaas(): void
    {
        $this->handleWebhook('asaas');
    }

    public function webhookStripe(): void
    {
        $this->handleWebhook('stripe');
    }

    private function handleWebhook(string $gatewayName): void
    {
        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $payload = array_replace_recursive($payload, $_POST, $_GET);

        try {
            (new PaymentManager())->reconcileWebhook($gatewayName, $payload);
            http_response_code(200);
            echo 'ok';
        } catch (Throwable $exception) {
            http_response_code(400);
            echo 'error';
        }
        exit;
    }

    private function extractCredentials(string $gateway, array $input): array
    {
        $credentials = match ($gateway) {
            'mercado_pago' => [
                'access_token' => trim((string) ($input['access_token'] ?? '')),
            ],
            'pagseguro' => [
                'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
                'token' => trim((string) ($input['token'] ?? '')),
            ],
            'asaas' => [
                'api_key' => trim((string) ($input['api_key'] ?? '')),
                'environment' => trim((string) ($input['environment'] ?? 'production')) ?: 'production',
            ],
            'stripe' => [
                'secret_key' => trim((string) ($input['secret_key'] ?? '')),
            ],
            default => throw new \InvalidArgumentException('Gateway invalido.'),
        };

        foreach ($credentials as $field => $value) {
            if ($field === 'environment') {
                continue;
            }

            if ($value === '') {
                throw new \InvalidArgumentException('Preencha todas as credenciais antes de validar o gateway.');
            }
        }

        if (isset($credentials['email']) && !filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Informe um e-mail valido para o PagSeguro.');
        }

        if (isset($credentials['environment']) && !in_array($credentials['environment'], ['production', 'sandbox'], true)) {
            throw new \InvalidArgumentException('Ambiente do Asaas invalido.');
        }

        return $credentials;
    }
}
