<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityMatchLog extends Model
{
    protected $table = 'identity_match_logs';

    protected $fillable = [
        'session_user_id',
        'session_email',
        'session_ad_login',
        'matched_reader_id',
        'matched_by',
        'candidate_count',
        'has_ambiguity',
        'ambiguity_details',
        'is_stale',
        'stale_reason',
        'context_notes',
    ];

    protected $casts = [
        'has_ambiguity' => 'boolean',
        'is_stale' => 'boolean',
    ];
}
