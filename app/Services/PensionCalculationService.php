<?php

namespace App\Services;

use App\Helper\ForecastFileHelper;

/**
 * Serwis odpowiedzialny za obliczenia związane z prognozowaniem emerytur
 *
 * Zawiera logikę biznesową do:
 * - Szacowania przyszłych emerytur
 * - Obliczania składek emerytalnych
 * - Waloryzacji środków
 * - Analizy wpływu zwolnień lekarskich
 *
 * Wszystkie parametry są konfigurowalne przez config/pension.php
 * i mogą być nadpisane przez zmienne środowiskowe (.env)
 */
class PensionCalculationService
{
    /**
     * Helper do wczytywania danych prognostycznych
     *
     * @var ForecastFileHelper
     */
    private ForecastFileHelper $forecastHelper;

    /**
     * Konstruktor serwisu
     */
    public function __construct()
    {
        $this->forecastHelper = new ForecastFileHelper();
    }
    /**
     * Oblicza prognozowaną emeryturę na podstawie danych użytkownika
     *
     * @param int $age Wiek użytkownika
     * @param string $gender Płeć (male/female)
     * @param float $grossSalary Miesięczne wynagrodzenie brutto
     * @param int $retirementYear Planowany rok przejścia na emeryturę
     * @param float|null $accountBalance Zgromadzone środki na koncie w ZUS
     * @param float|null $subaccountBalance Zgromadzone środki na subkoncie w ZUS
     * @param bool $includeSickLeave Czy uwzględnić zwolnienia lekarskie
     * @param string $forecastVariant Wariant prognozy ZUS (variant_1, variant_2, variant_3)
     * @return array Tablica z wynikami prognozy
     */
    public function calculatePension(
        int $age,
        string $gender,
        float $grossSalary,
        int $retirementYear,
        ?float $accountBalance = null,
        ?float $subaccountBalance = null,
        bool $includeSickLeave = false,
        ?string $forecastVariant = null
    ): array {
        // Użyj domyślnego wariantu z konfiguracji, jeśli nie podano
        $forecastVariant = $forecastVariant ?? config('pension.default_forecast_variant', 'variant_1');
        $retirementAge = $this->getRetirementAge($gender);
        
        // Calculate years to retirement based on the provided retirement year
        $currentYear = (int) date('Y');
        $yearsToRetirement = $retirementYear - $currentYear;

        // Oszacuj salda, jeśli nie podano
        if ($accountBalance === null) {
            $workStartAge = (int) config('pension.calculation.default_work_start_age', 25);
            $estimatedYearsWorked = max(0, $age - $workStartAge);
            $accountBalance = $this->estimateAccountBalance(
                $grossSalary,
                $estimatedYearsWorked,
                $forecastVariant
            );
        }

        if ($subaccountBalance === null) {
            $subaccountBalance = $this->estimateSubaccountBalance($accountBalance);
        }

        // Oblicz przyszłe składki
        $futureContributions = $this->calculateFutureContributions(
            $grossSalary,
            $yearsToRetirement,
            $forecastVariant
        );

        // Zaktualizuj salda po dodaniu przyszłych składek
        // Przyszłe składki są dzielone między konto główne (62.59%) i subkonto (37.41%)
        $accountContributionRate = (float) config('pension.calculation.account_contribution_rate', 0.1222);
        $contributionRate = (float) config('pension.calculation.contribution_rate', 0.1952);
        $accountShare = $accountContributionRate / $contributionRate; // ~0.6259
        
        $finalAccountBalance = $accountBalance + ($futureContributions * $accountShare);
        $finalSubaccountBalance = $subaccountBalance + ($futureContributions * (1 - $accountShare));

        // Łączny kapitał emerytalny
        $totalCapital = $finalAccountBalance + $finalSubaccountBalance;

        // Oblicz miesięczną emeryturę BEZ zwolnień (bazowa wartość)
        $lifeExpectancyMonths = $this->getLifeExpectancyMonths($gender);
        $monthlyPensionWithoutSickLeave = $totalCapital / $lifeExpectancyMonths;

        // Zawsze oblicz wpływ zwolnień lekarskich
        $sickLeaveImpact = $this->calculateSickLeaveImpact(
            $gender,
            $yearsToRetirement,
            $age,
            $monthlyPensionWithoutSickLeave
        );
        
        // Emerytura Z uwzględnieniem zwolnień (zawsze obliczana)
        $monthlyPensionWithSickLeave = $monthlyPensionWithoutSickLeave - $sickLeaveImpact['pension_reduction'];
        
        // Użyj odpowiedniej wartości w zależności od wyboru użytkownika
        $monthlyPension = $includeSickLeave ? $monthlyPensionWithSickLeave : $monthlyPensionWithoutSickLeave;

        // Oblicz dodatkowe informacje ekonomiczne
        $economicContext = $this->calculateEconomicContext(
            $grossSalary,
            $monthlyPension,
            $retirementYear,
            $forecastVariant
        );

        // Oblicz prognozy odroczenia emerytury (o 1, 2 i 5 lat)
        $delayedRetirementOptions = $this->calculateDelayedRetirementOptions(
            $age,
            $gender,
            $grossSalary,
            $accountBalance,
            $subaccountBalance,
            $retirementYear,
            $forecastVariant
        );

        return [
            'monthly_pension' => round($monthlyPension, 2),
            'monthly_pension_without_sick_leave' => round($monthlyPensionWithoutSickLeave, 2),
            'account_balance' => round($finalAccountBalance, 2),
            'subaccount_balance' => round($finalSubaccountBalance, 2),
            'total_contributions' => round($totalCapital, 2),
            'years_to_retirement' => $yearsToRetirement,
            'sick_leave_impact' => $sickLeaveImpact,
            'forecast_variant' => $forecastVariant,
            'economic_context' => $economicContext,
            'delayed_retirement_options' => $delayedRetirementOptions,
        ];
    }

