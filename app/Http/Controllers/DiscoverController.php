<?php

namespace App\Http\Controllers;

use App\Services\UdcClassificationService;
use Illuminate\View\View;

class DiscoverController extends Controller
{
    public function __invoke(UdcClassificationService $classification): View
    {
        return view('discover', [
            'activePage' => 'discover',
            'udcTree' => $classification->tree(),
        ]);
    }
}
