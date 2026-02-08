<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Heroku\HerokuClient;
use App\Services\Import\ResourceMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HerokuApiController extends Controller
{
    public function listApps(Request $request): JsonResponse
    {
        $token = $request->session()->get('heroku_access_token');
        if (! $token) {
            return response()->json(['message' => 'Heroku not connected.'], 403);
        }
        $client = new HerokuClient($token);
        $apps = $client->getApps();
        $phpApps = [];
        foreach ($apps as $app) {
            $buildpackDesc = $app['buildpack_provided_description'] ?? null;
            $isPhp = $buildpackDesc && stripos($buildpackDesc, 'PHP') !== false;
            if (! $isPhp && ! empty($app['id'])) {
                $buildpacks = $client->getBuildpackInstallations($app['id']);
                foreach ($buildpacks as $bp) {
                    $name = $bp['buildpack']['name'] ?? $bp['buildpack']['url'] ?? '';
                    if (stripos($name, 'php') !== false) {
                        $isPhp = true;
                        break;
                    }
                }
            }
            $phpApps[] = [
                'id' => $app['id'],
                'name' => $app['name'],
                'web_url' => $app['web_url'] ?? '',
                'region' => $app['region'] ?? ['name' => 'us'],
                'buildpack_provided_description' => $buildpackDesc,
                'updated_at' => $app['updated_at'] ?? '',
                'owner' => $app['owner'] ?? ['email' => ''],
                'is_compatible' => $isPhp,
            ];
        }

        return response()->json($phpApps);
    }

    public function getResources(Request $request, string $appId): JsonResponse
    {
        $token = $request->session()->get('heroku_access_token');
        if (! $token) {
            return response()->json(['message' => 'Heroku not connected.'], 403);
        }
        $client = new HerokuClient($token);
        $mapper = new ResourceMapper($client);
        $resources = $mapper->fetchResources($appId);
        $app = $client->getApp($appId);

        return response()->json([
            'app' => $app,
            'resources' => $resources,
        ]);
    }
}
