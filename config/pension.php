<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wiek emerytalny
    |--------------------------------------------------------------------------
    |
    | Wiek emerytalny w Polsce według płci. Wartości te mogą się zmieniać
    | w zależności od zmian w przepisach. Aktualizuj te wartości zgodnie
    | z obowiązującym prawem.
    |
    */

    'retirement_age' => [
        'male' => env('PENSION_RETIREMENT_AGE_MALE', 65),
        'female' => env('PENSION_RETIREMENT_AGE_FEMALE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parametry obliczeń emerytalnych
    |--------------------------------------------------------------------------
    |
    | Konfiguracja parametrów używanych w obliczeniach prognozy emerytury.
    |
    */

    'calculation' => [
        // Stawka składki emerytalnej (19.52% wynagrodzenia brutto)
        'contribution_rate' => env('PENSION_CONTRIBUTION_RATE', 0.1952),

        // Średni roczny wzrost wynagrodzeń w Polsce (5%)
        'average_wage_growth' => env('PENSION_WAGE_GROWTH', 0.05),

        // Stopa waloryzacji składek emerytalnych (5% rocznie)
        'valorization_rate' => env('PENSION_VALORIZATION_RATE', 0.05),

        // Wiek rozpoczęcia pracy (domyślnie 25 lat)
        'default_work_start_age' => env('PENSION_WORK_START_AGE', 25),

        // Oczekiwana długość życia po emeryturze w latach
        'life_expectancy' => [
            'female' => env('PENSION_LIFE_EXPECTANCY_FEMALE', 25),
            'male' => env('PENSION_LIFE_EXPECTANCY_MALE', 20),
        ],

        // Średnia liczba dni zwolnienia lekarskiego rocznie
        'sick_days_per_year' => [
            'female' => env('PENSION_SICK_DAYS_FEMALE', 12),
            'male' => env('PENSION_SICK_DAYS_MALE', 9),
        ],

        // Liczba dni roboczych w roku
        'working_days_per_year' => env('PENSION_WORKING_DAYS_PER_YEAR', 250),

        // Procent utraty składki podczas zwolnienia (80%)
        'sick_leave_contribution_loss' => env('PENSION_SICK_LEAVE_LOSS', 0.8),

        // Procent subkonta względem głównego konta (25%)
        'subaccount_percentage' => env('PENSION_SUBACCOUNT_PERCENTAGE', 0.25),

        // Domyślna stopa zwrotu z inwestycji dla obliczeń oszczędności (6%)
        'default_investment_return_rate' => env('PENSION_INVESTMENT_RETURN_RATE', 0.06),
    ],

    /*
    |--------------------------------------------------------------------------
    | Walidacja
    |--------------------------------------------------------------------------
    |
    | Limity walidacji dla formularza symulacji.
    |
    */

    'validation' => [
        'age' => [
            'min' => 18,
            'max' => 100,
        ],
        'salary' => [
            'min' => 1000,
            'max' => 100000,
        ],
        'balance' => [
            'min' => 0,
            'max' => 10000000,
        ],
        'retirement_year' => [
            'min_offset' => 0, // Bieżący rok
            'max_offset' => 50, // +50 lat od bieżącego
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warianty prognozy ZUS
    |--------------------------------------------------------------------------
    |
    | Definicje wariantów prognoz ekonomicznych używanych w obliczeniach.
    | Warianty bazują na oficjalnych prognozach ZUS do 2080 roku.
    |
    */

    'forecast_variants' => [
        'variant_1' => [
            'name' => 'Wariant pośredni',
            'description' => 'Średni scenariusz rozwoju gospodarczego',
        ],
        'variant_2' => [
            'name' => 'Wariant pesymistyczny',
            'description' => 'Scenariusz zakładający wolniejszy wzrost gospodarczy',
        ],
        'variant_3' => [
            'name' => 'Wariant optymistyczny',
            'description' => 'Scenariusz zakładający szybszy wzrost gospodarczy',
        ],
    ],

    // Domyślny wariant używany w obliczeniach
    'default_forecast_variant' => env('PENSION_DEFAULT_FORECAST_VARIANT', 'variant_1'),
];

