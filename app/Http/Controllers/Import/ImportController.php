<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function connect(Request $request): Response
    {
        $herokuConnected = (bool) $request->session()->get('heroku_access_token');
        $cloudConnected = (bool) $request->session()->get('cloud_api_token');
        $cloudOrganizationName = $request->session()->get('cloud_organization_name');

        return Inertia::render('Import/Connect', [
            'herokuConnected' => $herokuConnected,
            'cloudConnected' => $cloudConnected,
            'cloudOrganizationName' => $cloudOrganizationName,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function configure(Request $request): Response
    {
        return Inertia::render('Import/Configure');
    }

    public function deploy(Request $request): Response
    {
        $result = $request->session()->get('import_deploy_result');

        return Inertia::render('Import/Deploy', [
            'deployResult' => $result,
        ]);
    }
}
