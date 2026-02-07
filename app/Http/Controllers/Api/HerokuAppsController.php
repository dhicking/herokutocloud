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
        $herokuApi = new HerokuApi($request->user()->herokuToken);

        return response()->json($herokuApi->listApps());
    }

    public function show(Request $request, string $app): JsonResponse
    {
        $herokuApi = new HerokuApi($request->user()->herokuToken);

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
