<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditActivityObserver
{
    public function created(Model $model): void
    {
        $this->record('created', $model, [], $this->safeValues($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->safeValues($model->getChanges());

        if ($changes === []) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($changes) as $attribute) {
            $oldValues[$attribute] = $model->getOriginal($attribute);
        }

        $this->record('updated', $model, $this->safeValues($oldValues), $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $this->safeValues($model->getOriginal()), []);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function safeValues(array $values): array
    {
        return collect($values)
            ->reject(fn (mixed $value, string $key): bool => preg_match('/password|remember_token|secret|account_number|routing_code|document_path|selfie_path|storage_path|metadata|payload/i', $key) === 1)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function record(string $action, Model $model, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => "record.{$action}",
            'model' => $model::class,
            'record_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'message' => Str::headline(class_basename($model))." {$action}.",
        ]);
    }
}