    /**
     * Zwraca wiek emerytalny w zależności od płci
     *
     * Wartości są pobierane z konfiguracji (config/pension.php)
     * i mogą być nadpisane przez zmienne środowiskowe.
     *
     * @param string $gender Płeć (male/female)
     * @return int Wiek emerytalny
     */
    public function getRetirementAge(string $gender): int
    {
        return (int) config("pension.retirement_age.{$gender}", $gender === 'male' ? 65 : 60);
    }

    /**
     * Szacuje zgromadzone środki na koncie na podstawie historii zatrudnienia
     * z wykorzystaniem rzeczywistych danych prognostycznych ZUS
     *
     * @param float $currentSalary Obecne wynagrodzenie
     * @param int $yearsWorked Liczba przepracowanych lat
     * @param string $variant Wariant prognozy (variant_1, variant_2, variant_3)
     * @return float Szacowane saldo konta
     */
    private function estimateAccountBalance(
        float $currentSalary,
        int $yearsWorked,
        string $variant = 'variant_1'
    ): float {
        if ($yearsWorked <= 0) {
            return 0;
        }

        $contributionRate = (float) config('pension.calculation.contribution_rate', 0.1952);

        // Wczytaj dane prognostyczne
        $realWageData = $this->forecastHelper->getRealWage(); // Prognoza realnego wzrostu przeciętnego wynagrodzenia
        $cpiData = $this->forecastHelper->getCpiTotal(); // Prognoza CPI ogółem

        $currentYear = (int) date('Y');
        $totalBalance = 0;

        // Rekonstrukcja historii wynagrodzeń i składek
        for ($i = 0; $i < $yearsWorked; $i++) {
            $yearsAgo = $yearsWorked - $i;
            $historicalYear = $currentYear - $yearsAgo;

            // Oblicz historyczne wynagrodzenie używając danych prognostycznych
            $historicalSalary = $this->calculateHistoricalSalary(
                $currentSalary,
                $currentYear,
                $historicalYear,
                $realWageData,
                $variant
            );

            // Roczna składka
            $yearlyContribution = $historicalSalary * $contributionRate * 12;

            // Waloryzacja składki do dnia dzisiejszego używając CPI
            $valorizedContribution = $this->valorizeContribution(
                $yearlyContribution,
                $historicalYear,
                $currentYear,
                $cpiData,
                $variant
            );

            $totalBalance += $valorizedContribution;
        }

        return $totalBalance;
    }

