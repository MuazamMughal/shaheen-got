<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit trail (Admin → Activity Log). Records who changed which fields.
 * Models override activityAttributes() to include non-fillable fields such
 * as status, which are set with forceFill().
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->activityAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(Str::snake(class_basename($this)));
    }

    protected function activityAttributes(): array
    {
        return $this->getFillable();
    }
}
