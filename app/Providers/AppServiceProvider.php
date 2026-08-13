<?php

namespace App\Providers;

use App\Models\Review;
use App\Observers\ReviewObserver;
use App\Services\PaymentGatewayInterface;
use App\Services\PaystackGateway;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PaymentGatewayInterface::class,
            PaystackGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(
            function (
                object $notifiable,
                string $token
            ) {
                return config('app.frontend_url')
                    . "/password-reset/{$token}?email="
                    . urlencode(
                        $notifiable
                            ->getEmailForPasswordReset()
                    );
            }
        );

        Review::observe(
            ReviewObserver::class
        );
    }
}