    /**
     * Oblicza historyczne wynagrodzenie na podstawie danych prognostycznych
     *
     * @param float $currentSalary Obecne wynagrodzenie
     * @param int $currentYear Obecny rok
     * @param int $targetYear Rok docelowy
     * @param array $wageData Dane o wzroście wynagrodzeń
     * @param string $variant Wariant prognozy
     * @return float Wynagrodzenie w danym roku
     */
    private function calculateHistoricalSalary(
        float $currentSalary,
        int $currentYear,
        int $targetYear,
        array $wageData,
        string $variant
    ): float {
        if ($targetYear >= $currentYear) {
            return $currentSalary;
        }

        $salary = $currentSalary;
        $variantData = $wageData['series'][$variant]['data'] ?? [];

        // Cofamy się w czasie, dzieląc przez wskaźnik wzrostu
        for ($year = $currentYear; $year > $targetYear; $year--) {
            $growthIndex = $this->getGrowthIndexForYear($year, $variantData);
            // Indeks jest podany jako wartość bazująca na 100 (poprzedni rok)
            // Np. 102.9 oznacza 2.9% wzrostu
            $growthRate = ($growthIndex - 100) / 100;
            $salary = $salary / (1 + $growthRate);
        }

        return $salary;
    }

    /**
     * Waloryzuje składkę od historycznego roku do obecnego
     *
     * @param float $contribution Wysokość składki
     * @param int $fromYear Rok początkowy
     * @param int $toYear Rok końcowy
     * @param array $cpiData Dane o wskaźniku cen
     * @param string $variant Wariant prognozy
     * @return float Zwaloryzowana składka
     */
    private function valorizeContribution(
        float $contribution,
        int $fromYear,
        int $toYear,
        array $cpiData,
        string $variant
    ): float {
        if ($fromYear >= $toYear) {
            return $contribution;
        }

        $valorizedAmount = $contribution;
        $variantData = $cpiData['series'][$variant]['data'] ?? [];

        // Waloryzacja od roku historycznego do obecnego
        for ($year = $fromYear + 1; $year <= $toYear; $year++) {
            $cpiIndex = $this->getGrowthIndexForYear($year, $variantData);
            // CPI jako indeks (np. 102.5 oznacza 2.5% inflacji)
            $inflationRate = ($cpiIndex - 100) / 100;
            $valorizedAmount = $valorizedAmount * (1 + $inflationRate);
        }

        return $valorizedAmount;
    }

    /**
     * Pobiera wskaźnik wzrostu dla danego roku
     * Interpoluje dane jeśli brak danych dla konkretnego roku
     *
     * @param int $year Rok
     * @param array $data Dane z prognozy
     * @return float Wskaźnik wzrostu
     */
    private function getGrowthIndexForYear(int $year, array $data): float
    {
        $yearStr = (string) $year;

        // Jeśli mamy dokładne dane dla tego roku
        if (isset($data[$yearStr])) {
            return (float) $data[$yearStr];
        }

        // Znajdź najbliższe lata z danymi
        $years = array_keys($data);
        $years = array_map('intval', $years);
        sort($years);

        // Jeśli rok jest wcześniejszy niż najwcześniejszy rok w danych
        if ($year < min($years)) {
            return (float) $data[(string) min($years)];
        }

        // Jeśli rok jest późniejszy niż najpóźniejszy rok w danych
        if ($year > max($years)) {
            return (float) $data[(string) max($years)];
        }

        // Interpolacja liniowa
        $lowerYear = null;
        $upperYear = null;

        foreach ($years as $dataYear) {
            if ($dataYear < $year) {
                $lowerYear = $dataYear;
            } elseif ($dataYear > $year && $upperYear === null) {
                $upperYear = $dataYear;
                break;
            }
        }

        if ($lowerYear !== null && $upperYear !== null) {
            $lowerValue = (float) $data[(string) $lowerYear];
            $upperValue = (float) $data[(string) $upperYear];

            // Interpolacja liniowa
            $ratio = ($year - $lowerYear) / ($upperYear - $lowerYear);
            return $lowerValue + ($upperValue - $lowerValue) * $ratio;
        }

        // Fallback - użyj domyślnej wartości
        return 102.5; // Domyślnie 2.5% wzrostu
    }

