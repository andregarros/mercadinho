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
            throw new \RuntimeException("View {$view} não encontrada.");
        }

        require BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . $path);
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
            flash('error', 'Faça login para continuar.');
            $this->redirect('/login');
        }
    }

    protected function enforceWritablePlan(bool $expectsJson = false): void
    {
        $user = auth_user();
        if (!$user || $user['plan_status'] !== 'expired') {
            return;
        }

        $message = 'Seu plano expirou. Você ainda pode consultar dados, mas novas alterações estão bloqueadas.';

        if ($expectsJson) {
            $this->json(['success' => false, 'message' => $message], 403);
        }

        flash('error', $message);
        $this->redirect('/dashboard');
    }
}
