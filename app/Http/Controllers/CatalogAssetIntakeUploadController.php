<?php

namespace App\Http\Controllers;

use App\Services\CatalogAssetIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CatalogAssetIntakeUploadController extends Controller
{
    public function __invoke(Request $request, CatalogAssetIntake $intake): JsonResponse
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        abort_unless($request->hasValidRelativeSignature(), 401);
        $data = Validator::make(['launch_token' => $request->input('launch_token'), 'entry_token' => $request->query('entry_token'),
            'files' => $request->file('files')], ['launch_token' => ['required', 'string', 'max:32768'], 'entry_token' => ['required', 'string', 'max:8192'],
                'files' => ['nullable', 'array', 'size:1'], 'files.*' => ['required', 'file']])->validate();

        return response()->json($intake->transfer($request->user(), $data['launch_token'], $data['entry_token'], $data['files'][0] ?? null));
    }
}