    /**
     * Szacuje saldo subkonta na podstawie salda głównego konta
     *
     * @param float $accountBalance Saldo głównego konta
     * @return float Szacowane saldo subkonta (domyślnie 25% głównego konta)
     */
    private function estimateSubaccountBalance(float $accountBalance): float
    {
        $percentage = (float) config('pension.calculation.subaccount_percentage', 0.25);
        return $accountBalance * $percentage;
    }

    /**
     * Oblicza przyszłe składki emerytalne z uwzględnieniem wzrostu wynagrodzeń
     * na podstawie rzeczywistych danych prognostycznych ZUS
     *
     * @param float $currentSalary Obecne wynagrodzenie
     * @param int $years Liczba lat do emerytury
     * @param string $variant Wariant prognozy (variant_1, variant_2, variant_3)
     * @return float Suma przyszłych składek
     */
    private function calculateFutureContributions(
        float $currentSalary,
        int $years,
        string $variant = 'variant_1'
    ): float {
        $contributionRate = (float) config('pension.calculation.contribution_rate', 0.1952);

        // Wczytaj dane prognostyczne
        $realWageData = $this->forecastHelper->getRealWage();
        $variantData = $realWageData['series'][$variant]['data'] ?? [];

        $currentYear = (int) date('Y');
        $totalContributions = 0;
        $salary = $currentSalary;

        for ($i = 1; $i <= $years; $i++) {
            $futureYear = $currentYear + $i;

            // Pobierz wskaźnik wzrostu dla przyszłego roku
            $growthIndex = $this->getGrowthIndexForYear($futureYear, $variantData);
            $growthRate = ($growthIndex - 100) / 100;

            // Prognozowane wynagrodzenie
            $salary = $salary * (1 + $growthRate);

            // Roczna składka
            $yearlyContribution = $salary * $contributionRate * 12;

            $totalContributions += $yearlyContribution;
        }

        return $totalContributions;
    }

    /**
     * Zwraca oczekiwaną długość życia po emeryturze w miesiącach
     *
     * @param string $gender Płeć (male/female)
     * @return int Długość życia w miesiącach
     */
    private function getLifeExpectancyMonths(string $gender): int
    {
        $yearsLifeExpectancy = (int) config(
            "pension.calculation.life_expectancy.{$gender}",
            $gender === 'female' ? 25 : 20
        );

        return $yearsLifeExpectancy * 12;
    }

