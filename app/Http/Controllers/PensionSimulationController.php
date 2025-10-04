<?php

namespace App\Http\Controllers;

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
}
