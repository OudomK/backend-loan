<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->useLogName($this->getTable())
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['updated_at']);

        $except = method_exists($this, 'auditLogExcept')
            ? (array) $this->auditLogExcept()
            : [];

        return empty($except)
            ? $options
            : $options->logExcept($except);
    }
}
