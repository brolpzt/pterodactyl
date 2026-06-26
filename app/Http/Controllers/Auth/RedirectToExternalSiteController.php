<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;

class RedirectToExternalSiteController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->away(config('pterodactyl.external_site_url', 'https://hostgamer.net'));
    }
}
