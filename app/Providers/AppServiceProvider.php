<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('manage-internpulse', fn (User $user): bool => $user->role === 'superadmin');
        \Illuminate\Support\Facades\View::composer('management.*', function (View $view): void {
            $view->with('t', fn (string $en, string $ms) => new HtmlString('<span data-en="'.e($en).'" data-ms="'.e($ms).'">'.e($en).'</span>'));
        });
    }
}
