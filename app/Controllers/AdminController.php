<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Sale;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\AppSettingsService;
use Throwable;

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $userModel = new User();
        $users = $userModel->allWithStats();
        $subscriptionModel = new SubscriptionPayment();
        $subscriptions = $subscriptionModel->allRecent(100);
        $activeSubscribers = array_values(array_filter($users, fn (array $user): bool => ($user['plan_status'] ?? '') === 'active'));
        $salesTotals = (new Sale())->totalsForAdminDashboard();
        $subscriptionTotals = $subscriptionModel->totalsForAdminDashboard();

        $summary = [
            'users_total' => count($users),
            'trial_total' => count(array_filter($users, fn (array $user): bool => ($user['plan_status'] ?? '') === 'trial')),
            'active_total' => count(array_filter($users, fn (array $user): bool => ($user['plan_status'] ?? '') === 'active')),
            'expired_total' => count(array_filter($users, fn (array $user): bool => ($user['plan_status'] ?? '') === 'expired')),
            'sales_daily_total' => (float) ($salesTotals['daily_total'] ?? 0),
            'sales_monthly_total' => (float) ($salesTotals['monthly_total'] ?? 0),
            'sales_lifetime_total' => (float) ($salesTotals['lifetime_total'] ?? 0),
            'subscriptions_daily_total' => (float) ($subscriptionTotals['daily_total'] ?? 0),
            'subscriptions_monthly_total' => (float) ($subscriptionTotals['monthly_total'] ?? 0),
            'subscriptions_lifetime_total' => (float) ($subscriptionTotals['lifetime_total'] ?? 0),
        ];

        $this->view('admin/index', [
            'users' => $users,
            'subscriptions' => $subscriptions,
            'activeSubscribers' => $activeSubscribers,
            'summary' => $summary,
            'settings' => (new AppSettingsService())->adminSettings(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requireAdmin();
        verify_csrf($_POST['csrf_token'] ?? null);

        try {
            (new AppSettingsService())->saveAdminSettings($_POST);
            flash('success', 'Configuracoes da assinatura atualizadas.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/admin');
    }

    public function updateUser(): void
    {
        $this->requireAdmin();
        verify_csrf($_POST['csrf_token'] ?? null);

        $userId = (int) ($_POST['id'] ?? 0);
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $planStatus = trim((string) ($_POST['plan_status'] ?? 'trial'));
        $planExpiresAt = trim((string) ($_POST['plan_expires_at'] ?? ''));

        if ($userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($planStatus, ['trial', 'active', 'expired'], true)) {
            flash('error', 'Dados do usuario invalidos.');
            $this->redirect('/admin');
        }

        $userModel = new User();
        $existing = $userModel->findById($userId);
        if (!$existing) {
            flash('error', 'Usuario nao encontrado.');
            $this->redirect('/admin');
        }

        $isAdminRequested = (int) ($_POST['is_admin'] ?? 0) === 1;
        if (!empty($existing['is_admin']) && !$isAdminRequested && $userModel->countAdminsExcluding($userId) === 0) {
            flash('error', 'Nao e permitido remover o ultimo administrador do sistema.');
            $this->redirect('/admin');
        }

        $userModel->updateProfileByAdmin($userId, [
            'store_name' => trim((string) ($_POST['store_name'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => $email,
            'is_admin' => $isAdminRequested,
            'plan_status' => $planStatus,
            'plan_expires_at' => $planExpiresAt !== '' ? str_replace('T', ' ', $planExpiresAt) . ':00' : null,
        ]);

        if ((int) auth_user()['id'] === $userId) {
            $fresh = (new User())->findById($userId);
            if ($fresh) {
                $_SESSION['user'] = $fresh;
            }
        }

        flash('success', 'Usuario atualizado.');
        $this->redirect('/admin');
    }

    public function deleteUser(): void
    {
        $this->requireAdmin();
        verify_csrf($_POST['csrf_token'] ?? null);

        $userId = (int) ($_POST['id'] ?? 0);
        if ($userId <= 0 || $userId === (int) auth_user()['id']) {
            flash('error', 'Nao e permitido remover o proprio usuario admin logado.');
            $this->redirect('/admin');
        }

        $userModel = new User();
        $existing = $userModel->findById($userId);
        if (!$existing) {
            flash('error', 'Usuario nao encontrado.');
            $this->redirect('/admin');
        }

        if (!empty($existing['is_admin']) && $userModel->countAdminsExcluding($userId) === 0) {
            flash('error', 'Nao e permitido excluir o ultimo administrador do sistema.');
            $this->redirect('/admin');
        }

        $userModel->deleteByAdmin($userId);
        flash('success', 'Usuario removido.');
        $this->redirect('/admin');
    }
}