    /**
     * Oblicza kontekst ekonomiczny prognozy
     *
     * @param float $currentSalary Obecne wynagrodzenie
     * @param float $monthlyPension Miesięczna emerytura
     * @param int $retirementYear Rok przejścia na emeryturę
     * @param string $variant Wariant prognozy
     * @return array Kontekst ekonomiczny
     */
    private function calculateEconomicContext(
        float $currentSalary,
        float $monthlyPension,
        int $retirementYear,
        string $variant
    ): array {
        // Wczytaj dane ekonomiczne
        $cpiForPensioners = $this->forecastHelper->getCpiForPensioners();
        $realGdp = $this->forecastHelper->getRealGdp();
        $unemployment = $this->forecastHelper->getUnemploymentRate();
        $realWage = $this->forecastHelper->getRealWage();

        $currentYear = (int) date('Y');

        // 1. Oblicz przyszłe wynagrodzenie w roku emerytury
        $futureGrossSalary = $this->calculateFutureSalary(
            $currentSalary,
            $currentYear,
            $retirementYear,
            $realWage,
            $variant
        );

        // 2. Współczynnik zastąpienia (replacement rate)
        $replacementRate = ($monthlyPension / $futureGrossSalary) * 100;

        // 3. Siła nabywcza emerytury (ile będzie "warta" w dzisiejszych złotówkach)
        $purchasingPower = $this->calculatePurchasingPower(
            $monthlyPension,
            $currentYear,
            $retirementYear,
            $cpiForPensioners,
            $variant
        );

        // 4. Średni wzrost gospodarczy w okresie
        $avgGdpGrowth = $this->calculateAverageGrowth(
            $currentYear,
            $retirementYear,
            $realGdp['series'][$variant]['data'] ?? []
        );

        // 5. Średnia stopa bezrobocia w okresie
        $avgUnemployment = $this->calculateAverageValue(
            $currentYear,
            $retirementYear,
            $unemployment['series'][$variant]['data'] ?? []
        );

        // 6. Inflacja skumulowana dla emerytów
        $cumulativeInflation = $this->calculateCumulativeInflation(
            $currentYear,
            $retirementYear,
            $cpiForPensioners,
            $variant
        );

        // 7. Prognoza emerytury w cenach dzisiejszych (pierwszych 10 lat emerytury)
        $pensionForecast = $this->calculatePensionPurchasingPowerForecast(
            $monthlyPension,
            $retirementYear,
            $cpiForPensioners,
            $variant
        );

        // 8. Oblicz prognozowane średnie świadczenie emerytalne w roku emerytury
        $averagePensionInRetirementYear = $this->calculateAveragePensionInYear(
            $retirementYear,
            $currentYear,
            $realWage,
            $cpiForPensioners,
            $variant
        );

        // 9. Porównanie z prognozowanym średnim świadczeniem (ile razy więcej/mniej)
        $pensionToAverageRatio = $averagePensionInRetirementYear > 0 
            ? ($monthlyPension / $averagePensionInRetirementYear) * 100 
            : 0;

        return [
            'future_gross_salary' => round($futureGrossSalary, 2),
            'replacement_rate' => round($replacementRate, 2),
            'purchasing_power_today' => round($purchasingPower, 2),
            'avg_gdp_growth' => round($avgGdpGrowth, 2),
            'avg_unemployment_rate' => round($avgUnemployment, 2),
            'cumulative_inflation' => round($cumulativeInflation, 2),
            'pension_forecast_10years' => $pensionForecast,
            'variant_name' => $this->getVariantName($variant),
            'average_pension_in_retirement_year' => round($averagePensionInRetirementYear, 2),
            'pension_to_average_ratio' => round($pensionToAverageRatio, 2),
        ];
    }

    /**
     * Oblicza przyszłe wynagrodzenie w określonym roku
     *
     * @param float $currentSalary Obecne wynagrodzenie
     * @param int $currentYear Obecny rok
     * @param int $targetYear Rok docelowy
     * @param array $wageData Dane o wzroście wynagrodzeń
     * @param string $variant Wariant prognozy
     * @return float Przyszłe wynagrodzenie
     */
    private function calculateFutureSalary(
        float $currentSalary,
        int $currentYear,
        int $targetYear,
        array $wageData,
        string $variant
    ): float {
        if ($targetYear <= $currentYear) {
            return $currentSalary;
        }

        $salary = $currentSalary;
        $variantData = $wageData['series'][$variant]['data'] ?? [];

        for ($year = $currentYear + 1; $year <= $targetYear; $year++) {
            $growthIndex = $this->getGrowthIndexForYear($year, $variantData);
            $growthRate = ($growthIndex - 100) / 100;
            $salary = $salary * (1 + $growthRate);
        }

        return $salary;
    }

