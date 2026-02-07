<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Jobs\ImportPhase1Job;
use App\Jobs\ImportPhase2Job;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $imports = $request->user()->imports()->latest()->get();

        return response()->json($imports);
    }

    public function store(StoreImportRequest $request): JsonResponse
    {
        $import = $request->user()->imports()->create($request->validated());

        ImportPhase1Job::dispatch($import);

        return response()->json($import, 201);
    }

    public function show(Request $request, Import $import): JsonResponse
    {
        abort_unless($import->user_id === $request->user()->id, 403);

        return response()->json($import);
    }

    public function phase2(Request $request, Import $import): JsonResponse
    {
        abort_unless($import->user_id === $request->user()->id, 403);
        abort_unless($import->status === Import::STATUS_PHASE1_DONE, 422, 'Phase 1 must be completed first.');

        ImportPhase2Job::dispatch($import);

        return response()->json($import->fresh());
    }
}
