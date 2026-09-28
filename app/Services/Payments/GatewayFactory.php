<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\Http\HttpClient;

final class GatewayFactory
{
    public static function make(string $gateway): GatewayClientInterface
    {
        $http = new HttpClient();

        return match ($gateway) {
            'mercado_pago' => new MercadoPagoGatewayClient($http),
            'pagseguro' => new PagSeguroGatewayClient($http),
            'asaas' => new AsaasGatewayClient($http),
            'stripe' => new StripeGatewayClient($http),
            default => throw new \InvalidArgumentException('Gateway nao suportado: ' . $gateway),
        };
    }

    public static function supportedGateways(): array
    {
        return [
            'mercado_pago' => [
                'label' => gateway_label('mercado_pago'),
                'fields' => [
                    'access_token' => 'Access Token',
                ],
            ],
            'pagseguro' => [
                'label' => gateway_label('pagseguro'),
                'fields' => [
                    'email' => 'Email da conta',
                    'token' => 'Token',
                ],
            ],
            'asaas' => [
                'label' => gateway_label('asaas'),
                'fields' => [
                    'api_key' => 'API Key',
                    'environment' => 'Ambiente',
                ],
            ],
            'stripe' => [
                'label' => gateway_label('stripe'),
                'fields' => [
                    'secret_key' => 'Secret Key',
                ],
            ],
        ];
    }
}
