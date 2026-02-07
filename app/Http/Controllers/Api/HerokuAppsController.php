<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Heroku\HerokuApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HerokuAppsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $token = $request->user()->herokuToken;

        if (! $token) {
            return response()->json(
                ['message' => 'Heroku account is not connected. Connect it in Settings → Integrations.'],
                403
            );
        }

        $herokuApi = new HerokuApi($token);

        return response()->json($herokuApi->listApps());
    }

    public function show(Request $request, string $app): JsonResponse
    {
        $token = $request->user()->herokuToken;

        if (! $token) {
            return response()->json(
                ['message' => 'Heroku account is not connected. Connect it in Settings → Integrations.'],
                403
            );
        }

        $herokuApi = new HerokuApi($token);

        $appData = $herokuApi->getApp($app);
        $configVars = $herokuApi->getConfigVars($app);
        $formation = $herokuApi->getFormation($app);
        $addons = $herokuApi->getAddons($app);
        $domains = $herokuApi->getDomains($app);
        $buildpacks = $herokuApi->getBuildpackInstallations($app);

        return response()->json([
            'app' => $appData,
            'config_vars' => $configVars,
            'formation' => $formation,
            'addons' => $addons,
            'domains' => $domains,
            'buildpack_installations' => $buildpacks,
        ]);
    }
}