    /**
     * Oblicza siłę nabywczą przyszłej emerytury w dzisiejszych cenach
     *
     * @param float $futurePension Emerytura w przyszłości
     * @param int $fromYear Rok początkowy
     * @param int $toYear Rok końcowy
     * @param array $cpiData Dane CPI
     * @param string $variant Wariant prognozy
     * @return float Siła nabywcza w dzisiejszych cenach
     */
    private function calculatePurchasingPower(
        float $futurePension,
        int $fromYear,
        int $toYear,
        array $cpiData,
        string $variant
    ): float {
        if ($toYear <= $fromYear) {
            return $futurePension;
        }

        $presentValue = $futurePension;
        $variantData = $cpiData['series'][$variant]['data'] ?? [];

        // Dyskontuj do wartości dzisiejszej
        for ($year = $toYear; $year > $fromYear; $year--) {
            $cpiIndex = $this->getGrowthIndexForYear($year, $variantData);
            $inflationRate = ($cpiIndex - 100) / 100;
            $presentValue = $presentValue / (1 + $inflationRate);
        }

        return $presentValue;
    }

    /**
     * Oblicza średni wzrost w okresie
     *
     * @param int $fromYear Rok początkowy
     * @param int $toYear Rok końcowy
     * @param array $data Dane indeksu
     * @return float Średni wzrost procentowy
     */
    private function calculateAverageGrowth(int $fromYear, int $toYear, array $data): float
    {
        if ($toYear <= $fromYear || empty($data)) {
            return 0;
        }

        $totalGrowth = 0;
        $count = 0;

        for ($year = $fromYear + 1; $year <= $toYear; $year++) {
            $index = $this->getGrowthIndexForYear($year, $data);
            $growth = $index - 100;
            $totalGrowth += $growth;
            $count++;
        }

        return $count > 0 ? $totalGrowth / $count : 0;
    }

    /**
     * Oblicza średnią wartość w okresie
     *
     * @param int $fromYear Rok początkowy
     * @param int $toYear Rok końcowy
     * @param array $data Dane
     * @return float Średnia wartość
     */
    private function calculateAverageValue(int $fromYear, int $toYear, array $data): float
    {
        if ($toYear <= $fromYear || empty($data)) {
            return 0;
        }

        $total = 0;
        $count = 0;

        for ($year = $fromYear; $year <= $toYear; $year++) {
            $value = $this->getGrowthIndexForYear($year, $data);
            $total += $value;
            $count++;
        }

        return $count > 0 ? $total / $count : 0;
    }

    /**
     * Oblicza skumulowaną inflację
     *
     * @param int $fromYear Rok początkowy
     * @param int $toYear Rok końcowy
     * @param array $cpiData Dane CPI
     * @param string $variant Wariant prognozy
     * @return float Skumulowana inflacja w procentach
     */
    private function calculateCumulativeInflation(
        int $fromYear,
        int $toYear,
        array $cpiData,
        string $variant
    ): float {
        if ($toYear <= $fromYear) {
            return 0;
        }

        $cumulativeRate = 1.0;
        $variantData = $cpiData['series'][$variant]['data'] ?? [];

        for ($year = $fromYear + 1; $year <= $toYear; $year++) {
            $cpiIndex = $this->getGrowthIndexForYear($year, $variantData);
            $inflationRate = ($cpiIndex - 100) / 100;
            $cumulativeRate *= (1 + $inflationRate);
        }

        return ($cumulativeRate - 1) * 100;
    }

