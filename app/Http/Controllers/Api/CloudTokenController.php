<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CloudToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_token' => ['required', 'string'],
            'organization_name' => ['nullable', 'string'],
        ]);

        CloudToken::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated,
        );

        return response()->json(['stored' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->cloudToken?->delete();

        return response()->json(['deleted' => true]);
    }
}
