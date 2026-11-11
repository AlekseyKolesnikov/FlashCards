<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Activation email (SPECIFICATION.md §9.1). The link is signed and expires.
        VerifyEmail::createUrlUsing(fn ($user) => URL::temporarySignedRoute(
            'api.email.verify',
            now()->addMinutes(config('auth.verification.expire')),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));

        VerifyEmail::toMailUsing(fn ($user, string $url) => (new MailMessage)
            ->subject('Activate your '.config('app.name').' account')
            ->greeting("Hello {$user->username},")
            ->line('Open this link to activate your account:')
            ->action('Activate account', $url)
            ->line('The link works for '.intdiv(config('auth.verification.expire'), 60).' hours.')
            ->line('If you did not create an account, ignore this email.'));
    }
}
