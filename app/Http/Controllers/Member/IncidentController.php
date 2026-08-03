<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        return view('member.incidents', [
            'cases' => CirculationIncidentCase::query()
                ->where('reader_id', $request->user()->getKey())
                ->with(['originalCopy.bibliographicRecord', 'fine', 'candidates', 'replacementCopy.bibliographicRecord'])
                ->latest('opened_at')->paginate(Setting::resultsPerPage()),
        ]);
    }

    public function show(Request $request, CirculationIncidentCase $incident): View
    {
        abort_unless((int) $incident->reader_id === (int) $request->user()->getKey(), 403);

        return view('member.incident-show', [
            'incident' => $incident->load(['originalCopy.bibliographicRecord', 'fine', 'candidates', 'replacementCopy.bibliographicRecord']),
        ]);
    }
}
