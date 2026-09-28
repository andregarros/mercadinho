<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppRateLimit;

final class LoginThrottleService
{
    private AppRateLimit $rateLimitModel;

    public function __construct()
    {
        $this->rateLimitModel = new AppRateLimit();
    }

    public function ensureAllowed(string $email): void
    {
        foreach ($this->keys($email) as $key) {
            $record = $this->rateLimitModel->findByKey($key);
            if (!$record) {
                continue;
            }

            if (!empty($record['blocked_until']) && strtotime((string) $record['blocked_until']) > time()) {
                throw new \RuntimeException('Muitas tentativas de login. Aguarde alguns minutos e tente novamente.');
            }
        }
    }

    public function registerFailure(string $email): void
    {
        $maxAttempts = max(3, (int) config('app.login_max_attempts', 5));
        $blockMinutes = max(1, (int) config('app.login_block_minutes', 15));

        foreach ($this->keys($email) as $key) {
            $this->rateLimitModel->hit($key, $maxAttempts, $blockMinutes);
        }
    }

    public function clear(string $email): void
    {
        foreach ($this->keys($email) as $key) {
            $this->rateLimitModel->clear($key);
        }
    }

    private function keys(string $email): array
    {
        return [
            'login:email:' . mb_strtolower(trim($email)),
            'login:ip:' . client_ip(),
        ];
    }
}
