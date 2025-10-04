<?php

namespace App\Http\Controllers;

use App\Models\PensionSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class PensionSimulationController extends Controller
{
    /**
     * Store a new pension simulation session
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'pension_value' => 'required|numeric|min:500|max:50000',
        ]);

        $pensionValue = $request->input('pension_value');
        
        // Calculate additional data that might be useful
        $calculationData = $this->calculatePensionData($pensionValue);

        $session = PensionSession::create([
            'pension_value' => $pensionValue,
            'calculation_data' => $calculationData,
        ]);

        return response()->json([
            'success' => true,
            'session_uuid' => $session->uuid,
            'share_url' => route('pension.session.show', $session->uuid),
        ]);
    }

    /**
     * Show a shared pension session
     */
    public function show(string $uuid): Response
    {
        $session = PensionSession::where('uuid', $uuid)
            ->notExpired()
            ->first();

        if (!$session) {
            abort(404, 'Sesja nie została znaleziona lub wygasła.');
        }

        // Prepare data for the frontend
        $pensionData = $this->preparePensionData($session->pension_value, $session->calculation_data);

        return Inertia::render('Welcome', [
            'sharedSession' => [
                'uuid' => $session->uuid,
                'pension_value' => $session->pension_value,
                'created_at' => $session->created_at,
            ],
            'sharedPensionData' => $pensionData,
        ]);
    }

    /**
     * Calculate pension-related data
     */
    private function calculatePensionData(float $pensionValue): array
    {
        $averagePension = 3500;
        $percentageDifference = (($pensionValue - $averagePension) / $averagePension) * 100;

        return [
            'average_pension' => $averagePension,
            'percentage_difference' => round($percentageDifference, 1),
            'calculated_at' => now()->toISOString(),
        ];
    }

    /**
     * Prepare pension data for frontend display
     */
    private function preparePensionData(float $pensionValue, ?array $calculationData): array
    {
        $pensionGroups = [
            [
                'name' => 'Poniżej minimalnej',
                'amount' => 1200,
                'percentage' => 15,
                'color' => 'rgb(240, 94, 94)',
                'description' => 'Świadczeniobiorcy otrzymujący emeryturę w wysokości poniżej minimalnej wykazywali się niską aktywnością zawodową, nie przepracowali minimum 25 lat dla mężczyzn i 20 lat dla kobiet, w związku z tym nie nabyli prawa do gwarancji minimalnej emerytury.'
            ],
            [
                'name' => 'Minimalna',
                'amount' => 1780,
                'percentage' => 25,
                'color' => 'rgb(190, 195, 206)',
                'description' => 'Osoby otrzymujące emeryturę minimalną przepracowały wymagany staż pracy, jednak ich składki były niskie z uwagi na niskie wynagrodzenia lub przerwy w karierze zawodowej.'
            ],
            [
                'name' => 'Średnia krajowa',
                'amount' => 3500,
                'percentage' => 35,
                'color' => 'rgb(0, 153, 63)',
                'description' => 'Najliczniejsza grupa emerytów, którzy pracowali przez większość swojego życia zawodowego, ze średnimi wynagrodzeniami. Stanowią trzon systemu emerytalnego.'
            ],
            [
                'name' => 'Powyżej średniej',
                'amount' => 5500,
                'percentage' => 20,
                'color' => 'rgb(63, 132, 210)',
                'description' => 'Osoby, które przez większość kariery otrzymywały wynagrodzenia powyżej średniej krajowej, często specjaliści lub kadra menedżerska.'
            ],
            [
                'name' => 'Wysokie',
                'amount' => 8000,
                'percentage' => 5,
                'color' => 'rgb(255, 179, 79)',
                'description' => 'Grupa obejmująca osoby z długim stażem pracy i wysokimi zarobkami, często członkowie zarządów, właściciele firm lub wysocy specjaliści.'
            ]
        ];

        // Find user's group
        $userGroup = collect($pensionGroups)->first(function ($group) use ($pensionValue) {
            return $pensionValue <= $group['amount'];
        }) ?? $pensionGroups[count($pensionGroups) - 1];

        return [
            'pension_groups' => $pensionGroups,
            'user_group' => $userGroup,
            'average_pension' => $calculationData['average_pension'] ?? 3500,
            'percentage_difference' => $calculationData['percentage_difference'] ?? 0,
        ];
    }
}

