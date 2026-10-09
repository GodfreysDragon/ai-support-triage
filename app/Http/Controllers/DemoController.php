<?php

namespace App\Http\Controllers;

use App\Demo\CreateDemoAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    /**
     * Sign the visitor in to a fresh demo account.
     */
    public function __invoke(Request $request, CreateDemoAccount $createDemoAccount): RedirectResponse
    {
        abort_unless(config('demo.enabled'), 404);

        Auth::login($createDemoAccount());
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
