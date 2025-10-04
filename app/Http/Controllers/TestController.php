<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TestController extends Controller
{
    /**
     * Endpoint testowy - podstawowy test
     */
    public function index(): JsonResponse
    {
        $realPath = storage_path('app/public/pension_facts.json');
        $content = file_get_contents($realPath);
        $data = json_decode($content, true);

        dd($data);

        $jsonContent = Storage::get('public/pension_facts.json');
        $data = json_decode($jsonContent, true);
        dd($jsonContent);


        return response()->json([
            'status' => 'success',
            'message' => 'TestController działa poprawnie!',
            'timestamp' => now()->toDateTimeString(),
            'environment' => config('app.env'),
        ]);
    }

    /**
     * Endpoint testowy z parametrem
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'id' => $id,
            'message' => "Otrzymano ID: {$id}",
        ]);
    }

    /**
     * Endpoint testowy POST
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Dane zostały odebrane',
            'received_data' => $data,
        ]);
    }

    /**
     * Endpoint testowy do debugowania
     */
    public function debug(): JsonResponse
    {
        return response()->json([
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_connection' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
        ]);
    }
}
