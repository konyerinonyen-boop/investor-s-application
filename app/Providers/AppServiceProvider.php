<?php

namespace App\Providers;

use App\Listeners\AuditPermissionActivity;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\EquityInvestment;
use App\Models\EquityRound;
use App\Models\KycProfile;
use App\Models\Loan;
use App\Models\LoanOffer;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Observers\AuditActivityObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super_admin') ? true : null;
        });

        foreach ([
            User::class,
            Product::class,
            EquityRound::class,
            EquityInvestment::class,
            LoanOffer::class,
            Loan::class,
            KycProfile::class,
            Document::class,
            Payment::class,
            RepaymentSchedule::class,
            Notification::class,
            Role::class,
            Permission::class,
        ] as $model) {
            $model::observe(AuditActivityObserver::class);
        }

        Event::listen(Login::class, function (Login $event): void {
            AuditLog::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'action' => 'auth.login',
                'model' => User::class,
                'record_id' => $event->user->getAuthIdentifier(),
                'message' => 'User signed in.',
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user === null) {
                return;
            }

            AuditLog::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'action' => 'auth.logout',
                'model' => User::class,
                'record_id' => $event->user->getAuthIdentifier(),
                'message' => 'User signed out.',
            ]);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            AuditLog::create([
                'user_id' => $event->user?->getAuthIdentifier(),
                'action' => 'auth.failed',
                'model' => User::class,
                'record_id' => $event->user?->getAuthIdentifier(),
                'new_values' => ['ip_address' => request()->ip(), 'guard' => $event->guard],
                'message' => 'Failed sign-in attempt.',
            ]);
        });

        Event::listen(RoleAttachedEvent::class, fn (RoleAttachedEvent $event) => app(AuditPermissionActivity::class)->roleAttached($event));
        Event::listen(RoleDetachedEvent::class, fn (RoleDetachedEvent $event) => app(AuditPermissionActivity::class)->roleDetached($event));
        Event::listen(PermissionAttachedEvent::class, fn (PermissionAttachedEvent $event) => app(AuditPermissionActivity::class)->permissionAttached($event));
        Event::listen(PermissionDetachedEvent::class, fn (PermissionDetachedEvent $event) => app(AuditPermissionActivity::class)->permissionDetached($event));

        Blueprint::macro('money', function (string $column = 'amount') {
            return $this->decimal($column, 15, 2);
        });
    }
}
