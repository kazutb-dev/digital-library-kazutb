<?php

namespace App\Models;

/**
 * Reader/editorial collection backed by the extended historical shortlist
 * table so existing saved items remain intact.
 */
class LiteratureCollection extends LiteratureDraft
{
    protected $table = 'literature_drafts';
}
