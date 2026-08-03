<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Campus networks
    |--------------------------------------------------------------------------
    |
    | Materials with access_level = "campus" are licensed for on-site use only,
    | so being signed in is not enough — the request must also arrive from a
    | university network. Comma-separated list of IPs or CIDR ranges (v4 or v6).
    |
    | Left empty, no address qualifies as on-campus and campus-only materials
    | stay closed. That is deliberate: silently treating every address as
    | on-campus would breach the licence terms the flag exists to enforce.
    |
    */

    'campus_ranges' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DIGITAL_CAMPUS_RANGES', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Reader position tracking
    |--------------------------------------------------------------------------
    |
    | The viewer stores the last page a reader reached so it can reopen there.
    | Progress is only kept for identified readers; guests reading "public"
    | material leave no trace.
    |
    */

    'track_reading_progress' => (bool) env('DIGITAL_TRACK_READING_PROGRESS', true),

];
