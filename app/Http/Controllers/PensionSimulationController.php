<?php

namespace App\Http\Controllers;

use App\Models\PensionSession;
use App\Http\Requests\PensionSimulationRequest;
use App\Services\PensionCalculationService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kontroler odpowiedzialny za obsługę symulacji przyszłej emerytury
 * 
 * Umożliwia użytkownikom prognozowanie wysokości ich przyszłej emerytury
 * na podstawie wprowadzonych danych osobowych i zawodowych.
 */
class PensionSimulationController extends Controller
{
    /**
     * Serwis do obliczeń emerytalnych
     *
     * @var PensionCalculationService
     */
    private PensionCalculationService $calculationService;

    /**
     * Konstruktor kontrolera
     *
     * @param PensionCalculationService $calculationService Serwis do obliczeń
     */
    public function __construct(PensionCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * Wyświetla formularz symulacji emerytury
     *
     * @return Response Widok Inertia z formularzem
     */
    public function index(): Response
    {
        return Inertia::render('PensionSimulation');
    }

    /**
     * Przetwarza dane z formularza i zwraca prognozę emerytury
     *
     * @param PensionSimulationRequest $request Walidowane dane z formularza
     * @return JsonResponse Wyniki prognozy w formacie JSON
     */
    public function simulate(PensionSimulationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->calculationService->calculatePension(
            age: $validated['age'],
            gender: $validated['gender'],
            grossSalary: $validated['gross_salary'],
            retirementYear: $validated['retirement_year'],
            accountBalance: $validated['account_balance'] ?? null,
            subaccountBalance: $validated['subaccount_balance'] ?? null,
            includeSickLeave: $validated['include_sick_leave'] ?? false
        );

        return response()->json($result);
    }


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