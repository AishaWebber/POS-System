<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('mail-delivery', fn (Request $request) => Limit::perMinute(5)->by('outgoing-mail-'.($request->user()?->id ?? $request->ip())));
        View::composer('*', fn ($view) => $view->with('currentUser', auth()->user()));
    }
}
