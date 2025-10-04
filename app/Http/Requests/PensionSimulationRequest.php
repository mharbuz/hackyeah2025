<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PensionSimulationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentYear = date('Y');
        
        return [
            'age' => [
                'required',
                'integer',
                'min:18',
                'max:100',
                function ($attribute, $value, $fail) {
                    $gender = $this->input('gender');
                    $retirementAge = $gender === 'male' ? 65 : 60;
                    
                    if ($value >= $retirementAge) {
                        $fail("Wiek musi być mniejszy niż wiek emerytalny ($retirementAge lat)");
                    }
                },
            ],
            'gender' => 'required|in:male,female',
            'gross_salary' => 'required|numeric|min:1000|max:100000',
            'retirement_year' => [
                'required',
                'integer',
                "min:$currentYear",
                'max:' . ($currentYear + 50),
            ],
            'account_balance' => 'nullable|numeric|min:0|max:10000000',
            'subaccount_balance' => 'nullable|numeric|min:0|max:10000000',
            'include_sick_leave' => 'boolean',
            'forecast_variant' => 'nullable|in:variant_1,variant_2,variant_3',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'age.required' => 'Wiek jest wymagany',
            'age.integer' => 'Wiek musi być liczbą całkowitą',
            'age.min' => 'Wiek musi wynosić co najmniej 18 lat',
            'age.max' => 'Wiek nie może przekraczać 100 lat',
            
            'gender.required' => 'Płeć jest wymagana',
            'gender.in' => 'Nieprawidłowa wartość płci',
            
            'gross_salary.required' => 'Wynagrodzenie brutto jest wymagane',
            'gross_salary.numeric' => 'Wynagrodzenie musi być liczbą',
            'gross_salary.min' => 'Wynagrodzenie musi wynosić co najmniej 1 000 zł',
            'gross_salary.max' => 'Wynagrodzenie nie może przekraczać 100 000 zł',
            
            'retirement_year.required' => 'Rok zakończenia pracy jest wymagany',
            'retirement_year.integer' => 'Rok musi być liczbą całkowitą',
            'retirement_year.min' => 'Rok nie może być wcześniejszy niż bieżący rok',
            'retirement_year.max' => 'Rok przekracza dozwolony zakres',
            
            'account_balance.numeric' => 'Saldo konta musi być liczbą',
            'account_balance.min' => 'Saldo konta nie może być ujemne',
            'account_balance.max' => 'Saldo konta przekracza dozwoloną wartość',
            
            'subaccount_balance.numeric' => 'Saldo subkonta musi być liczbą',
            'subaccount_balance.min' => 'Saldo subkonta nie może być ujemne',
            'subaccount_balance.max' => 'Saldo subkonta przekracza dozwoloną wartość',
            
            'include_sick_leave.boolean' => 'Nieprawidłowa wartość opcji zwolnień lekarskich',
            
            'forecast_variant.in' => 'Nieprawidłowy wariant prognozy (dostępne: variant_1, variant_2, variant_3)',
        ];
    }
}

