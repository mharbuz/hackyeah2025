<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { home } from '@/routes';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';

// Typy
interface FormData {
    age: string;
    gender: 'male' | 'female' | '';
    gross_salary: string;
    retirement_year: string;
    account_balance: string;
    subaccount_balance: string;
    include_sick_leave: boolean;
}

interface ValidationErrors {
    age?: string;
    gender?: string;
    gross_salary?: string;
    retirement_year?: string;
    account_balance?: string;
    subaccount_balance?: string;
}

interface SimulationResult {
    monthly_pension: number;
    total_contributions: number;
    years_to_retirement: number;
    sick_leave_impact?: {
        average_days: number;
        pension_reduction: number;
        percentage_reduction: number;
    };
    forecast_variant?: string;
    economic_context?: {
        future_gross_salary: number;
        replacement_rate: number;
        purchasing_power_today: number;
        avg_gdp_growth: number;
        avg_unemployment_rate: number;
        cumulative_inflation: number;
        pension_forecast_10years: Array<{
            year: number;
            pension_nominal: number;
            pension_real: number;
        }>;
        variant_name: string;
    };
}

// Stan formularza
const formData = ref<FormData>({
    age: '',
    gender: '',
    gross_salary: '',
    retirement_year: '',
    account_balance: '',
    subaccount_balance: '',
    include_sick_leave: false
});

const errors = ref<ValidationErrors>({});
const isSubmitting = ref(false);
const showResults = ref(false);
const simulationResult = ref<SimulationResult | null>(null);

// Obliczenie wieku emerytalnego
const retirementAge = computed(() => {
    if (formData.value.gender === 'male') return 65;
    if (formData.value.gender === 'female') return 60;
    return null;
});

// Obliczenie roku emerytalnego na podstawie wieku i płci
watch([() => formData.value.age, () => formData.value.gender], () => {
    if (formData.value.age && retirementAge.value) {
        const currentYear = new Date().getFullYear();
        const age = parseInt(formData.value.age);
        const yearsToRetirement = retirementAge.value - age;
        
        if (yearsToRetirement >= 0) {
            formData.value.retirement_year = (currentYear + yearsToRetirement).toString();
        }
    }
});

// Walidacja wieku
const validateAge = (): boolean => {
    const age = parseInt(formData.value.age);
    if (!formData.value.age) {
        errors.value.age = 'Wiek jest wymagany';
        return false;
    }
    if (age < 18 || age > 100) {
        errors.value.age = 'Wiek musi być w przedziale 18-100 lat';
        return false;
    }
    if (retirementAge.value && age >= retirementAge.value) {
        errors.value.age = `Wiek musi być mniejszy niż wiek emerytalny (${retirementAge.value} lat)`;
        return false;
    }
    delete errors.value.age;
    return true;
};

// Walidacja płci
const validateGender = (): boolean => {
    if (!formData.value.gender) {
        errors.value.gender = 'Płeć jest wymagana';
        return false;
    }
    delete errors.value.gender;
    return true;
};

// Walidacja wynagrodzenia
const validateSalary = (): boolean => {
    const salary = parseFloat(formData.value.gross_salary);
    if (!formData.value.gross_salary) {
        errors.value.gross_salary = 'Wynagrodzenie brutto jest wymagane';
        return false;
    }
    if (salary < 1000 || salary > 100000) {
        errors.value.gross_salary = 'Wynagrodzenie musi być w przedziale 1 000 - 100 000 zł';
        return false;
    }
    delete errors.value.gross_salary;
    return true;
};

// Walidacja roku emerytalnego
const validateRetirementYear = (): boolean => {
    const year = parseInt(formData.value.retirement_year);
    const currentYear = new Date().getFullYear();
    if (!formData.value.retirement_year) {
        errors.value.retirement_year = 'Rok zakończenia pracy jest wymagany';
        return false;
    }
    if (year < currentYear || year > currentYear + 50) {
        errors.value.retirement_year = `Rok musi być w przedziale ${currentYear}-${currentYear + 50}`;
        return false;
    }
    delete errors.value.retirement_year;
    return true;
};

// Walidacja salda konta
const validateAccountBalance = (): boolean => {
    if (formData.value.account_balance) {
        const balance = parseFloat(formData.value.account_balance);
        if (balance < 0 || balance > 10000000) {
            errors.value.account_balance = 'Saldo musi być w przedziale 0 - 10 000 000 zł';
            return false;
        }
    }
    delete errors.value.account_balance;
    return true;
};

