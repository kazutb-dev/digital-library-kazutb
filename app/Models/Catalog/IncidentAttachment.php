<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentAttachment extends Model
{
    protected $fillable = ['incident_case_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    public function incidentCase(): BelongsTo
    {
        return $this->belongsTo(CirculationIncidentCase::class, 'incident_case_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
