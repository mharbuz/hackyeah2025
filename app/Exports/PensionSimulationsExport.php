<?php

namespace App\Exports;

use App\Models\PensionSession;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

/**
 * Klasa odpowiedzialna za eksport danych symulacji emerytalnych do pliku Excel
 */
class PensionSimulationsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    /**
     * Konstruktor z opcjonalnymi parametrami filtrowania
     */
    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Pobiera kolekcję danych do eksportu
     */
    public function collection()
    {
        $query = PensionSession::query()->orderBy('created_at', 'desc');

        if ($this->startDate) {
            $query->where('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->where('created_at', '<=', $this->endDate);
        }

        return $query->get();
    }

    /**
     * Definiuje nagłówki kolumn
     */
    public function headings(): array
    {
        return [
            'Data użycia',
            'Godzina użycia',
            'Emerytura oczekiwana',
            'Wiek',
            'Płeć',
            'Wysokość wynagrodzenia',
            'Czy uwzględniał okresy choroby',
            'Wysokość zgromadzonych środków na koncie i Subkoncie',
            'Emerytura rzeczywista',
            'Emerytura urealniona',
            'Kod pocztowy',
        ];
    }

    /**
     * Mapuje dane modelu na wiersze eksportu
     */
    public function map($session): array
    {
        $formData = $session->form_data ?? [];
        $simulationResults = $session->simulation_results ?? [];
        $calculationData = $session->calculation_data ?? [];

        // Wyodrębnienie danych z formularza
        $birthDate = $formData['birthDate'] ?? null;
        $age = null;
        if ($birthDate) {
            try {
                $age = Carbon::parse($birthDate)->age;
            } catch (\Exception $e) {
                $age = null;
            }
        }

        // Mapowanie płci
        $gender = $formData['gender'] ?? null;
        $genderLabel = match($gender) {
            'male' => 'Mężczyzna',
            'female' => 'Kobieta',
            default => 'Nie podano'
        };

        // Wynagrodzenie
        $salary = $formData['currentSalary'] ?? $formData['salary'] ?? null;

        // Okresy choroby
        $sickLeave = isset($formData['sickLeaveDays']) && $formData['sickLeaveDays'] > 0 ? 'Tak' : 'Nie';

        // Zgromadzone środki
        $accountBalance = ($formData['accountBalance'] ?? 0) + ($formData['subAccountBalance'] ?? 0);

        // Emerytura oczekiwana (z danych symulacji lub form_data)
        $expectedPension = $formData['expectedPension'] ?? 
                          $formData['desired_pension'] ?? 
                          $simulationResults['expectedPension'] ?? 
                          null;

        // Emerytura rzeczywista (obliczona)
        $actualPension = $session->pension_value ?? 
                        $simulationResults['monthlyPension'] ?? 
                        $calculationData['monthlyPension'] ?? 
                        null;

        // Emerytura urealniona (z uwzględnieniem inflacji)
        $realPension = $simulationResults['realMonthlyPension'] ?? 
                      $calculationData['realMonthlyPension'] ?? 
                      $actualPension;

        // Kod pocztowy
        $postalCode = $formData['postalCode'] ?? $formData['postal_code'] ?? 'Nie podano';

        return [
            $session->created_at ? $session->created_at->format('Y-m-d') : '',
            $session->created_at ? $session->created_at->format('H:i:s') : '',
            $expectedPension ? number_format($expectedPension, 2, ',', ' ') . ' zł' : 'Nie podano',
            $age ?? 'Nie podano',
            $genderLabel,
            $salary ? number_format($salary, 2, ',', ' ') . ' zł' : 'Nie podano',
            $sickLeave,
            number_format($accountBalance, 2, ',', ' ') . ' zł',
            $actualPension ? number_format($actualPension, 2, ',', ' ') . ' zł' : 'Nie obliczono',
            $realPension ? number_format($realPension, 2, ',', ' ') . ' zł' : 'Nie obliczono',
            $postalCode,
        ];
    }

    /**
     * Definiuje style arkusza
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '004166'],
                ],
                'font' => [
                    'color' => ['rgb' => 'FFFFFF'],
                    'bold' => true,
                ],
            ],
        ];
    }
}