    /**
     * Oblicza prognozę siły nabywczej emerytury w pierwszych 10 latach
     *
     * @param float $initialPension Początkowa emerytura
     * @param int $retirementYear Rok emerytury
     * @param array $cpiData Dane CPI
     * @param string $variant Wariant prognozy
     * @return array Prognoza na 10 lat
     */
    private function calculatePensionPurchasingPowerForecast(
        float $initialPension,
        int $retirementYear,
        array $cpiData,
        string $variant
    ): array {
        $forecast = [];
        $pension = $initialPension;
        $variantData = $cpiData['series'][$variant]['data'] ?? [];

        for ($i = 0; $i < 10; $i++) {
            $year = $retirementYear + $i;

            // Waloryzacja emerytury (zakładamy, że emerytura rośnie z CPI)
            if ($i > 0) {
                $cpiIndex = $this->getGrowthIndexForYear($year, $variantData);
                $inflationRate = ($cpiIndex - 100) / 100;
                $pension *= (1 + $inflationRate);
            }

            // Siła nabywcza w cenach z roku emerytury
            $purchasingPower = $this->calculatePurchasingPower(
                $pension,
                $retirementYear,
                $year,
                $cpiData,
                $variant
            );

            $forecast[] = [
                'year' => $year,
                'pension_nominal' => round($pension, 2),
                'pension_real' => round($purchasingPower, 2),
            ];
        }

        return $forecast;
    }

    /**
     * Oblicza prognozowane średnie świadczenie emerytalne w danym roku
     * 
     * Bazuje na założeniu, że średnia emerytura stanowi około 55-60% średniego wynagrodzenia
     * w gospodarce (typowy współczynnik zastąpienia w systemie emerytalnym).
     * Aktualna średnia emerytura w Polsce (2025) to około 3500 zł brutto.
     *
     * @param int $targetYear Rok, dla którego obliczamy średnią emeryturę
     * @param int $currentYear Obecny rok
     * @param array $wageData Dane o wzroście wynagrodzeń
     * @param array $cpiData Dane o inflacji dla emerytów
     * @param string $variant Wariant prognozy
     * @return float Prognozowane średnie świadczenie emerytalne
     */
    private function calculateAveragePensionInYear(
        int $targetYear,
        int $currentYear,
        array $wageData,
        array $cpiData,
        string $variant
    ): float {
        // Bieżąca średnia emerytura w Polsce (2025) - około 3500 zł brutto
        $currentAveragePension = 3500.0;
        
        if ($targetYear <= $currentYear) {
            return $currentAveragePension;
        }

        // Prognozy emerytury rosną wraz z waloryzacją, która jest powiązana z CPI dla emerytów
        // oraz wzrostem wynagrodzeń (w Polsce to średnia ze wzrostu CPI i płac)
        $averagePension = $currentAveragePension;
        $wageVariantData = $wageData['series'][$variant]['data'] ?? [];
        $cpiVariantData = $cpiData['series'][$variant]['data'] ?? [];

        for ($year = $currentYear + 1; $year <= $targetYear; $year++) {
            // Wzrost wynagrodzeń
            $wageGrowthIndex = $this->getGrowthIndexForYear($year, $wageVariantData);
            $wageGrowthRate = ($wageGrowthIndex - 100) / 100;
            
            // Inflacja dla emerytów
            $cpiIndex = $this->getGrowthIndexForYear($year, $cpiVariantData);
            $inflationRate = ($cpiIndex - 100) / 100;
            
            // Waloryzacja emerytur to średnia arytmetyczna wzrostu cen i płac (tzw. wskaźnik waloryzacji)
            // zgodnie z polskim systemem emerytalnym (art. 89 ustawy emerytalnej)
            $valorizationRate = ($wageGrowthRate * 0.2 + $inflationRate * 0.8);
            
            $averagePension *= (1 + $valorizationRate);
        }

        return $averagePension;
    }

    /**
     * Zwraca nazwę wariantu prognozy
     *
     * @param string $variant Identyfikator wariantu
     * @return string Nazwa wariantu
     */
    private function getVariantName(string $variant): string
    {
        $variants = config('pension.forecast_variants', []);
        return $variants[$variant]['name'] ?? $variant;
    }

