<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ActivityTrendChart;
use App\Filament\Widgets\AdminOverviewStats;
use App\Filament\Widgets\RecentActivity;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('FadaWebs Control')
            ->colors([
                'primary' => '#0f766e',
                'success' => '#16a34a',
                'warning' => '#f59e0b',
                'danger' => '#dc2626',
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('Opportunities'),
                NavigationGroup::make()->label('Investment Operations'),
                NavigationGroup::make()->label('Risk & Compliance'),
                NavigationGroup::make()->label('People & Access'),
                NavigationGroup::make()->label('System'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AdminOverviewStats::class,
                ActivityTrendChart::class,
                RecentActivity::class,
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                ValidatePostSize::class,
                TrimStrings::class,
                ConvertEmptyStringsToNull::class,
                TrustProxies::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
