<?php

namespace App\Http\Controllers;

use App\Models\PensionSession;
use App\Http\Requests\PensionSimulationRequest;
use App\Services\PensionCalculationService;
use App\Services\PensionDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Serwis do obsługi rozkładu emerytur
     *
     * @var PensionDistributionService
     */
    private PensionDistributionService $distributionService;

    /**
     * Konstruktor kontrolera
     *
     * @param PensionCalculationService $calculationService Serwis do obliczeń
     * @param PensionDistributionService $distributionService Serwis do rozkładu emerytur
     */
    public function __construct(
        PensionCalculationService $calculationService,
        PensionDistributionService $distributionService
    ) {
        $this->calculationService = $calculationService;
        $this->distributionService = $distributionService;
    }

    /**
     * Wyświetla formularz symulacji emerytury
     *
     * @param Request $request
     * @return Response|RedirectResponse Widok Inertia z formularzem lub przekierowanie
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $sessionUuid = $request->query('session');
        
        // If session parameter exists, validate it
        if ($sessionUuid) {
            $session = PensionSession::where('uuid', $sessionUuid)
                ->notExpired()
                ->first();

            // If session doesn't exist, is expired, or doesn't have pension_value, redirect to homepage
            if (!$session || !$session->pension_value) {
                return redirect()->route('home');
            }

            // If session has form data, pass it to the frontend
            if ($session->form_data) {
                return Inertia::render('PensionSimulation', [
                    'sessionUuid' => $sessionUuid,
                    'expectedPension' => $session->pension_value,
                    'existingFormData' => $session->form_data,
                    'existingSimulationResults' => $session->simulation_results
                ]);
            }
            
            // Session exists and has pension_value but no form data yet
            return Inertia::render('PensionSimulation', [
                'sessionUuid' => $sessionUuid,
                'expectedPension' => $session->pension_value
            ]);
        }
        
        // No session parameter - redirect to homepage
        return redirect()->route('home');
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
            includeSickLeave: $validated['include_sick_leave'] ?? false,
            forecastVariant: $validated['forecast_variant'] ?? 'variant_1'
        );

        // Add expected pension comparison if provided
        if (isset($validated['expected_pension']) && $validated['expected_pension'] > 0) {
            $result['expected_pension_comparison'] = $this->calculateExpectedPensionComparison(
                $validated['expected_pension'],
                $result['monthly_pension'],
                $validated['age'],
                $validated['gender'],
                $validated['gross_salary'],
                $validated['retirement_year'],
                $validated['account_balance'] ?? null,
                $validated['subaccount_balance'] ?? null,
                $validated['forecast_variant'] ?? 'variant_1'
            );
        }

        // If session_uuid is provided, update the existing session with form data
        if (isset($validated['session_uuid'])) {
            $session = PensionSession::where('uuid', $validated['session_uuid'])
                ->notExpired()
                ->first();

            if ($session) {
                // Update session with form data and calculation results
                $session->update([
                    'form_data' => [
                        'age' => $validated['age'],
                        'gender' => $validated['gender'],
                        'gross_salary' => $validated['gross_salary'],
                        'retirement_year' => $validated['retirement_year'],
                        'account_balance' => $validated['account_balance'] ?? null,
                        'subaccount_balance' => $validated['subaccount_balance'] ?? null,
                        'include_sick_leave' => $validated['include_sick_leave'] ?? false,
                        'forecast_variant' => $validated['forecast_variant'] ?? 'variant_1',
                    ],
                    'simulation_results' => $result,
                    'updated_at' => now(),
                ]);
            }
        }

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

        // Prepare pension data for the frontend
        $pensionData = $this->preparePensionData($pensionValue, $calculationData);

        return response()->json([
            'success' => true,
            'session_uuid' => $session->uuid,
            'share_url' => route('home') . '?session=' . $session->uuid,
            'pension_data' => $pensionData,
        ]);
    }

    /**
     * Show a shared pension session
     */
    public function show(Request $request): Response
    {
        $sessionUuid = $request->query('session');
        
        // If no session parameter, show normal welcome page
        if (!$sessionUuid) {
            return Inertia::render('Welcome');
        }

        $session = PensionSession::where('uuid', $sessionUuid)
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
     * Calculate pension data for storage
     */
    private function calculatePensionData(float $pensionValue): array
    {
        $averagePension = 3500; // Średnia krajowa
        $percentageDifference = (($pensionValue - $averagePension) / $averagePension) * 100;

        return [
            'average_pension' => $averagePension,
            'percentage_difference' => $percentageDifference,
            'calculated_at' => now()->toISOString(),
        ];
    }

    /**
     * Prepare pension data for frontend display
     */
    private function preparePensionData(float $pensionValue, ?array $calculationData): array
    {
        // Wykryj czy to urządzenie mobilne (możesz użyć User-Agent lub innej metody)
        $isMobile = $this->isMobileDevice();
        
        // Użyj serwisu do przygotowania danych
        $data = $this->distributionService->preparePensionData($pensionValue, $isMobile);
        
        // Dodaj dane z kalkulacji jeśli są dostępne
        if ($calculationData) {
            $data['average_pension'] = $calculationData['average_pension'] ?? $data['average_pension'];
            $data['percentage_difference'] = $calculationData['percentage_difference'] ?? $data['percentage_difference'];
        }
        
        return $data;
    }

    /**
     * Wykrywa czy żądanie przychodzi z urządzenia mobilnego
     *
     * @return bool True jeśli urządzenie mobilne, false w przeciwnym wypadku
     */
    private function isMobileDevice(): bool
    {
        $userAgent = request()->header('User-Agent', '');
        
        $mobileKeywords = [
            'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 
            'BlackBerry', 'Windows Phone', 'Opera Mini', 'IEMobile'
        ];
        
        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calculate expected pension comparison and solutions
     */
    private function calculateExpectedPensionComparison(
        float $expectedPension,
        float $predictedPension,
        int $age,
        string $gender,
        float $grossSalary,
        int $retirementYear,
        ?float $accountBalance,
        ?float $subaccountBalance,
        string $forecastVariant
    ): array {
        $difference = $expectedPension - $predictedPension;
        $percentageDifference = $predictedPension > 0 ? ($difference / $predictedPension) * 100 : 0;
        $exceedsExpectations = $predictedPension >= $expectedPension;

        $result = [
            'expected_pension' => $expectedPension,
            'predicted_pension' => $predictedPension,
            'difference' => $difference,
            'percentage_difference' => $percentageDifference,
            'exceeds_expectations' => $exceedsExpectations,
        ];

        // If predicted pension is lower than expected, calculate solutions
        if (!$exceedsExpectations) {
            $result['solutions'] = $this->calculatePensionSolutions(
                $expectedPension,
                $predictedPension,
                $age,
                $gender,
                $grossSalary,
                $retirementYear,
                $accountBalance,
                $subaccountBalance,
                $forecastVariant
            );
        }

        return $result;
    }

    /**
     * Calculate solutions to achieve expected pension
     */
    private function calculatePensionSolutions(
        float $expectedPension,
        float $predictedPension,
        int $age,
        string $gender,
        float $grossSalary,
        int $retirementYear,
        ?float $accountBalance,
        ?float $subaccountBalance,
        string $forecastVariant
    ): array {
        $solutions = [];

        // Solution 1: Extend work period
        $solutions['extend_work_period'] = $this->calculateExtendedWorkPeriod(
            $expectedPension,
            $age,
            $gender,
            $grossSalary,
            $accountBalance,
            $subaccountBalance,
            $forecastVariant
        );

        // Solution 2: Higher salary
        $solutions['higher_salary'] = $this->calculateRequiredSalary(
            $expectedPension,
            $age,
            $gender,
            $grossSalary,
            $retirementYear,
            $accountBalance,
            $subaccountBalance,
            $forecastVariant
        );

        // Solution 3: Investment savings
        $solutions['investment_savings'] = $this->calculateInvestmentSavings(
            $expectedPension,
            $predictedPension,
            $age,
            $gender,
            $grossSalary,
            $retirementYear
        );

        return $solutions;
    }

    /**
     * Calculate how many additional years to work to achieve expected pension
     */
    private function calculateExtendedWorkPeriod(
        float $expectedPension,
        int $age,
        string $gender,
        float $grossSalary,
        ?float $accountBalance,
        ?float $subaccountBalance,
        string $forecastVariant
    ): array {
        $additionalYears = 0;
        $maxAdditionalYears = 10; // Maximum 10 additional years
        $currentYear = (int) (new \DateTime())->format('Y');
        $retirementAge = $this->getRetirementAge($gender);
        
        for ($years = 1; $years <= $maxAdditionalYears; $years++) {
            // Calculate new retirement age (original + additional years)
            $newRetirementAge = $retirementAge + $years;
            
            // Calculate new retirement year
            $newRetirementYear = $currentYear + ($newRetirementAge - $age);
            
            // Calculate pension with extended work period
            $result = $this->calculationService->calculatePension(
                age: $age,
                gender: $gender,
                grossSalary: $grossSalary,
                retirementYear: $newRetirementYear,
                accountBalance: $accountBalance,
                subaccountBalance: $subaccountBalance,
                includeSickLeave: false,
                forecastVariant: $forecastVariant
            );
            
            
            if ($result['monthly_pension'] >= $expectedPension) {
                $additionalYears = $years;
                break;
            }
        }

        if ($additionalYears > 0) {
            $newRetirementAge = $retirementAge + $additionalYears;
            $newRetirementYear = $currentYear + ($newRetirementAge - $age);
            
            $finalResult = $this->calculationService->calculatePension(
                age: $age,
                gender: $gender,
                grossSalary: $grossSalary,
                retirementYear: $newRetirementYear,
                accountBalance: $accountBalance,
                subaccountBalance: $subaccountBalance,
                includeSickLeave: false,
                forecastVariant: $forecastVariant
            );
            
            return [
                'additional_years' => $additionalYears,
                'new_retirement_year' => $newRetirementYear,
                'new_monthly_pension' => round($finalResult['monthly_pension'], 2),
            ];
        }

        return [
            'additional_years' => 0,
            'new_retirement_year' => 0,
            'new_monthly_pension' => 0,
        ];
    }

    /**
     * Calculate required salary to achieve expected pension
     */
    private function calculateRequiredSalary(
        float $expectedPension,
        int $age,
        string $gender,
        float $currentSalary,
        int $retirementYear,
        ?float $accountBalance,
        ?float $subaccountBalance,
        string $forecastVariant
    ): array {
        $requiredSalary = $currentSalary;
        $maxSalary = $currentSalary * 3; // Maximum 3x current salary
        $step = $currentSalary * 0.1; // 10% steps
        
        for ($salary = $currentSalary + $step; $salary <= $maxSalary; $salary += $step) {
            $result = $this->calculationService->calculatePension(
                age: $age,
                gender: $gender,
                grossSalary: $salary,
                retirementYear: $retirementYear,
                accountBalance: $accountBalance,
                subaccountBalance: $subaccountBalance,
                includeSickLeave: false,
                forecastVariant: $forecastVariant
            );
            
            if ($result['monthly_pension'] >= $expectedPension) {
                $requiredSalary = $salary;
                break;
            }
        }

        $salaryIncrease = $requiredSalary - $currentSalary;
        $percentageIncrease = $currentSalary > 0 ? ($salaryIncrease / $currentSalary) * 100 : 0;

        return [
            'required_salary' => round($requiredSalary, 2),
            'salary_increase' => round($salaryIncrease, 2),
            'percentage_increase' => round($percentageIncrease, 2),
        ];
    }

    /**
     * Calculate investment savings needed to compensate for pension gap
     */
    private function calculateInvestmentSavings(
        float $expectedPension,
        float $predictedPension,
        int $age,
        string $gender,
        float $grossSalary,
        int $retirementYear
    ): array {
        $pensionGap = $expectedPension - $predictedPension;
        $yearsToRetirement = $retirementYear - (new \DateTime())->format('Y');
        $investmentReturnRate = config('pension.calculation.default_investment_return_rate', 0.06);
        $lifeExpectancyYears = $gender === 'male' ? 20 : 25; // Years on pension
        
        // Calculate total amount needed to cover the gap for life expectancy
        $totalGapNeeded = $pensionGap * 12 * $lifeExpectancyYears;
        
        // Calculate monthly savings needed with compound interest
        $monthlyRate = $investmentReturnRate / 12;
        $totalMonths = $yearsToRetirement * 12;
        
        if ($monthlyRate > 0 && $totalMonths > 0) {
            $monthlySavings = $totalGapNeeded * $monthlyRate / (pow(1 + $monthlyRate, $totalMonths) - 1);
        } else {
            $monthlySavings = $totalGapNeeded / $totalMonths;
        }
        
        $percentageOfSalary = $grossSalary > 0 ? ($monthlySavings / $grossSalary) * 100 : 0;

        return [
            'monthly_savings' => round($monthlySavings, 2),
            'percentage_of_salary' => round($percentageOfSalary, 2),
            'investment_return_rate' => $investmentReturnRate * 100,
            'total_investment_needed' => round($totalGapNeeded, 2),
        ];
    }

    /**
     * Get retirement age based on gender
     */
    private function getRetirementAge(string $gender): int
    {
        return $gender === 'male' ? 65 : 60;
    }
}