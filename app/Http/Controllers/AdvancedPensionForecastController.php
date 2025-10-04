<?php

namespace App\Http\Controllers;

use App\Services\PensionCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kontroler odpowiedzialny za zaawansowany dashboard prognozowania emerytur
 * 
 * Umożliwia użytkownikom szczegółowe prognozowanie z możliwością:
 * - Wprowadzania konkretnych kwot wynagrodzeń z przeszłości
 * - Definiowania przyszłych wynagrodzeń i wskaźników indeksacji
 * - Określania okresów choroby w przeszłości i przyszłości
 * - Podglądu wzrostu kapitału na koncie i subkoncie ZUS w czasie
 */
class AdvancedPensionForecastController extends Controller
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
     * Wyświetla zaawansowany dashboard prognozowania
     *
     * @return Response Widok Inertia z dashboardem
     */
    public function index(): Response
    {
        return Inertia::render('PensionForecastDashboard');
    }

    /**
     * Przetwarza zaawansowaną symulację z szczegółowymi danymi historycznymi i przyszłościowymi
     *
     * @param Request $request Dane z formularza
     * @return JsonResponse Wyniki prognozy w formacie JSON
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'age' => 'required|integer|min:18|max:100',
            'gender' => 'required|in:male,female',
            'current_gross_salary' => 'required|numeric|min:1000|max:100000',
            'retirement_year' => 'required|integer|min:' . date('Y') . '|max:' . (date('Y') + 50),
            'account_balance' => 'nullable|numeric|min:0|max:10000000',
            'subaccount_balance' => 'nullable|numeric|min:0|max:10000000',
            'wage_indexation_rate' => 'required|numeric|min:0|max:20',
            'historical_data' => 'nullable|array',
            'historical_data.*.year' => 'required|integer',
            'historical_data.*.gross_salary' => 'required|numeric|min:0',
            'historical_data.*.sick_leave_days' => 'required|integer|min:0|max:365',
            'future_data' => 'nullable|array',
            'future_data.*.year' => 'required|integer',
            'future_data.*.gross_salary' => 'required|numeric|min:0',
            'future_data.*.sick_leave_days' => 'required|integer|min:0|max:365',
        ]);

        // Oblicz bazową prognozę
        $basePension = $this->calculationService->calculatePension(
            age: $validated['age'],
            gender: $validated['gender'],
            grossSalary: $validated['current_gross_salary'],
            retirementYear: $validated['retirement_year'],
            accountBalance: $validated['account_balance'] ?? null,
            subaccountBalance: $validated['subaccount_balance'] ?? null,
            includeSickLeave: false,
            forecastVariant: 'variant_1'
        );

        // Oblicz prognozę wzrostu konta
        $accountGrowthForecast = $this->calculateAccountGrowthForecast(
            $validated['age'],
            $validated['gender'],
            $validated['current_gross_salary'],
            $validated['retirement_year'],
            $validated['account_balance'] ?? null,
            $validated['subaccount_balance'] ?? null,
            $validated['wage_indexation_rate'],
            $validated['historical_data'] ?? [],
            $validated['future_data'] ?? []
        );

        // Oblicz szczegółowy wpływ zwolnień lekarskich
        $detailedSickLeaveImpact = $this->calculateDetailedSickLeaveImpact(
            $validated['historical_data'] ?? [],
            $validated['future_data'] ?? []
        );

        // Oblicz scenariusze odroczenia emerytury
        $delayedRetirementOptions = [];
        foreach ([1, 2, 5] as $additionalYears) {
            $delayedPension = $this->calculationService->calculatePension(
                age: $validated['age'] + $additionalYears,
                gender: $validated['gender'],
                grossSalary: $validated['current_gross_salary'],
                retirementYear: $validated['retirement_year'] + $additionalYears,
                accountBalance: $validated['account_balance'] ?? null,
                subaccountBalance: $validated['subaccount_balance'] ?? null,
                includeSickLeave: false,
                forecastVariant: 'variant_1'
            );

            $delayedRetirementOptions[] = [
                'additional_years' => $additionalYears,
                'retirement_age' => $this->calculationService->getRetirementAge($validated['gender']) + $additionalYears,
                'monthly_pension' => $delayedPension['monthly_pension'],
                'total_capital' => $delayedPension['account_balance'] + $delayedPension['subaccount_balance'],
            ];
        }

        return response()->json([
            'monthly_pension' => $basePension['monthly_pension'],
            'account_balance' => $basePension['account_balance'],
            'subaccount_balance' => $basePension['subaccount_balance'],
            'total_contributions' => $basePension['total_contributions'],
            'years_to_retirement' => $basePension['years_to_retirement'],
            'economic_context' => $basePension['economic_context'],
            'account_growth_forecast' => $accountGrowthForecast,
            'sick_leave_impact' => $detailedSickLeaveImpact,
            'delayed_retirement_options' => $delayedRetirementOptions,
        ]);
    }

    /**
     * Oblicza prognozę wzrostu kapitału na koncie i subkoncie ZUS
     *
     * @param int $age Obecny wiek
     * @param string $gender Płeć
     * @param float $currentSalary Obecne wynagrodzenie
     * @param int $retirementYear Rok emerytury
     * @param float|null $initialAccountBalance Początkowe saldo konta
     * @param float|null $initialSubaccountBalance Początkowe saldo subkonta
     * @param float $indexationRate Wskaźnik indeksacji (%)
     * @param array $historicalData Dane historyczne
     * @param array $futureData Dane przyszłościowe
     * @return array Tablica z rocznym wzrostem kapitału
     */
    private function calculateAccountGrowthForecast(
        int $age,
        string $gender,
        float $currentSalary,
        int $retirementYear,
        ?float $initialAccountBalance,
        ?float $initialSubaccountBalance,
        float $indexationRate,
        array $historicalData,
        array $futureData
    ): array {
        $currentYear = (int) date('Y');
        $retirementAge = $this->calculationService->getRetirementAge($gender);
        $yearsToRetirement = $retirementYear - $currentYear;

        // Stawki składek
        $contributionRate = (float) config('pension.calculation.contribution_rate', 0.1952);
        $accountContributionRate = (float) config('pension.calculation.account_contribution_rate', 0.1222);
        $subaccountRate = 0.25; // 25% trafia na subkonto

        // Szacowanie początkowych sald
        if ($initialAccountBalance === null) {
            $workStartAge = (int) config('pension.calculation.default_work_start_age', 25);
            $estimatedYearsWorked = max(0, $age - $workStartAge);
            $initialAccountBalance = $currentSalary * $accountContributionRate * 12 * $estimatedYearsWorked * 1.05;
        }

        if ($initialSubaccountBalance === null) {
            $initialSubaccountBalance = $initialAccountBalance * $subaccountRate;
        }

        $forecast = [];
        $accountBalance = $initialAccountBalance;
        $subaccountBalance = $initialSubaccountBalance;
        $cumulativeContribution = $accountBalance + $subaccountBalance;

        // Generuj prognozę dla każdego roku do emerytury
        for ($i = 1; $i <= $yearsToRetirement; $i++) {
            $year = $currentYear + $i;
            $currentAge = $age + $i;

            // Znajdź wynagrodzenie dla tego roku
            $salary = $currentSalary * pow(1 + ($indexationRate / 100), $i);
            
            // Sprawdź czy mamy konkretne dane z future_data
            foreach ($futureData as $futureItem) {
                if ($futureItem['year'] == $year && $futureItem['gross_salary'] > 0) {
                    $salary = $futureItem['gross_salary'];
                    break;
                }
            }

            // Oblicz roczne składki
            $yearlyContribution = $salary * $accountContributionRate * 12;
            
            // Uwzględnij zwolnienia lekarskie jeśli są
            $sickLeaveDays = 0;
            foreach ($futureData as $futureItem) {
                if ($futureItem['year'] == $year) {
                    $sickLeaveDays = $futureItem['sick_leave_days'] ?? 0;
                    break;
                }
            }
            
            if ($sickLeaveDays > 0) {
                // Redukcja składki o ~80% za dni zwolnienia
                $workingDays = 365 - $sickLeaveDays;
                $yearlyContribution = ($salary * $accountContributionRate * 12) * ($workingDays / 365);
            }

            // Waloryzacja (wzrost o średnio 5% rocznie)
            $valorizationRate = (float) config('pension.calculation.valorization_rate', 0.05);
            $accountBalance = $accountBalance * (1 + $valorizationRate);
            $subaccountBalance = $subaccountBalance * (1 + $valorizationRate);

            // Dodaj nowe składki
            $accountContribution = $yearlyContribution * (1 - $subaccountRate);
            $subaccountContribution = $yearlyContribution * $subaccountRate;

            $accountBalance += $accountContribution;
            $subaccountBalance += $subaccountContribution;
            $cumulativeContribution += $yearlyContribution;

            $forecast[] = [
                'year' => $year,
                'age' => $currentAge,
                'account_balance' => round($accountBalance, 2),
                'subaccount_balance' => round($subaccountBalance, 2),
                'total_balance' => round($accountBalance + $subaccountBalance, 2),
                'annual_contribution' => round($yearlyContribution, 2),
                'cumulative_contribution' => round($cumulativeContribution, 2),
            ];
        }

        return $forecast;
    }

    /**
     * Oblicza szczegółowy wpływ zwolnień lekarskich
     *
     * @param array $historicalData Dane historyczne
     * @param array $futureData Dane przyszłościowe
     * @return array Szczegółowe informacje o wpływie zwolnień
     */
    private function calculateDetailedSickLeaveImpact(array $historicalData, array $futureData): array
    {
        $totalHistoricalDays = 0;
        $totalFutureDays = 0;

        foreach ($historicalData as $item) {
            $totalHistoricalDays += $item['sick_leave_days'] ?? 0;
        }

        foreach ($futureData as $item) {
            $totalFutureDays += $item['sick_leave_days'] ?? 0;
        }

        $totalDays = $totalHistoricalDays + $totalFutureDays;
        $totalYears = count($historicalData) + count($futureData);
        $avgDaysPerYear = $totalYears > 0 ? $totalDays / $totalYears : 0;

        // Oszacowanie redukcji emerytury
        // Zwolnienie lekarskie redukuje składkę o ~80%
        $contributionLossRate = 0.80;
        $estimatedPensionReduction = ($totalDays / 365) * $contributionLossRate;

        return [
            'total_historical_sick_days' => $totalHistoricalDays,
            'total_future_sick_days' => $totalFutureDays,
            'total_sick_days' => $totalDays,
            'average_days_per_year' => round($avgDaysPerYear, 1),
            'estimated_pension_reduction_percent' => round($estimatedPensionReduction * 100, 2),
        ];
    }

    /**
     * Generuje szczegółowy raport PDF z prognozą emerytalną
     *
     * @param Request $request Dane do raportu
     * @return \Illuminate\Http\Response PDF do pobrania
     */
    public function generatePdfReport(Request $request)
    {
        try {
            $profile = $request->input('profile');
            $simulationResults = $request->input('simulation_results');
            $historicalData = $request->input('historical_data', []);
            $futureData = $request->input('future_data', []);

            // Przygotowanie danych do raportu
            $data = [
                'profile' => $profile,
                'simulation_results' => $simulationResults,
                'historical_data' => $historicalData,
                'future_data' => $futureData,
                'generation_date' => now()->format('Y-m-d H:i:s'),
            ];

            // Generowanie PDF z widoku
            $pdf = Pdf::loadView('pdf.pension-forecast-report', $data);
            
            // Konfiguracja PDF
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', true);
            
            // Nazwa pliku
            $fileName = sprintf(
                'Raport_Emerytalny_%dlat_%s.pdf',
                $profile['age'],
                now()->format('Y-m-d')
            );

            // Zwróć PDF do pobrania
            return $pdf->download($fileName);

        } catch (\Exception $e) {
            Log::error('Błąd generowania raportu PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Wystąpił błąd podczas generowania raportu PDF',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

