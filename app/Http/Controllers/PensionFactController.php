<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PensionFactController extends Controller
{
    /**
     * Zwraca losową ciekawostkę o emeryturach
     */
    public function random(): JsonResponse
    {
        try {
            $realPath = storage_path('app/public/pension_facts.json');
            $content = file_get_contents($realPath);
            $data = json_decode($content, true);

            if (!isset($data['facts']) || empty($data['facts'])) {
                return response()->json([
                    'fact' => 'Brak dostępnych ciekawostek w systemie.'
                ], 200);
            }

            $facts = $data['facts'];
            $randomFact = $facts[array_rand($facts)];

            return response()->json([
                'fact' => $randomFact
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'fact' => 'Nie udało się pobrać ciekawostki. Spróbuj ponownie później.'
            ], 500);
        }
    }
}

