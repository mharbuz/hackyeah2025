<?php

namespace App\Services;

use App\Helper\ForecastFileHelper;

/**
 * Serwis odpowiedzialny za obsługę danych o rozkładzie emerytur w Polsce
 *
 * Zarządza danymi statystycznymi dotyczącymi rozkładu wysokości emerytur,
 * dostosowując format danych do różnych urządzeń (desktop/mobile).
 */
class PensionDistributionService
{
    /**
     * Helper do obsługi plików z danymi
     *
     * @var ForecastFileHelper
     */
    private ForecastFileHelper $forecastHelper;

    /**
     * Konstruktor serwisu
     *
     * @param ForecastFileHelper $forecastHelper Helper do wczytywania danych
     */
    public function __construct(ForecastFileHelper $forecastHelper)
    {
        $this->forecastHelper = $forecastHelper;
    }

    /**
     * Zwraca dane o rozkładzie emerytur dla urządzenia desktop
     * Zawiera 26 szczegółowych przedziałów emerytalnych
     *
     * @return array Tablica z 26 grupami emerytalnymi
     */
    public function getDesktopDistribution(): array
    {
        return $this->forecastHelper->getPensionDistributionDesktop();
    }

    /**
     * Zwraca dane o rozkładzie emerytur dla urządzeń mobilnych
     * Zawiera 8 zgrupowanych przedziałów emerytalnych dla lepszej czytelności
     *
     * @return array Tablica z 8 grupami emerytalnymi
     */
    public function getMobileDistribution(): array
    {
        return $this->forecastHelper->getPensionDistributionMobile();
    }

    /**
     * Znajduje grupę emerytalną dla podanej kwoty emerytury
     *
     * @param float $pensionValue Kwota emerytury
     * @param array $groups Tablica grup emerytalnych
     * @return array|null Znaleziona grupa lub null
     */
    public function findPensionGroup(float $pensionValue, array $groups): ?array
    {
        foreach ($groups as $group) {
            if ($pensionValue <= $group['amount']) {
                return $group;
            }
        }

        // Jeśli nie znaleziono grupy, zwróć ostatnią (najwyższą)
        return end($groups) ?: null;
    }

    /**
     * Przygotowuje dane o emeryturze dla wyświetlenia na froncie
     *
     * @param float $pensionValue Kwota emerytury użytkownika
     * @param bool $isMobile Czy to urządzenie mobilne
     * @return array Przygotowane dane dla frontendu
     */
    public function preparePensionData(float $pensionValue, bool $isMobile = false): array
    {
        $pensionGroups = $isMobile 
            ? $this->getMobileDistribution() 
            : $this->getDesktopDistribution();

        $userGroup = $this->findPensionGroup($pensionValue, $pensionGroups);

        $averagePension = 3500; // Średnia krajowa
        $percentageDifference = (($pensionValue - $averagePension) / $averagePension) * 100;

        return [
            'pension_groups' => $pensionGroups,
            'user_group' => $userGroup,
            'average_pension' => $averagePension,
            'percentage_difference' => $percentageDifference,
        ];
    }
}