    /**
     * Oblicza opcje odroczenia emerytury o 1, 2 i 5 lat
     * 
     * Pokazuje, jak zwiększy się emerytura, jeśli użytkownik odłoży
     * decyzję o przejściu na emeryturę.
     *
     * @param int $age Obecny wiek
     * @param string $gender Płeć
     * @param float $grossSalary Wynagrodzenie brutto
     * @param float $accountBalance Saldo konta
     * @param float $subaccountBalance Saldo subkonta
     * @param int $plannedRetirementYear Planowany rok emerytury
     * @param string $variant Wariant prognozy
     * @return array Opcje odroczenia (1, 2, 5 lat)
     */
    private function calculateDelayedRetirementOptions(
        int $age,
        string $gender,
        float $grossSalary,
        float $accountBalance,
        float $subaccountBalance,
        int $plannedRetirementYear,
        string $variant
    ): array {
        $options = [];
        $delayYears = [1, 2, 5];
        $retirementAge = $this->getRetirementAge($gender);
        
        foreach ($delayYears as $delay) {
            $delayedAge = $age + ($retirementAge - $age) + $delay;
            $delayedRetirementYear = $plannedRetirementYear + $delay;
            $additionalYears = ($retirementAge - $age) + $delay;
            
            // Oblicz dodatkowe składki za okres odroczenia
            $additionalContributions = $this->calculateFutureContributions(
                $grossSalary,
                $additionalYears,
                $variant
            );
            
            // Łączny kapitał z odroczeniem
            $totalCapitalWithDelay = $accountBalance + $subaccountBalance + $additionalContributions;
            
            // Oczekiwana długość życia zmniejsza się z każdym rokiem odroczenia
            $lifeExpectancyMonths = $this->getLifeExpectancyMonths($gender) - ($delay * 12);
            
            // Miesięczna emerytura z odroczeniem
            $monthlyPensionWithDelay = $totalCapitalWithDelay / max($lifeExpectancyMonths, 12);
            
            $options[] = [
                'delay_years' => $delay,
                'retirement_year' => $delayedRetirementYear,
                'monthly_pension' => round($monthlyPensionWithDelay, 2),
                'total_capital' => round($totalCapitalWithDelay, 2),
            ];
        }
        
        return $options;
    }

    /**
     * Oblicza wpływ zwolnień lekarskich na wysokość emerytury
     *
     * @param string $gender Płeć (male/female)
     * @param int $yearsToRetirement Lata do emerytury
     * @param int $currentAge Obecny wiek
     * @param float $monthlyPension Obliczona miesięczna emerytura
     * @return array Dane o wpływie zwolnień lekarskich
     */
    private function calculateSickLeaveImpact(
        string $gender,
        int $yearsToRetirement,
        int $currentAge,
        float $monthlyPension
    ): array {
        $workStartAge = (int) config('pension.calculation.default_work_start_age', 25);
        $workingDaysPerYear = (int) config('pension.calculation.working_days_per_year', 250);
        $sickLeaveContributionLoss = (float) config('pension.calculation.sick_leave_contribution_loss', 0.8);

        // Średnia liczba dni zwolnienia rocznie
        $sickDaysPerYear = (int) config(
            "pension.calculation.sick_days_per_year.{$gender}",
            $gender === 'female' ? 12 : 9
        );

        // Całkowity staż pracy (dotychczasowy + przyszły)
        $yearsWorked = max(0, $currentAge - $workStartAge);
        $totalYearsWorked = $yearsWorked + $yearsToRetirement;

        // Łączna liczba dni zwolnienia
        $totalSickDays = $sickDaysPerYear * $totalYearsWorked;

        // Łączna liczba dni roboczych
        $totalWorkingDays = $workingDaysPerYear * $totalYearsWorked;

        // Procent utraty składek
        $contributionLossPercentage = ($totalSickDays / $totalWorkingDays) * 100;

        // Efektywna strata
        $effectiveLoss = $contributionLossPercentage * $sickLeaveContributionLoss;

        // Obniżenie miesięcznej emerytury
        $pensionReduction = $monthlyPension * ($effectiveLoss / 100);

        return [
            'average_days' => round($totalSickDays),
            'pension_reduction' => round($pensionReduction, 2),
            'percentage_reduction' => round($effectiveLoss, 2),
        ];
    }
}
