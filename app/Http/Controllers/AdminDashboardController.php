<?php

namespace App\Http\Controllers;

use App\Exports\PensionSimulationsExport;
use App\Models\PensionSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

/**
 * Kontroler panelu administratora
 * Zarządza dostępem do danych statystycznych i eksportem raportów
 */
class AdminDashboardController extends Controller
{
    /**
     * Wyświetla panel administratora z statystykami
     */
    public function index(Request $request)
    {
        // Pobierz parametry filtrowania
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Zapytanie bazowe
        $query = PensionSession::query();

        // Zastosuj filtry dat jeśli podane
        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        // Pobierz statystyki
        $totalSimulations = $query->count();
        
        // Statystyki według płci
        $genderStats = $this->getGenderStatistics($query);

        // Ostatnie symulacje
        $recentSimulations = $query->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'date' => $session->created_at->format('Y-m-d H:i:s'),
                    'pension_value' => $session->pension_value,
                    'gender' => $session->form_data['gender'] ?? null,
                    'age' => $this->calculateAge($session->form_data['birthDate'] ?? null),
                    'postal_code' => $session->form_data['postalCode'] ?? $session->form_data['postal_code'] ?? null,
                ];
            });

        return Inertia::render('Admin/Dashboard', [
            'statistics' => [
                'total' => $totalSimulations,
                'gender' => $genderStats,
            ],
            'recentSimulations' => $recentSimulations,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Eksportuje raport symulacji do pliku Excel
     */
    public function exportReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $fileName = 'raport_symulacji_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new PensionSimulationsExport($startDate, $endDate),
            $fileName
        );
    }

    /**
     * Pobiera statystyki według płci
     */
    private function getGenderStatistics($query)
    {
        $sessions = $query->get();
        
        $stats = [
            'male' => 0,
            'female' => 0,
            'unknown' => 0,
        ];

        foreach ($sessions as $session) {
            $gender = $session->form_data['gender'] ?? 'unknown';
            
            if (isset($stats[$gender])) {
                $stats[$gender]++;
            } else {
                $stats['unknown']++;
            }
        }

        return [
            'male' => $stats['male'],
            'female' => $stats['female'],
            'unknown' => $stats['unknown'],
        ];
    }

    /**
     * Oblicza wiek na podstawie daty urodzenia
     */
    private function calculateAge($birthDate)
    {
        if (!$birthDate) {
            return null;
        }

        try {
            return Carbon::parse($birthDate)->age;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Pobiera szczegóły pojedynczej symulacji
     */
    public function show($id)
    {
        $session = PensionSession::findOrFail($id);

        return Inertia::render('Admin/SimulationDetails', [
            'simulation' => [
                'id' => $session->id,
                'date' => $session->created_at->format('Y-m-d H:i:s'),
                'pension_value' => $session->pension_value,
                'form_data' => $session->form_data,
                'simulation_results' => $session->simulation_results,
                'calculation_data' => $session->calculation_data,
            ],
        ]);
    }
}

