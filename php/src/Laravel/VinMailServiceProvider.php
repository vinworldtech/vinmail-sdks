<?php

declare(strict_types=1);

namespace VinWorld\VinMail\Laravel;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use VinWorld\VinMail\VinMailClient;

class VinMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VinMailClient::class, function ($app): VinMailClient {
            $config = $app['config']->get('services.vinmail', []);
            $key = (string) ($config['key'] ?? env('VINMAIL_API_KEY', ''));
            $baseUrl = (string) ($config['base_url'] ?? env('VINMAIL_BASE_URL', VinMailClient::DEFAULT_BASE_URL));

            return new VinMailClient($key, $baseUrl);
        });

        $this->app->alias(VinMailClient::class, 'vinmail');
    }

    public function boot(): void
    {
        if (class_exists(Mail::class) && class_exists(\Symfony\Component\Mailer\Transport\AbstractTransport::class)) {
            Mail::extend('vinmail', function (array $config = []): VinMailTransport {
                $key = (string) ($config['key'] ?? config('services.vinmail.key') ?? env('VINMAIL_API_KEY', ''));
                $baseUrl = (string) ($config['base_url'] ?? config('services.vinmail.base_url') ?? VinMailClient::DEFAULT_BASE_URL);
                return new VinMailTransport(new VinMailClient($key, $baseUrl));
            });
        }
    }
}
