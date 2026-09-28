<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;

final class AppSettingsService
{
    private AppSetting $settings;

    public function __construct()
    {
        $this->settings = new AppSetting();
    }

    public function subscriptionAccessToken(): string
    {
        return trim((string) ($this->settings->get('subscription_access_token')
            ?? config('app.subscription_access_token', '')));
    }

    public function subscriptionAmount(): float
    {
        return round((float) ($this->settings->get('subscription_amount')
            ?? config('app.subscription_amount', 29.90)), 2);
    }

    public function subscriptionDays(): int
    {
        return max(1, (int) ($this->settings->get('subscription_days')
            ?? config('app.subscription_days', 30)));
    }

    public function trialDays(): int
    {
        return max(0, (int) ($this->settings->get('trial_days')
            ?? config('app.trial_days', 3)));
    }

    public function adminSettings(): array
    {
        return [
            'subscription_access_token' => $this->subscriptionAccessToken(),
            'subscription_amount' => number_format($this->subscriptionAmount(), 2, '.', ''),
            'subscription_days' => (string) $this->subscriptionDays(),
            'trial_days' => (string) $this->trialDays(),
        ];
    }

    public function saveAdminSettings(array $data): void
    {
        $token = trim((string) ($data['subscription_access_token'] ?? ''));
        $amount = round((float) ($data['subscription_amount'] ?? 0), 2);
        $subscriptionDays = max(1, (int) ($data['subscription_days'] ?? 30));
        $trialDays = max(0, (int) ($data['trial_days'] ?? 3));

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Informe um valor valido para a assinatura.');
        }

        if ($token === '') {
            $token = $this->subscriptionAccessToken();
        }

        $this->settings->setMany([
            'subscription_access_token' => $token,
            'subscription_amount' => (string) $amount,
            'subscription_days' => (string) $subscriptionDays,
            'trial_days' => (string) $trialDays,
        ]);
    }
}