// Walidacja salda subkonta
const validateSubaccountBalance = (): boolean => {
    if (formData.value.subaccount_balance) {
        const balance = parseFloat(formData.value.subaccount_balance);
        if (balance < 0 || balance > 10000000) {
            errors.value.subaccount_balance = 'Saldo musi być w przedziale 0 - 10 000 000 zł';
            return false;
        }
    }
    delete errors.value.subaccount_balance;
    return true;
};

// Walidacja całego formularza
const validateForm = (): boolean => {
    const validations = [
        validateAge(),
        validateGender(),
        validateSalary(),
        validateRetirementYear(),
        validateAccountBalance(),
        validateSubaccountBalance()
    ];
    
    return validations.every(v => v);
};

// Formatowanie waluty
const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency: 'PLN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
};

// Obsługa wysyłki formularza
const handleSubmit = async () => {
    errors.value = {};
    
    if (!validateForm()) {
        return;
    }
    
    isSubmitting.value = true;
    
    try {
        const response = await fetch('/api/pension/simulate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                age: parseInt(formData.value.age),
                gender: formData.value.gender,
                gross_salary: parseFloat(formData.value.gross_salary),
                retirement_year: parseInt(formData.value.retirement_year),
                account_balance: formData.value.account_balance ? parseFloat(formData.value.account_balance) : null,
                subaccount_balance: formData.value.subaccount_balance ? parseFloat(formData.value.subaccount_balance) : null,
                include_sick_leave: formData.value.include_sick_leave
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            if (data.errors) {
                errors.value = data.errors;
            } else {
                throw new Error(data.message || 'Wystąpił błąd podczas przetwarzania');
            }
            return;
        }
        
        simulationResult.value = data;
        showResults.value = true;
        
        // Przewiń do wyników
        setTimeout(() => {
            document.getElementById('results')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
        
    } catch (error) {
        console.error('Błąd symulacji:', error);
        alert('Wystąpił błąd podczas przetwarzania. Spróbuj ponownie.');
    } finally {
        isSubmitting.value = false;
    }
};

// Reset formularza
const resetForm = () => {
    formData.value = {
        age: '',
        gender: '',
        gross_salary: '',
        retirement_year: '',
        account_balance: '',
        subaccount_balance: '',
        include_sick_leave: false
    };
    errors.value = {};
    showResults.value = false;
    simulationResult.value = null;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
</script>

<template>
    <Head title="Symulacja przyszłej emerytury - ZUS">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    
    <div class="min-h-screen bg-gradient-to-br from-[rgb(0,65,110)] via-[rgb(63,132,210)] to-[rgb(0,153,63)]">
        <!-- Header -->
        <header class="bg-white/10 backdrop-blur-md border-b border-white/20 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <nav class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Link :href="home()" class="group flex items-center gap-3 bg-white rounded-xl px-5 py-3 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                            <div class="w-8 h-8 bg-gradient-to-br from-[rgb(0,65,110)] to-[rgb(63,132,210)] rounded-lg flex items-center justify-center text-white font-bold text-sm">
                                ZUS
                            </div>
                            <span class="font-bold text-[rgb(0,65,110)] text-lg hidden sm:block">Symulator</span>
                        </Link>
                        <div class="hidden md:block">
                            <h1 class="text-white text-xl font-bold drop-shadow-lg">Symulacja przyszłej emerytury</h1>
                        </div>
                    </div>
                    <Link
                        :href="home()"
                        class="flex items-center gap-2 rounded-xl bg-white/90 backdrop-blur px-5 py-3 text-base font-semibold text-[rgb(0,65,110)] hover:bg-white transition-all duration-300 shadow-lg hover:shadow-xl"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Powrót</span>
                    </Link>
                </nav>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
            <!-- Hero Section -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full mb-4">
                    <div class="w-2 h-2 bg-[rgb(0,153,63)] rounded-full animate-pulse"></div>
                    <span class="text-white text-sm font-semibold">Prognoza emerytury</span>
                </div>
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-4 drop-shadow-lg">
                    Zaplanuj swoją przyszłość
                </h2>
                <p class="text-white/90 text-lg md:text-xl max-w-2xl mx-auto drop-shadow">
                    Poznaj szacunkową wysokość swojej przyszłej emerytury w kilka minut
                </p>
            </div>

            <!-- Formularz -->
            <Card class="shadow-2xl border-none overflow-hidden backdrop-blur-sm bg-white/95 mb-8">
                <CardHeader class="bg-white border-b-4 border-[rgb(0,153,63)] relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-[rgb(0,153,63)]/5 to-[rgb(63,132,210)]/5"></div>
                    <div class="relative">
                        <CardTitle class="text-2xl md:text-3xl font-bold flex items-center gap-3 text-[rgb(0,65,110)]">
                            <div class="w-12 h-12 bg-gradient-to-br from-[rgb(0,153,63)] to-[rgb(0,65,110)] rounded-xl flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span>Twoje dane</span>
                        </CardTitle>
                        <CardDescription class="text-gray-600 text-base md:text-lg mt-3 ml-15">
                            Wypełnij formularz, aby otrzymać spersonalizowaną prognozę
                        </CardDescription>
                    </div>
                </CardHeader>
                
                <CardContent class="p-6 md:p-8 lg:p-10">
                    <form @submit.prevent="handleSubmit" class="space-y-10">
                        <!-- Sekcja: Dane podstawowe -->
                        <div class="space-y-6">
                            <div class="flex items-center gap-3 pb-3 border-b-2 border-[rgb(0,153,63)]">
                                <div class="w-8 h-8 bg-gradient-to-br from-[rgb(0,153,63)] to-[rgb(0,65,110)] rounded-lg flex items-center justify-center text-white font-bold text-sm">
                                    1
                                </div>
                                <h3 class="text-xl md:text-2xl font-bold text-[rgb(0,65,110)]">
                                    Informacje podstawowe
                                </h3>
                            </div>
                            
                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Wiek -->
                                <div class="space-y-3">
                                    <Label for="age" class="text-base font-semibold text-[rgb(0,65,110)] flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Wiek <span class="text-[rgb(240,94,94)]">*</span></span>
                                    </Label>
                                    <Input
                                        id="age"
                                        v-model="formData.age"
                                        type="number"
                                        min="18"
                                        max="100"
                                        placeholder="np. 35"
                                        @blur="validateAge"
                                        :class="[
                                            'text-lg h-14 transition-all duration-200',
                                            errors.age ? 'border-[rgb(240,94,94)] border-2 focus-visible:ring-[rgb(240,94,94)] shake' : 'border-[rgb(190,195,206)] focus-visible:ring-[rgb(0,153,63)]'
                                        ]"
                                        required
                                    />
                                    <p v-if="errors.age" class="text-[rgb(240,94,94)] text-sm font-medium flex items-center gap-2 animate-fadeIn">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        {{ errors.age }}
                                    </p>
                                </div>

                                <!-- Płeć -->
                                <div class="space-y-3">
                                    <Label class="text-base font-semibold text-[rgb(0,65,110)] flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                        </svg>
                                        <span>Płeć <span class="text-[rgb(240,94,94)]">*</span></span>
                                    </Label>
                                    <div class="flex gap-3">
                                        <button
                                            type="button"
                                            @click="formData.gender = 'male'; validateGender()"
                                            :class="[
                                                'flex-1 h-14 rounded-xl border-2 font-semibold text-base transition-all duration-300 transform hover:scale-105',
                                                formData.gender === 'male'
                                                    ? 'bg-gradient-to-br from-[rgb(63,132,210)] to-[rgb(0,65,110)] text-white border-transparent shadow-lg'
                                                    : 'bg-white text-[rgb(0,65,110)] border-[rgb(190,195,206)] hover:border-[rgb(63,132,210)] hover:shadow-md',
                                                errors.gender ? 'border-[rgb(240,94,94)]' : ''
                                            ]"
                                        >
                                            Mężczyzna
                                        </button>
                                        <button
                                            type="button"
                                            @click="formData.gender = 'female'; validateGender()"
                                            :class="[
                                                'flex-1 h-14 rounded-xl border-2 font-semibold text-base transition-all duration-300 transform hover:scale-105',
                                                formData.gender === 'female'
                                                    ? 'bg-gradient-to-br from-[rgb(63,132,210)] to-[rgb(0,65,110)] text-white border-transparent shadow-lg'
                                                    : 'bg-white text-[rgb(0,65,110)] border-[rgb(190,195,206)] hover:border-[rgb(63,132,210)] hover:shadow-md',
                                                errors.gender ? 'border-[rgb(240,94,94)]' : ''
                                            ]"
                                        >
                                            Kobieta
                                        </button>
                                    </div>
                                    <p v-if="retirementAge" class="text-[rgb(0,153,63)] text-sm font-medium flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        Wiek emerytalny: {{ retirementAge }} lat
                                    </p>
                                    <p v-if="errors.gender" class="text-[rgb(240,94,94)] text-sm font-medium flex items-center gap-2 animate-fadeIn">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        {{ errors.gender }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Wynagrodzenie brutto -->
                                <div class="space-y-3">
                                    <Label for="gross_salary" class="text-base font-semibold text-[rgb(0,65,110)] flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Wynagrodzenie brutto (mies.) <span class="text-[rgb(240,94,94)]">*</span></span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="gross_salary"
                                            v-model="formData.gross_salary"
                                            type="number"
                                            step="100"
                                            min="1000"
                                            placeholder="np. 5000"
                                            @blur="validateSalary"
                                            :class="[
                                                'text-lg h-14 pr-12 transition-all duration-200',
                                                errors.gross_salary ? 'border-[rgb(240,94,94)] border-2 focus-visible:ring-[rgb(240,94,94)] shake' : 'border-[rgb(190,195,206)] focus-visible:ring-[rgb(0,153,63)]'
                                            ]"
                                            required
                                        />
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-gray-400">zł</span>
                                    </div>
                                    <p v-if="errors.gross_salary" class="text-[rgb(240,94,94)] text-sm font-medium flex items-center gap-2 animate-fadeIn">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        {{ errors.gross_salary }}
                                    </p>
                                </div>

                                <!-- Planowany rok zakończenia pracy -->
                                <div class="space-y-3">
                                    <Label for="retirement_year" class="text-base font-semibold text-[rgb(0,65,110)] flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Planowany rok emerytury <span class="text-[rgb(240,94,94)]">*</span></span>
                                    </Label>
                                    <Input
                                        id="retirement_year"
                                        v-model="formData.retirement_year"
                                        type="number"
                                        :min="new Date().getFullYear()"
                                        placeholder="np. 2055"
                                        @blur="validateRetirementYear"
                                        :class="[
                                            'text-lg h-14 transition-all duration-200',
                                            errors.retirement_year ? 'border-[rgb(240,94,94)] border-2 focus-visible:ring-[rgb(240,94,94)] shake' : 'border-[rgb(190,195,206)] focus-visible:ring-[rgb(0,153,63)]'
                                        ]"
                                        required
                                    />
                                    <p v-if="errors.retirement_year" class="text-[rgb(240,94,94)] text-sm font-medium flex items-center gap-2 animate-fadeIn">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        {{ errors.retirement_year }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Sekcja: Dane fakultatywne -->
                        <div class="space-y-6 bg-gradient-to-br from-[rgb(190,195,206)]/10 to-[rgb(255,179,79)]/5 p-6 md:p-8 rounded-2xl border-2 border-dashed border-[rgb(190,195,206)]">
                            <div class="flex items-center gap-3 pb-3 border-b-2 border-[rgb(255,179,79)]">
                                <div class="w-8 h-8 bg-gradient-to-br from-[rgb(255,179,79)] to-[rgb(255,179,79)]/80 rounded-lg flex items-center justify-center text-white font-bold text-sm">
                                    2
                                </div>
                                <h3 class="text-xl md:text-2xl font-bold text-[rgb(0,65,110)]">
                                    Informacje dodatkowe <span class="text-sm font-normal text-gray-500">(opcjonalnie)</span>
                                </h3>
                            </div>
                            
                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Saldo konta w ZUS -->
                                <div class="space-y-3">
                                    <Label for="account_balance" class="text-base font-semibold text-[rgb(0,65,110)] flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                            <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Saldo konta w ZUS</span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="account_balance"
                                            v-model="formData.account_balance"
                                            type="number"
                                            step="100"
                                            min="0"
                                            placeholder="np. 50000"
                                            @blur="validateAccountBalance"
                                            :class="[
                                                'text-lg h-14 pr-12 transition-all duration-200',
                                                errors.account_balance ? 'border-[rgb(240,94,94)] border-2 focus-visible:ring-[rgb(240,94,94)]' : 'border-[rgb(190,195,206)] focus-visible:ring-[rgb(255,179,79)]'
                                            ]"
                                        />
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-gray-400">zł</span>
                                    </div>
                                    <p v-if="errors.account_balance" class="text-[rgb(240,94,94)] text-sm font-medium flex items-center gap-2 animate-fadeIn">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        {{ errors.account_balance }}
                                    </p>
                                </div>

                                <!-- Saldo subkonta w ZUS -->
                                <div class="space-y-3">
                                    <Label for="subaccount_balance" class="text-base font-semibold text-[rgb(0,65,110)] flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                            <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Saldo subkonta w ZUS</span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="subaccount_balance"
                                            v-model="formData.subaccount_balance"
                                            type="number"
                                            step="100"
                                            min="0"
                                            placeholder="np. 20000"
                                            @blur="validateSubaccountBalance"
                                            :class="[
                                                'text-lg h-14 pr-12 transition-all duration-200',
                                                errors.subaccount_balance ? 'border-[rgb(240,94,94)] border-2 focus-visible:ring-[rgb(240,94,94)]' : 'border-[rgb(190,195,206)] focus-visible:ring-[rgb(255,179,79)]'
                                            ]"
                                        />
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-gray-400">zł</span>
                                    </div>
                                    <p v-if="errors.subaccount_balance" class="text-[rgb(240,94,94)] text-sm font-medium flex items-center gap-2 animate-fadeIn">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        {{ errors.subaccount_balance }}
                                    </p>
                                </div>
                            </div>

                            <div class="bg-white/50 backdrop-blur-sm p-4 rounded-xl text-sm text-gray-600 flex items-start gap-3 border border-[rgb(190,195,206)]/30">
                                <svg class="w-5 h-5 text-[rgb(255,179,79)] flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <p>
                                    Jeśli nie znasz dokładnych wartości zgromadzonych środków, zostaw puste pola - oszacujemy je na podstawie Twojego wynagrodzenia i wieku
                                </p>
                            </div>

                            <!-- Zwolnienia lekarskie -->
                            <div class="space-y-3 bg-white p-5 rounded-xl border-2 border-[rgb(190,195,206)] hover:border-[rgb(0,153,63)] transition-all duration-300">
                                <div class="flex items-start gap-3">
                                    <Checkbox
                                        id="include_sick_leave"
                                        v-model:checked="formData.include_sick_leave"
                                        class="mt-1 w-6 h-6 data-[state=checked]:bg-[rgb(0,153,63)] data-[state=checked]:border-[rgb(0,153,63)]"
                                    />
                                    <div class="flex-1">
                                        <Label for="include_sick_leave" class="text-base font-semibold text-[rgb(0,65,110)] cursor-pointer flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                            </svg>
                                            Uwzględnij zwolnienia lekarskie
                                        </Label>
                                        <p class="text-sm text-gray-600 mt-1.5 leading-relaxed">
                                            Symulacja uwzględni średnią długość zwolnień lekarskich w ciągu kariery zawodowej i ich realny wpływ na wysokość emerytury
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Przycisk akcji -->
                        <div class="pt-6">
                            <Button
                                type="submit"
                                :disabled="isSubmitting"
                                class="w-full h-16 text-xl font-bold bg-gradient-to-r from-[rgb(0,153,63)] to-[rgb(0,65,110)] hover:from-[rgb(0,65,110)] hover:to-[rgb(0,153,63)] text-white transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                            >
                                <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-3 h-6 w-6 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                {{ isSubmitting ? 'Obliczam prognozę...' : 'Zaprognozuj moją przyszłą emeryturę' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Wyniki symulacji -->
            <div v-if="showResults && simulationResult" id="results" class="space-y-6 animate-slideUp">
                <!-- Główny wynik -->
                <Card class="shadow-2xl border-none overflow-hidden bg-gradient-to-br from-[rgb(0,153,63)] via-[rgb(0,65,110)] to-[rgb(63,132,210)] text-white relative">
                    <div class="absolute inset-0 bg-grid-white/5"></div>
                    <CardContent class="relative p-8 md:p-12 text-center">
                        <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full mb-4">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            <span class="text-sm font-bold">Twoja prognoza</span>
                        </div>
                        <h2 class="text-xl md:text-2xl font-bold mb-3 opacity-90">Prognozowana miesięczna emerytura</h2>
                        <div class="text-5xl md:text-7xl font-bold mb-4 drop-shadow-lg">
                            {{ formatCurrency(simulationResult.monthly_pension) }}
                        </div>
                        <div class="flex items-center justify-center gap-3 text-lg md:text-xl opacity-90">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                            <span>
                                Za {{ simulationResult.years_to_retirement }} {{ simulationResult.years_to_retirement === 1 ? 'rok' : (simulationResult.years_to_retirement < 5 ? 'lata' : 'lat') }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Szczegóły -->
                <div class="grid md:grid-cols-2 gap-6">
                    <Card class="shadow-xl border-none hover:shadow-2xl transition-all duration-300 transform hover:scale-105 bg-white/95 backdrop-blur-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-[rgb(0,65,110)] flex items-center gap-2">
                                <div class="w-10 h-10 bg-gradient-to-br from-[rgb(0,153,63)] to-[rgb(0,153,63)]/80 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                        <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span>Składki emerytalne</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-4xl font-bold text-[rgb(0,153,63)] mb-2">
                                {{ formatCurrency(simulationResult.total_contributions) }}
                            </div>
                            <p class="text-gray-600 leading-relaxed">
                                Łączna wartość składek odprowadzonych do emerytury (zgromadzone + przyszłe)
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="shadow-xl border-none hover:shadow-2xl transition-all duration-300 transform hover:scale-105 bg-white/95 backdrop-blur-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-[rgb(0,65,110)] flex items-center gap-2">
                                <div class="w-10 h-10 bg-gradient-to-br from-[rgb(63,132,210)] to-[rgb(63,132,210)]/80 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span>Czas do emerytury</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-4xl font-bold text-[rgb(63,132,210)] mb-2">
                                {{ simulationResult.years_to_retirement }} {{ simulationResult.years_to_retirement === 1 ? 'rok' : (simulationResult.years_to_retirement < 5 ? 'lata' : 'lat') }}
                            </div>
                            <p class="text-gray-600 leading-relaxed">
                                Czas pozostały do osiągnięcia wieku emerytalnego i przejścia na emeryturę
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Kontekst ekonomiczny -->
                <div v-if="simulationResult.economic_context" class="grid md:grid-cols-3 gap-6">
                    <!-- Współczynnik zastąpienia -->
                    <Card class="shadow-xl border-none hover:shadow-2xl transition-all duration-300 transform hover:scale-105 bg-gradient-to-br from-[rgb(63,132,210)]/10 to-white backdrop-blur-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-[rgb(0,65,110)] text-base flex items-center gap-2">
                                <div class="w-8 h-8 bg-gradient-to-br from-[rgb(63,132,210)] to-[rgb(63,132,210)]/80 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                    </svg>
                                </div>
                                <span>Współczynnik zastąpienia</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-[rgb(63,132,210)] mb-2">
                                {{ simulationResult.economic_context.replacement_rate.toFixed(1) }}%
                            </div>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Stosunek emerytury do ostatniego wynagrodzenia
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Siła nabywcza -->
                    <Card class="shadow-xl border-none hover:shadow-2xl transition-all duration-300 transform hover:scale-105 bg-gradient-to-br from-[rgb(255,179,79)]/10 to-white backdrop-blur-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-[rgb(0,65,110)] text-base flex items-center gap-2">
                                <div class="w-8 h-8 bg-gradient-to-br from-[rgb(255,179,79)] to-[rgb(255,179,79)]/80 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span>Siła nabywcza dzisiaj</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-[rgb(255,179,79)] mb-2">
                                {{ formatCurrency(simulationResult.economic_context.purchasing_power_today) }}
                            </div>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Wartość Twojej emerytury w dzisiejszych cenach
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Inflacja skumulowana -->
                    <Card class="shadow-xl border-none hover:shadow-2xl transition-all duration-300 transform hover:scale-105 bg-gradient-to-br from-[rgb(240,94,94)]/10 to-white backdrop-blur-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-[rgb(0,65,110)] text-base flex items-center gap-2">
                                <div class="w-8 h-8 bg-gradient-to-br from-[rgb(240,94,94)] to-[rgb(240,94,94)]/80 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span>Inflacja skumulowana</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-[rgb(240,94,94)] mb-2">
                                {{ simulationResult.economic_context.cumulative_inflation.toFixed(1) }}%
                            </div>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Łączna inflacja do roku emerytury
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Prognozy makroekonomiczne -->
                <Card v-if="simulationResult.economic_context" class="shadow-xl border-none bg-gradient-to-br from-white to-[rgb(0,153,63)]/5 backdrop-blur-sm">
                    <CardHeader class="bg-gradient-to-r from-[rgb(0,153,63)]/10 to-transparent">
                        <CardTitle class="text-[rgb(0,65,110)] flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-[rgb(0,153,63)] to-[rgb(0,65,110)] rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xl">Prognozy ekonomiczne ZUS</div>
                                <div class="text-sm font-normal text-gray-600 mt-1">{{ simulationResult.economic_context.variant_name }}</div>
                            </div>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="bg-white/70 backdrop-blur-sm p-5 rounded-xl border border-[rgb(190,195,206)]/30">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-gray-600 font-medium">Średni wzrost PKB (rocznie)</p>
                                    <svg class="w-5 h-5 text-[rgb(0,153,63)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="text-2xl font-bold text-[rgb(0,153,63)]">
                                    {{ simulationResult.economic_context.avg_gdp_growth.toFixed(2) }}%
                                </div>
                                <p class="text-xs text-gray-500 mt-2">W okresie do emerytury</p>
                            </div>

                            <div class="bg-white/70 backdrop-blur-sm p-5 rounded-xl border border-[rgb(190,195,206)]/30">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-gray-600 font-medium">Średnia stopa bezrobocia</p>
                                    <svg class="w-5 h-5 text-[rgb(63,132,210)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="text-2xl font-bold text-[rgb(63,132,210)]">
                                    {{ simulationResult.economic_context.avg_unemployment_rate.toFixed(1) }}%
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Prognoza ZUS do {{ formData.retirement_year }}</p>
                            </div>

                            <div class="bg-white/70 backdrop-blur-sm p-5 rounded-xl border border-[rgb(190,195,206)]/30">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-gray-600 font-medium">Twoje wynagrodzenie w {{ formData.retirement_year }}</p>
                                    <svg class="w-5 h-5 text-[rgb(255,179,79)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="text-2xl font-bold text-[rgb(255,179,79)]">
                                    {{ formatCurrency(simulationResult.economic_context.future_gross_salary) }}
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Prognoza z uwzględnieniem wzrostu płac</p>
                            </div>

                            <div class="bg-gradient-to-br from-[rgb(0,153,63)]/10 to-[rgb(63,132,210)]/10 p-5 rounded-xl border-2 border-[rgb(0,153,63)]/30">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-gray-700 font-bold">Twoja emerytura to</p>
                                    <svg class="w-5 h-5 text-[rgb(0,153,63)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="text-2xl font-bold text-[rgb(0,153,63)] mb-1">
                                    {{ simulationResult.economic_context.replacement_rate.toFixed(1) }}%
                                </div>
                                <p class="text-xs text-gray-600">ostatniego wynagrodzenia przed emeryturą</p>
                            </div>
                        </div>

                        <div class="mt-6 bg-gradient-to-r from-[rgb(0,153,63)]/10 to-transparent p-5 rounded-xl border-l-4 border-[rgb(0,153,63)]">
                            <div class="flex items-start gap-3">
                                <svg class="w-6 h-6 text-[rgb(0,153,63)] flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-gray-700 leading-relaxed">
                                    <strong>Współczynnik zastąpienia</strong> pokazuje, jaki procent Twojego ostatniego wynagrodzenia będzie stanowić emerytura. 
                                    Im wyższy współczynnik, tym lepiej utrzymasz dotychczasowy standard życia na emeryturze. 
                                    Według standardów międzynarodowych, współczynnik powyżej 40% uważany jest za zadowalający.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Wpływ zwolnień lekarskich -->
                <Card v-if="simulationResult.sick_leave_impact" class="shadow-xl border-2 border-[rgb(255,179,79)] bg-gradient-to-br from-white to-[rgb(255,179,79)]/5 backdrop-blur-sm">
                    <CardHeader class="bg-gradient-to-r from-[rgb(255,179,79)]/10 to-transparent">
                        <CardTitle class="text-[rgb(0,65,110)] flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-[rgb(255,179,79)] to-[rgb(255,179,79)]/80 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>Wpływ zwolnień lekarskich</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-white/70 backdrop-blur-sm p-5 rounded-xl border border-[rgb(190,195,206)]/30">
                                <p class="text-sm text-gray-600 mb-2 font-medium">Średnia liczba dni na zwolnieniu</p>
                                <div class="text-3xl font-bold text-[rgb(0,65,110)]">
                                    {{ simulationResult.sick_leave_impact.average_days }} dni
                                </div>
                                <p class="text-xs text-gray-500 mt-2">W całej karierze zawodowej</p>
                            </div>
                            <div class="bg-white/70 backdrop-blur-sm p-5 rounded-xl border border-[rgb(240,94,94)]/30">
                                <p class="text-sm text-gray-600 mb-2 font-medium">Szacowane obniżenie świadczenia</p>
                                <div class="text-3xl font-bold text-[rgb(240,94,94)]">
                                    -{{ formatCurrency(simulationResult.sick_leave_impact.pension_reduction) }}
                                </div>
                                <p class="text-xs text-gray-500 mt-2">
                                    {{ simulationResult.sick_leave_impact.percentage_reduction.toFixed(2) }}% miesięcznej emerytury
                                </p>
                            </div>
                        </div>
                        <div class="bg-gradient-to-r from-[rgb(255,179,79)]/10 to-transparent p-5 rounded-xl border-l-4 border-[rgb(255,179,79)]">
                            <div class="flex items-start gap-3">
                                <svg class="w-6 h-6 text-[rgb(255,179,79)] flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-gray-700 leading-relaxed">
                                    <strong>Informacja:</strong> Średnio pracujący w Polsce {{ formData.gender === 'male' ? 'mężczyzna' : 'kobieta' }} 
                                    przebywa przez całą karierę na zwolnieniu lekarskim przez około {{ simulationResult.sick_leave_impact.average_days }} dni. 
                                    Podczas zwolnienia składki emerytalne są odprowadzane w niższej wysokości, co ma bezpośredni wpływ na ostateczną wysokość świadczenia.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Ważne informacje -->
                <Card class="shadow-xl bg-gradient-to-br from-white to-[rgb(190,195,206)]/10 backdrop-blur-sm border-none">
                    <CardHeader>
                        <CardTitle class="text-[rgb(0,65,110)] flex items-center gap-2">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <span>Ważne informacje</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-3">
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 bg-[rgb(0,153,63)]/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-[rgb(0,153,63)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Prognozy są szacunkowe i opierają się na obecnych przepisach oraz założeniu stałego wynagrodzenia skorygowanego o średni wzrost płac</span>
                            </li>
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 bg-[rgb(0,153,63)]/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-[rgb(0,153,63)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Symulacja uwzględnia średni wzrost wynagrodzeń w Polsce (5% rocznie) oraz waloryzację składek</span>
                            </li>
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 bg-[rgb(0,153,63)]/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-[rgb(0,153,63)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Rzeczywista emerytura może się różnić w zależności od zmian w przepisach, sytuacji ekonomicznej oraz indywidualnej ścieżki kariery</span>
                            </li>
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 bg-[rgb(0,153,63)]/10 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-[rgb(0,153,63)]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Zakładamy, że emerytura będzie przyznana w styczniu roku osiągnięcia wieku emerytalnego</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Akcje -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <Button
                        @click="resetForm"
                        class="flex-1 h-14 text-lg font-semibold bg-white text-[rgb(0,65,110)] border-2 border-[rgb(0,65,110)] hover:bg-[rgb(0,65,110)] hover:text-white transition-all duration-300 shadow-lg"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Wykonaj nową symulację
                    </Button>
                    <Link
                        :href="home()"
                        class="flex-1 h-14 text-lg font-semibold bg-gradient-to-r from-[rgb(255,179,79)] to-[rgb(255,179,79)]/80 text-white hover:from-[rgb(255,179,79)]/90 hover:to-[rgb(255,179,79)]/70 rounded-md flex items-center justify-center transition-all duration-300 shadow-lg hover:shadow-xl"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Powrót do strony głównej
                    </Link>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-16 pb-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 text-center border border-white/20">
                    <p class="text-white/90 text-sm">
                        © 2025 Zakład Ubezpieczeń Społecznych
                    </p>
                    <p class="text-white/70 text-xs mt-2">
                        Symulator służy wyłącznie celom informacyjnym i edukacyjnym
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Usuwanie strzałek z input number dla lepszej dostępności */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}

/* Poprawa widoczności tekstu w polach input */
input {
    color: rgb(0, 65, 110) !important;
    font-weight: 600 !important;
}

input::placeholder {
    color: rgb(107, 114, 128) !important;
    opacity: 0.7 !important;
    font-weight: 500 !important;
}

input:focus {
    color: rgb(0, 65, 110) !important;
}

/* Poprawiony kontrast dla wartości w polach */
input[type="number"],
input[type="text"] {
    background-color: white !important;
}

/* Ciemniejszy tekst dla lepszej czytelności */
input:not(:placeholder-shown) {
    color: rgb(0, 65, 110) !important;
    font-weight: 600 !important;
}

/* Grid pattern tło */
.bg-grid-white\/10 {
    background-image: 
        linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
    background-size: 20px 20px;
}

/* Animacje */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}

.animate-slideUp {
    animation: slideUp 0.5s ease-out;
}

.shake {
    animation: shake 0.5s ease-out;
}

/* Wysokie kontrasty dla WCAG 2.0 */
input:focus,
button:focus {
    outline: 3px solid rgba(0, 153, 63, 0.5);
    outline-offset: 2px;
}

/* Lepsze dostosowanie dla urządzeń mobilnych i osób starszych */
@media (max-width: 768px) {
    input,
    button {
        min-height: 56px;
        font-size: 1.125rem;
    }
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}
</style>
