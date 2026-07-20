<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class TaxPageController extends Controller
{
    public function index(): View
    {
        return view('tax.index');
    }
}
