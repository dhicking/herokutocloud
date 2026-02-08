<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Heroku\HerokuClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function connect(Request $request): Response
    {
        $herokuToken = $request->session()->get('heroku_access_token');
        $herokuConnected = (bool) $herokuToken;
        $cloudConnected = (bool) $request->session()->get('cloud_api_token');
        $cloudOrganizationName = $request->session()->get('cloud_organization_name');

        $herokuAccountEmail = null;
        if ($herokuToken) {
            try {
                $client = new HerokuClient($herokuToken);
                $account = $client->getAccount();
                $herokuAccountEmail = $account['email'] ?? null;
            } catch (\Throwable) {
                // token may be expired; still show as connected, name will be blank
            }
        }

        return Inertia::render('Import/Connect', [
            'herokuConnected' => $herokuConnected,
            'herokuAccountEmail' => $herokuAccountEmail,
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
