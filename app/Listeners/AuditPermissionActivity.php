<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AuditPermissionActivity
{
    public function roleAttached(RoleAttachedEvent $event): void
    {
        $this->record($event->model, 'role.attached', $event->rolesOrIds);
    }

    public function roleDetached(RoleDetachedEvent $event): void
    {
        $this->record($event->model, 'role.detached', $event->rolesOrIds);
    }

    public function permissionAttached(PermissionAttachedEvent $event): void
    {
        $this->record($event->model, 'permission.attached', $event->permissionsOrIds);
    }

    public function permissionDetached(PermissionDetachedEvent $event): void
    {
        $this->record($event->model, 'permission.detached', $event->permissionsOrIds);
    }

    private function record(Model $model, string $action, mixed $items): void
    {
        $items = is_iterable($items) ? $items : [$items];
        $names = collect($items)
            ->map(fn (mixed $item): mixed => $item instanceof Model ? ($item->name ?? $item->getKey()) : $item)
            ->values()
            ->all();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => "access.{$action}",
            'model' => $model::class,
            'record_id' => $model->getKey(),
            'new_values' => ['items' => $names],
            'message' => "Access permissions changed: {$action}.",
        ]);
    }
}
