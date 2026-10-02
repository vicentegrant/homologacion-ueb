<?php

namespace App\Providers;

use App\Models\HistorialEstadoSolicitud;
use App\Models\ObservacionDocumentacion;
use App\Models\ResolucionSolicitud;
use App\Observers\StudentActivityObserver;
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
        HistorialEstadoSolicitud::observe(StudentActivityObserver::class);
        ObservacionDocumentacion::observe(StudentActivityObserver::class);
        ResolucionSolicitud::observe(StudentActivityObserver::class);
    }
}
