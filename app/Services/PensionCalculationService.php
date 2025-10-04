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
 */
class PensionCalculationService
{
    /**
     * Stawka składki emerytalnej (19.52% wynagrodzenia brutto)
     */
    private const PENSION_CONTRIBUTION_RATE = 0.1952;

    /**
     * Średni roczny wzrost wynagrodzeń w Polsce (5%)
     */
    private const AVERAGE_WAGE_GROWTH = 0.05;

    /**
     * Stopa waloryzacji składek emerytalnych (5% rocznie)
     */
    private const VALORIZATION_RATE = 0.05;

    /**
     * Wiek rozpoczęcia pracy (domyślnie 25 lat)
     */
    private const DEFAULT_WORK_START_AGE = 25;

    /**
     * Oczekiwana długość życia po emeryturze w latach
     */
    private const LIFE_EXPECTANCY_FEMALE = 25;
    private const LIFE_EXPECTANCY_MALE = 20;

    /**
     * Średnia liczba dni zwolnienia lekarskiego rocznie
     */
    private const SICK_DAYS_PER_YEAR_FEMALE = 12;
    private const SICK_DAYS_PER_YEAR_MALE = 9;

    /**
     * Liczba dni roboczych w roku
     */
    private const WORKING_DAYS_PER_YEAR = 250;

    /**
     * Procent utraty składki podczas zwolnienia (80%)
     */
    private const SICK_LEAVE_CONTRIBUTION_LOSS = 0.8;

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
            $estimatedYearsWorked = max(0, $age - self::DEFAULT_WORK_START_AGE);
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
     * @param string $gender Płeć (male/female)
     * @return int Wiek emerytalny
     */
    public function getRetirementAge(string $gender): int
    {
        return $gender === 'male' ? 65 : 60;
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

        $totalBalance = 0;

        // Rekonstrukcja historii wynagrodzeń i składek
        for ($i = 0; $i < $yearsWorked; $i++) {
            $yearsAgo = $yearsWorked - $i;
            
            // Wynagrodzenie w danym roku (odwrócona projekcja wzrostu)
            $historicalSalary = $currentSalary / pow(1 + self::AVERAGE_WAGE_GROWTH, $yearsAgo);
            
            // Roczna składka
            $yearlyContribution = $historicalSalary * self::PENSION_CONTRIBUTION_RATE * 12;
            
            // Waloryzacja składki do dnia dzisiejszego
            $valorizedContribution = $yearlyContribution * pow(1 + self::VALORIZATION_RATE, $yearsAgo);
            
            $totalBalance += $valorizedContribution;
        }

        return $totalBalance;
    }

    /**
     * Szacuje saldo subkonta na podstawie salda głównego konta
     *
     * @param float $accountBalance Saldo głównego konta
     * @return float Szacowane saldo subkonta (25% głównego konta)
     */
    private function estimateSubaccountBalance(float $accountBalance): float
    {
        return $accountBalance * 0.25;
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
        $totalContributions = 0;

        for ($i = 1; $i <= $years; $i++) {
            // Prognozowane wynagrodzenie za i lat
            $futureSalary = $currentSalary * pow(1 + self::AVERAGE_WAGE_GROWTH, $i);
            
            // Roczna składka
            $yearlyContribution = $futureSalary * self::PENSION_CONTRIBUTION_RATE * 12;
            
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
        $yearsLifeExpectancy = $gender === 'female' 
            ? self::LIFE_EXPECTANCY_FEMALE 
            : self::LIFE_EXPECTANCY_MALE;
            
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
        // Średnia liczba dni zwolnienia rocznie
        $sickDaysPerYear = $gender === 'female' 
            ? self::SICK_DAYS_PER_YEAR_FEMALE 
            : self::SICK_DAYS_PER_YEAR_MALE;

        // Całkowity staż pracy (dotychczasowy + przyszły)
        $yearsWorked = max(0, $currentAge - self::DEFAULT_WORK_START_AGE);
        $totalYearsWorked = $yearsWorked + $yearsToRetirement;
        
        // Łączna liczba dni zwolnienia
        $totalSickDays = $sickDaysPerYear * $totalYearsWorked;
        
        // Łączna liczba dni roboczych
        $totalWorkingDays = self::WORKING_DAYS_PER_YEAR * $totalYearsWorked;
        
        // Procent utraty składek
        $contributionLossPercentage = ($totalSickDays / $totalWorkingDays) * 100;
        
        // Efektywna strata (80% wartości składki podczas zwolnienia)
        $effectiveLoss = $contributionLossPercentage * self::SICK_LEAVE_CONTRIBUTION_LOSS;
        
        // Obniżenie miesięcznej emerytury
        $pensionReduction = $monthlyPension * ($effectiveLoss / 100);

        return [
            'average_days' => round($totalSickDays),
            'pension_reduction' => round($pensionReduction, 2),
            'percentage_reduction' => round($effectiveLoss, 2),
        ];
    }
}

