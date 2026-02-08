<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Cloud\CloudClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudAuthController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'api_token' => ['required', 'string'],
        ]);
        $token = $request->input('api_token');
        try {
            $client = new CloudClient($token);
            $meta = $client->verifyToken();
            $request->session()->put('cloud_api_token', $token);
            $orgName = $meta['data']['attributes']['organization_name'] ?? $meta['organization_name'] ?? null;
            if ($orgName) {
                $request->session()->put('cloud_organization_name', $orgName);
            }

            return response()->json(['verified' => true, 'organization_name' => $orgName]);
        } catch (\Throwable $e) {
            return response()->json(['verified' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
