<?php

namespace App\Providers;

use App\Models\HistorialEstadoSolicitud;
use App\Models\ObservacionDocumentacion;
use App\Models\ResolucionSolicitud;
use App\Models\User;
use App\Observers\StudentActivityObserver;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim(config('app.frontend_url'), '/').'/?'.http_build_query(['reset_token' => $token, 'email' => $user->email]));
        HistorialEstadoSolicitud::observe(StudentActivityObserver::class);
        ObservacionDocumentacion::observe(StudentActivityObserver::class);
        ResolucionSolicitud::observe(StudentActivityObserver::class);
    }
}
