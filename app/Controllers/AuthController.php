<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

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

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            flash('error', 'E-mail ou senha inválidos.');
            $this->redirect('/login');
        }

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

        $storeName = trim($_POST['store_name'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($storeName === '' || $name === '' || $email === '' || $password === '') {
            flash('error', 'Preencha todos os campos.');
            $this->redirect('/register');
        }

        $userModel = new User();
        if ($userModel->findByEmail($email)) {
            flash('error', 'Este e-mail já está em uso.');
            $this->redirect('/register');
        }

        $userModel->create([
            'store_name' => $storeName,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'plan_status' => 'trial',
            'plan_expires_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
        ]);

        $user = $userModel->findByEmail($email);
        if ($user) {
            login_user($user);
        }

        flash('success', 'Conta criada. Seu período de teste já está ativo.');
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        verify_csrf($_POST['csrf_token'] ?? null);
        logout_user();
        flash('success', 'Sessão encerrada.');
        $this->redirect('/login');
    }
}
