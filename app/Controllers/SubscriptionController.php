<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Subscriptions\SubscriptionManager;
use Throwable;

final class SubscriptionController extends Controller
{
    public function show(): void
    {
        $this->requireAuth();

        $state = (new SubscriptionManager())->currentStateForUser((int) auth_user()['id']);
        $this->view('subscription/show', $state);
    }

    public function createPix(): void
    {
        $this->requireAuth();
        verify_csrf($_POST['csrf_token'] ?? null);

        try {
            $payment = (new SubscriptionManager())->createOrGetPendingPayment((int) auth_user()['id']);
            flash('success', 'PIX da assinatura gerado com sucesso.');
            $_SESSION['subscription_payment_id'] = (int) $payment['id'];
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/subscription');
    }

    public function status(): void
    {
        $this->requireAuth();

        try {
            $result = (new SubscriptionManager())->refreshPaymentStatus((int) auth_user()['id']);

            if (!empty($result['user'])) {
                $_SESSION['user'] = $result['user'];
            }

            $this->json([
                'success' => true,
                'user' => $result['user'],
                'payment' => $result['payment'],
            ]);
        } catch (Throwable $exception) {
            $this->json(['success' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    public function webhookMercadoPago(): void
    {
        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $payload = array_replace_recursive($payload, $_POST, $_GET);

        try {
            (new SubscriptionManager())->reconcileWebhook('mercado_pago', $payload);
            http_response_code(200);
            echo 'ok';
        } catch (Throwable) {
            http_response_code(400);
            echo 'error';
        }
        exit;
    }
}
