<?php

namespace App\Services;

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
     * Oblicza prognozowaną emeryturę na podstawie danych użytkownika
     *
     * @param int $age Wiek użytkownika
     * @param string $gender Płeć (male/female)
     * @param float $grossSalary Miesięczne wynagrodzenie brutto
     * @param int $retirementYear Planowany rok przejścia na emeryturę
     * @param float|null $accountBalance Zgromadzone środki na koncie w ZUS
     * @param float|null $subaccountBalance Zgromadzone środki na subkoncie w ZUS
     * @param bool $includeSickLeave Czy uwzględnić zwolnienia lekarskie
     * @return array Tablica z wynikami prognozy
     */
    public function calculatePension(
        int $age,
        string $gender,
        float $grossSalary,
        int $retirementYear,
        ?float $accountBalance = null,
        ?float $subaccountBalance = null,
        bool $includeSickLeave = false
    ): array {
        $retirementAge = $this->getRetirementAge($gender);
        $yearsToRetirement = $retirementAge - $age;

        // Oszacuj salda, jeśli nie podano
        if ($accountBalance === null) {
            $workStartAge = (int) config('pension.calculation.default_work_start_age', 25);
            $estimatedYearsWorked = max(0, $age - $workStartAge);
            $accountBalance = $this->estimateAccountBalance(
                $grossSalary,
                $estimatedYearsWorked
            );
        }

        if ($subaccountBalance === null) {
            $subaccountBalance = $this->estimateSubaccountBalance($accountBalance);
        }

        // Oblicz przyszłe składki
        $futureContributions = $this->calculateFutureContributions(
            $grossSalary,
            $yearsToRetirement
        );

        // Łączny kapitał emerytalny
        $totalCapital = $accountBalance + $subaccountBalance + $futureContributions;

        // Oblicz miesięczną emeryturę
        $lifeExpectancyMonths = $this->getLifeExpectancyMonths($gender);
        $monthlyPension = $totalCapital / $lifeExpectancyMonths;

        // Wpływ zwolnień lekarskich
        $sickLeaveImpact = null;
        if ($includeSickLeave) {
            $sickLeaveImpact = $this->calculateSickLeaveImpact(
                $gender,
                $yearsToRetirement,
                $age,
                $monthlyPension
            );
            $monthlyPension -= $sickLeaveImpact['pension_reduction'];
        }

        return [
            'monthly_pension' => round($monthlyPension, 2),
            'total_contributions' => round($totalCapital, 2),
            'years_to_retirement' => $yearsToRetirement,
            'sick_leave_impact' => $sickLeaveImpact,
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
     *
     * @param float $currentSalary Obecne wynagrodzenie
     * @param int $yearsWorked Liczba przepracowanych lat
     * @return float Szacowane saldo konta
     */
    private function estimateAccountBalance(float $currentSalary, int $yearsWorked): float
    {
        if ($yearsWorked <= 0) {
            return 0;
        }

        $contributionRate = (float) config('pension.calculation.contribution_rate', 0.1952);
        $wageGrowth = (float) config('pension.calculation.average_wage_growth', 0.05);
        $valorizationRate = (float) config('pension.calculation.valorization_rate', 0.05);

        $totalBalance = 0;

        // Rekonstrukcja historii wynagrodzeń i składek
        for ($i = 0; $i < $yearsWorked; $i++) {
            $yearsAgo = $yearsWorked - $i;
            
            // Wynagrodzenie w danym roku (odwrócona projekcja wzrostu)
            $historicalSalary = $currentSalary / pow(1 + $wageGrowth, $yearsAgo);
            
            // Roczna składka
            $yearlyContribution = $historicalSalary * $contributionRate * 12;
            
            // Waloryzacja składki do dnia dzisiejszego
            $valorizedContribution = $yearlyContribution * pow(1 + $valorizationRate, $yearsAgo);
            
            $totalBalance += $valorizedContribution;
        }

        return $totalBalance;
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
     *
     * @param float $currentSalary Obecne wynagrodzenie
     * @param int $years Liczba lat do emerytury
     * @return float Suma przyszłych składek
     */
    private function calculateFutureContributions(float $currentSalary, int $years): float
    {
        $contributionRate = (float) config('pension.calculation.contribution_rate', 0.1952);
        $wageGrowth = (float) config('pension.calculation.average_wage_growth', 0.05);
        
        $totalContributions = 0;

        for ($i = 1; $i <= $years; $i++) {
            // Prognozowane wynagrodzenie za i lat
            $futureSalary = $currentSalary * pow(1 + $wageGrowth, $i);
            
            // Roczna składka
            $yearlyContribution = $futureSalary * $contributionRate * 12;
            
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
