<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        extract($data, EXTR_SKIP);
        $config = Container::get('config');
        $currentUser = auth_user();
        $contentView = BASE_PATH . '/app/Views/' . $view . '.php';

        if (!is_file($contentView)) {
            throw new \RuntimeException("View {$view} nao encontrada.");
        }

        require BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
    }

    protected function redirect(string $path): never
    {
        $config = Container::get('config');
        $baseUrl = $config['app']['base_url'];
        header('Location: ' . $baseUrl . $path);
        exit;
    }

    protected function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function requireGuest(): void
    {
        if (auth_check()) {
            $this->redirect('/dashboard');
        }
    }

    protected function requireAuth(): void
    {
        if (!auth_check()) {
            flash('error', 'Faca login para continuar.');
            $this->redirect('/login');
        }

        if (!validate_session_security()) {
            logout_user();
            flash('error', 'Sua sessao expirou ou mudou de dispositivo/rede. Faca login novamente.');
            $this->redirect('/login');
        }

        $userId = (int) (auth_user()['id'] ?? 0);
        if ($userId > 0) {
            $freshUser = (new \App\Models\User())->refreshPlanStatus($userId);
            if ($freshUser) {
                $_SESSION['user'] = $freshUser;
            }
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();

        if (empty(auth_user()['is_admin'])) {
            flash('error', 'Acesso restrito ao administrador.');
            $this->redirect('/dashboard');
        }
    }

    protected function enforceWritablePlan(bool $expectsJson = false): void
    {
        $user = auth_user();
        if (!$user || ($user['plan_status'] ?? 'trial') !== 'expired') {
            return;
        }

        $message = 'Seu plano expirou. Voce ainda pode consultar dados, mas novas alteracoes estao bloqueadas.';

        if ($expectsJson) {
            $this->json(['success' => false, 'message' => $message], 403);
        }

        flash('error', $message);
        $this->redirect('/dashboard');
    }

    protected function enforceSubscriptionAccess(string $featureName, bool $expectsJson = false): void
    {
        $user = auth_user();
        if (!$user || ($user['plan_status'] ?? 'trial') !== 'expired') {
            return;
        }

        $message = sprintf('Seu periodo terminou. Pague a assinatura para liberar %s.', $featureName);

        if ($expectsJson) {
            $this->json([
                'success' => false,
                'message' => $message,
                'redirect' => url('/subscription'),
            ], 403);
        }

        flash('error', $message);
        $this->redirect('/subscription');
    }
}
