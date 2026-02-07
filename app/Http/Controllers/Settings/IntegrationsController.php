<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/integrations', [
            'herokuConnected' => $user->hasHerokuConnected(),
            'cloudConnected' => $user->hasCloudConnected(),
            'cloudOrganizationName' => $user->cloudToken?->organization_name,
            'status' => $request->session()->get('status'),
        ]);
    }
}
