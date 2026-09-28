<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\LoginThrottleService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->requireGuest();
        $this->view('auth/login', [], 'auth');
    }

    public function login(): void
    {
        $this->requireGuest();
        verify_csrf($_POST['csrf_token'] ?? null);

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            flash('error', 'Informe um e-mail valido e sua senha.');
            $this->redirect('/login');
        }

        $userModel = new User();
        $throttle = new LoginThrottleService();

        try {
            $throttle->ensureAllowed($email);
        } catch (\RuntimeException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('/login');
        }

        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password'])) {
            $throttle->registerFailure($email);
            flash('error', 'E-mail ou senha invalidos.');
            $this->redirect('/login');
        }

        $throttle->clear($email);
        $refreshed = $userModel->refreshPlanStatus((int) $user['id']) ?? $user;
        login_user($refreshed);
        flash('success', 'Login realizado com sucesso.');
        $this->redirect('/dashboard');
    }

    public function showRegister(): void
    {
        $this->requireGuest();
        $this->view('auth/register', [], 'auth');
    }

    public function register(): void
    {
        $this->requireGuest();
        verify_csrf($_POST['csrf_token'] ?? null);

        $storeName = trim((string) ($_POST['store_name'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($storeName === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('error', 'Preencha os dados corretamente. A senha deve ter no minimo 8 caracteres.');
            $this->redirect('/register');
        }

        $userModel = new User();
        $settings = new AppSettingsService();
        if ($userModel->findByEmail($email)) {
            flash('error', 'Este e-mail ja esta em uso.');
            $this->redirect('/register');
        }

        $trialDays = $settings->trialDays();

        $userModel->create([
            'store_name' => $storeName,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_admin' => $this->shouldCreateAsAdmin($email, $userModel),
            'plan_status' => $trialDays > 0 ? 'trial' : 'expired',
            'plan_expires_at' => $trialDays > 0 ? date('Y-m-d H:i:s', strtotime('+' . $trialDays . ' days')) : date('Y-m-d H:i:s'),
        ]);

        $user = $userModel->findByEmail($email);
        if ($user) {
            login_user($user);
        }

        flash('success', 'Conta criada. Seu periodo de teste de ' . $trialDays . ' dias ja esta ativo.');
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        verify_csrf($_POST['csrf_token'] ?? null);
        logout_user();
        flash('success', 'Sessao encerrada.');
        $this->redirect('/login');
    }

    private function shouldCreateAsAdmin(string $email, User $userModel): bool
    {
        $configuredAdmin = mb_strtolower(trim((string) config('app.admin_email', '')));
        if ($configuredAdmin !== '' && $configuredAdmin === mb_strtolower($email)) {
            return true;
        }

        return $userModel->countAdmins() === 0;
    }
}
