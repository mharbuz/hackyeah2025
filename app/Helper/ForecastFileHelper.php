<?php

namespace App\Helper;

/**
 * Klasa pomocnicza do obsługi plików z prognozami ekonomicznymi
 *
 * Umożliwia wczytywanie danych z plików JSON zawierających prognozy
 * dotyczące różnych wskaźników ekonomicznych, takich jak stopa bezrobocia,
 * wskaźnik cen towarów i usług konsumpcyjnych (CPI), realny wzrost wynagrodzeń
 * oraz PKB.
 */
class ForecastFileHelper
{
    // Stopa bezrobocia
    const UNEMPLOYMENT_RATE_FILE_PATH = 'app/private/data/unemployment_rate.json';

    // Ściągalność składek (%)
    const COLLECTION_OF_CONTRIBUTIONS_FILE_PATH = 'app/private/data/collection_of_contributions.json';

    // CPI ogółem – średnioroczny wskaźnik cen (indeks, %)
    const CPI_TOTAL_FILE_PATH = 'app/private/data/cpi_total.json';

    // CPI emeryci/renciści – średnioroczny wskaźnik cen (indeks, %)
    const CPI_FOR_PENSIONERS_FILE_PATH = 'app/private/data/cpi_for_pensioners_index.json';

    // Wskaźnik realnego wzrostu przeciętnego wynagrodzenia (indeks, %)
    const REAL_WAGE_FILE_PATH = 'app/private/data/real_wage_index.json';

    // Wskaźnik realnego wzrostu PKB (indeks, %)
    const REAL_GDP_FILE_PATH = 'app/private/data/real_gdp_index.json';

    /**
     * Zwraca informacje o prognozie stopy bezrobocia
     * @return array
     */
    public function getUnemploymentRate(): array
    {
        return $this->getArrayDataFromJsonFilePath(self::UNEMPLOYMENT_RATE_FILE_PATH);
    }

    /**
     * Zwraca informacje o prognozie ściągalności składek
     * @return array
     */
    public function getCollectionOfContributions(): array
    {
        return $this->getArrayDataFromJsonFilePath(self::COLLECTION_OF_CONTRIBUTIONS_FILE_PATH);
    }

    /**
     * Zwraca informacje o prognozie CPI ogółem
     * @return array
     */
    public function getCpiTotal(): array
    {
        return $this->getArrayDataFromJsonFilePath(self::CPI_TOTAL_FILE_PATH);
    }

    /**
     * Zwraca informacje o prognozie CPI emeryci/renciści
     * @return array
     */
    public function getCpiForPensioners(): array
    {
        return $this->getArrayDataFromJsonFilePath(self::CPI_FOR_PENSIONERS_FILE_PATH);
    }

    /**
     * Zwraca informacje o prognozie realnego wzrostu przeciętnego wynagrodzenia
     * @return array
     */
    public function getRealWage(): array
    {
        return $this->getArrayDataFromJsonFilePath(self::REAL_WAGE_FILE_PATH);
    }

    /**
     * Zwraca informacje o prognozie realnego wzrostu PKB
     * @return array
     */
    public function getRealGdp(): array
    {
        return $this->getArrayDataFromJsonFilePath(self::REAL_GDP_FILE_PATH);
    }

    /**
     * Wczytuje dane z pliku JSON i zwraca je jako tablicę
     *
     * @param string $filePath Ścieżka do pliku JSON
     * @return array Dane z pliku jako tablica asocjacyjna
     */
    protected function getArrayDataFromJsonFilePath(string $filePath): array
    {
        $realPath = storage_path($filePath);

        return json_decode(file_get_contents($realPath), true);
    }
}
