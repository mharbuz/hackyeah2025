<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import { useToast } from 'vue-toastification';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';

// Props
interface Props {
    sessionUuid?: string;
    expectedPension?: number; // Expected pension from session
    existingFormData?: {
        age: number;
        gender: 'male' | 'female';
        gross_salary: number;
        retirement_year: number;
        account_balance?: number;
        subaccount_balance?: number;
        include_sick_leave: boolean;
        forecast_variant?: string;
    };
    existingSimulationResults?: any;
}

const props = defineProps<Props>();

// Toast notifications
const toast = useToast();

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
    monthly_pension_without_sick_leave: number;
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
        average_pension_in_retirement_year: number;
        pension_to_average_ratio: number;
    };
    delayed_retirement_options: Array<{
        delay_years: number;
        retirement_year: number;
        monthly_pension: number;
        total_capital: number;
    }>;
    expected_pension_comparison?: {
        expected_pension: number;
        predicted_pension: number;
        difference: number;
        percentage_difference: number;
        exceeds_expectations: boolean;
        solutions?: {
            extend_work_period: {
                additional_years: number;
                new_retirement_year: number;
                new_monthly_pension: number;
            };
            higher_salary: {
                required_salary: number;
                salary_increase: number;
                percentage_increase: number;
            };
            investment_savings: {
                monthly_savings: number;
                percentage_of_salary: number;
                investment_return_rate: number;
                total_investment_needed: number;
            };
        };
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
const activeSolutionTab = ref<'work' | 'salary' | 'savings'>('work');

// Populate form with existing data if available
onMounted(() => {
    if (props.existingFormData) {
        formData.value = {
            age: props.existingFormData.age.toString(),
            gender: props.existingFormData.gender,
            gross_salary: props.existingFormData.gross_salary.toString(),
            retirement_year: props.existingFormData.retirement_year.toString(),
            account_balance: props.existingFormData.account_balance?.toString() || '',
            subaccount_balance: props.existingFormData.subaccount_balance?.toString() || '',
            include_sick_leave: props.existingFormData.include_sick_leave
        };
    }
    
    // If there are existing simulation results, show them
    if (props.existingSimulationResults) {
        simulationResult.value = props.existingSimulationResults;
        showResults.value = true;
    }
});

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
        const requestBody: any = {
            age: parseInt(formData.value.age),
            gender: formData.value.gender,
            gross_salary: parseFloat(formData.value.gross_salary),
            retirement_year: parseInt(formData.value.retirement_year),
            account_balance: formData.value.account_balance ? parseFloat(formData.value.account_balance) : null,
            subaccount_balance: formData.value.subaccount_balance ? parseFloat(formData.value.subaccount_balance) : null,
            include_sick_leave: formData.value.include_sick_leave
        };

        // Include session UUID if present
        if (props.sessionUuid) {
            requestBody.session_uuid = props.sessionUuid;
        }

        // Include expected pension from session if present
        if (props.expectedPension) {
            requestBody.expected_pension = props.expectedPension;
        }

        const response = await fetch('/api/pension/simulate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(requestBody)
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

        // Set default active tab based on available solutions
        if (data.expected_pension_comparison?.solutions) {
            if (data.expected_pension_comparison.solutions.extend_work_period?.additional_years > 0) {
                activeSolutionTab.value = 'work';
            } else if (data.expected_pension_comparison.solutions.higher_salary?.required_salary > 0) {
                activeSolutionTab.value = 'salary';
            } else if (data.expected_pension_comparison.solutions.investment_savings?.monthly_savings > 0) {
                activeSolutionTab.value = 'savings';
            }
        }
        
        // Przewiń do wyników
        setTimeout(() => {
            document.getElementById('results')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
        
    } catch (error) {
        console.error('Błąd symulacji:', error);
        toast.error('Wystąpił błąd podczas przetwarzania. Spróbuj ponownie.');
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

// Generate URL for advanced dashboard with session parameter
const getAdvancedDashboardUrl = () => {
    if (props.sessionUuid) {
        return `/dashboard-prognozowania?session=${props.sessionUuid}`;
    }
    return '/dashboard-prognozowania';
};
</script>

<template>
    <Head title="Symulacja przyszłej emerytury - ZUS">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    
    <div class="min-h-screen bg-white zus-page">
        <!-- Top Header Bar -->
        <header class="bg-white border-b">
            <div class="max-w-[1400px] mx-auto px-2 sm:px-4 lg:px-8">
                <!-- Desktop Header (lg and up) -->
                <div class="hidden lg:flex items-center py-5 gap-2">
                    <!-- Logo -->
                    <div class="flex items-center shrink-0 mr-5">
                        <Link :href="home()">
                            <img
                                src="/logo_zus_darker_with_text.svg"
                                alt="ZUS Logo" 
                                class="h-12 w-auto cursor-pointer"
                            />
                        </Link>
                        </div>

                    <!-- Right Side Navigation -->
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <!-- Kontakt -->
                        <a href="#" class="text-sm font-medium text-gray-700 hover:text-gray-900 hidden xl:block">
                            Kontakt
                        </a>

                        <!-- Separator -->
                        <div class="h-4 w-px bg-gray-300"></div>

                        <!-- Language Selector -->
                        <div class="relative hidden xl:block">
                            <button class="flex items-center space-x-1 text-sm font-medium text-gray-700 hover:text-gray-900">
                                <span>PL</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                    </div>

                        <!-- Separator -->
                        <div class="h-4 w-px bg-gray-300"></div>

                        <!-- Accessibility Icons -->
                        <div class="flex items-center gap-3">
                            <!-- Icon - Słuch -->
                            <button
                                class="p-2 rounded hover:opacity-90 transition-colors"
                                style="background-color: rgb(0, 65, 110);"
                                aria-label="Wersja dla osób niesłyszących"
                            >
                                <img src="/ikona_ucho.svg" alt="Ikona ucha" class="h-6 w-6" />
                            </button>

                            <!-- Separator -->
                            <div class="h-4 w-px bg-gray-300"></div>

                            <!-- Icon - Wózek -->
                            <button
                                class="p-2 rounded hover:opacity-90 transition-colors"
                                style="background-color: rgb(0, 65, 110);"
                                aria-label="Wersja dla osób niepełnosprawnych"
                            >
                                <img src="/ikona_wozek.svg" alt="Ikona wózka" class="h-6 w-6" />
                            </button>

                            <!-- Separator -->
                            <div class="h-4 w-px bg-gray-300"></div>

                            <!-- BIP Icon -->
                            <button class="p-2 rounded hover:bg-gray-100 transition-colors" aria-label="BIP">
                                <img src="/bip_simple.svg" alt="BIP" class="h-9 w-9" />
                            </button>
                        </div>

                        <!-- Separator -->
                        <div class="h-6 w-px bg-gray-300 mx-2"></div>

                        <!-- Login Buttons -->
                        <div class="flex items-center gap-2">
                            <button
                                class="flex px-3 xl:px-4 py-1.5 xl:py-2 text-xs xl:text-sm font-semibold border-2 rounded hover:opacity-90 transition-colors items-center"
                                style="color: rgb(0, 65, 110); border-color: rgb(0, 65, 110); white-space: nowrap;"
                            >
                                <span>Zarejestruj w PUE/eZUS</span>
                                <svg class="w-3 h-3 xl:w-4 xl:h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                            </button>
                            <button
                                class="flex px-3 xl:px-4 py-1.5 xl:py-2 text-xs xl:text-sm font-semibold text-gray-900 rounded hover:opacity-90 transition-colors items-center border-2"
                                style="background-color: rgb(250, 184, 86); border-color: rgb(0, 65, 110); white-space: nowrap;"
                            >
                                <span style="color: rgb(0, 65, 110);">Zaloguj do PUE/eZUS</span>
                                <svg class="w-3 h-3 xl:w-4 xl:h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex items-center gap-4 ml-4">
                            <!-- Search Icon -->
                            <button
                                class="p-2 rounded-full hover:opacity-90 transition-colors mb-2"
                                style="background-color: rgb(17, 120, 59);"
                                aria-label="Szukaj"
                            >
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>

                            <!-- EU Logo -->
                            <div class="2xl:flex items-center">
                                <img src="/eu_pl_chromatic.jpg" alt="Unia Europejska" class="h-12 w-auto" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Header (below lg) -->
                <div class="lg:hidden flex justify-between py-3">
                    <!-- First Row: Logo and Links -->
                    <div class="flex items-center justify-between mb-2">
                        <!-- Logo -->
                        <div class="flex items-center shrink-0">
                            <Link :href="home()">
                                <img
                                    src="/logo_zus_darker_with_text.svg"
                                    alt="ZUS Logo"
                                    class="h-10 sm:h-12 w-auto cursor-pointer"
                                />
                    </Link>
                        </div>

                        <!-- Links -->
                        <div class="flex items-center gap-2 sm:gap-4">
                            <!-- Zarejestruj w PUE/eZUS -->
                            <a
                                href="#"
                                class="hidden md:block text-sm font-medium hover:underline whitespace-nowrap"
                                style="color: rgb(0, 153, 63);"
                            >
                                Zarejestruj w PUE/eZUS
                            </a>

                            <!-- Zaloguj do PUE/eZUS -->
                            <a
                                href="#"
                                class="hidden md:block text-sm font-medium hover:underline whitespace-nowrap"
                                style="color: rgb(0, 153, 63);"
                            >
                                Zaloguj do PUE/eZUS
                            </a>
                        </div>
                    </div>

                    <!-- Second Row: Szukaj, UE, Menu -->
                    <div class="flex items-center justify-end gap-4">
                        <!-- Search Button - vertical layout -->
                        <button
                            class="flex flex-col items-center justify-center h-9 gap-1 px-2 py-1 rounded-full hover:opacity-90 transition-colors mb-2"
                            style="background-color: rgb(17, 120, 59);"
                            aria-label="Szukaj"
                        >
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>

                        <!-- EU Flag with text -->
                        <div class="flex flex-col items-center justify-center">
                            <img src="/eu_pl_chromatic.jpg" alt="Unia Europejska" class="h-10 w-auto mb-1" />
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="w-full max-w-7xl mx-auto p-4 lg:p-8">
            <!-- Hero Section -->
            <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12 mb-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4" style="color: rgb(0, 65, 110);">
                        Oblicz swoją przyszłą emeryturę
                </h2>
                    <p class="text-base lg:text-lg text-gray-700 max-w-2xl mx-auto">
                    Poznaj szacunkową wysokość swojej przyszłej emerytury w kilka minut
                </p>
                </div>
            </div>

            <!-- Formularz -->
            <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12 mb-8">
                <div class="mb-8">
                    <h3 class="text-2xl md:text-3xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                        Twoje dane
                    </h3>
                    <p class="text-gray-600 text-base md:text-lg">
                            Wypełnij formularz, aby otrzymać spersonalizowaną prognozę
                    </p>
                    </div>
                
                    <form @submit.prevent="handleSubmit" class="space-y-10">
                        <!-- Sekcja: Dane podstawowe -->
                        <div class="space-y-6">
                            <div class="flex items-center gap-3 pb-3 border-b-2 border-[rgb(0,153,63)]">
                                <div class="w-8 h-8 flex items-center justify-center text-white font-bold text-sm" style="background-color: rgb(0, 153, 63);">
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
                                                'flex-1 h-14 border-2 font-semibold text-base transition-all duration-300',
                                                formData.gender === 'male'
                                                    ? 'text-white border-transparent shadow-sm'
                                                    : 'bg-white text-[rgb(0,65,110)] border-[rgb(190,195,206)]',
                                                errors.gender ? 'border-[rgb(240,94,94)]' : ''
                                            ]"
                                            :style="formData.gender === 'male' ? 'background-color: rgb(63, 132, 210);' : ''"
                                        >
                                            Mężczyzna
                                        </button>
                                        <button
                                            type="button"
                                            @click="formData.gender = 'female'; validateGender()"
                                            :class="[
                                                'flex-1 h-14 border-2 font-semibold text-base transition-all duration-300',
                                                formData.gender === 'female'
                                                    ? 'text-white border-transparent shadow-sm'
                                                    : 'bg-white text-[rgb(0,65,110)] border-[rgb(190,195,206)]',
                                                errors.gender ? 'border-[rgb(240,94,94)]' : ''
                                            ]"
                                            :style="formData.gender === 'female' ? 'background-color: rgb(63, 132, 210);' : ''"
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
                        <div class="space-y-6 p-6 md:p-8 border-2 border-dashed border-[rgb(190,195,206)]" style="background-color: rgba(255, 179, 79, 0.05);">
                            <div class="flex items-center gap-3 pb-3 border-b-2 border-[rgb(255,179,79)]">
                                <div class="w-8 h-8 flex items-center justify-center text-white font-bold text-sm" style="background-color: rgb(255, 179, 79);">
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

                            <div class="bg-white p-4 text-sm text-gray-600 flex items-start gap-3 border border-[rgb(190,195,206)]">
                                <svg class="w-5 h-5 text-[rgb(255,179,79)] flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <p>
                                    Jeśli nie znasz dokładnych wartości zgromadzonych środków, zostaw puste pola - oszacujemy je na podstawie Twojego wynagrodzenia i wieku
                                </p>
                            </div>

                            <!-- Zwolnienia lekarskie -->
                            <div class="space-y-3 bg-white p-5 border-2 border-[rgb(190,195,206)] transition-all duration-300">
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
                            <button
                                type="submit"
                                :disabled="isSubmitting"
                                class="w-full px-8 py-4 text-lg lg:text-xl font-semibold text-white transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                                style="background-color: rgb(0, 153, 63);"
                            >
                                <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-3 h-6 w-6 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span v-if="isSubmitting">Obliczam prognozę...</span>
                                <span v-else>Zaprognozuj moją przyszłą emeryturę</span>
                            </button>
                        </div>
                    </form>
            </div>

            <!-- Wyniki symulacji -->
            <div v-if="showResults && simulationResult" id="results" class="space-y-6 animate-slideUp">
                <!-- Główny wynik -->
                <div class="bg-white border border-gray-200 shadow-sm p-8 md:p-12">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl lg:text-3xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                                Twoja prognoza emerytury
                            </h3>
                            <p class="text-gray-600">
                                Szacunkowa wysokość Twojej przyszłej emerytury
                            </p>
                        </div>

                        <!-- Dwie karty z kwotami -->
                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <!-- Wysokość rzeczywista (nominalna) -->
                            <div class="border-2 p-6 text-center" style="border-color: rgb(63, 132, 210); background-color: rgba(63, 132, 210, 0.05);">
                                <div class="text-sm font-semibold mb-3 uppercase tracking-wide" style="color: rgb(63, 132, 210);">
                                    Wysokość rzeczywista
                                </div>
                                <div class="text-4xl md:text-5xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                                    {{ formatCurrency(simulationResult.monthly_pension) }}
                                </div>
                                <p class="text-sm text-gray-600">
                                    Kwota nominalna w {{ formData.retirement_year }} roku
                                </p>
                            </div>

                            <!-- Wysokość urealniona (siła nabywcza) -->
                            <div class="border-2 p-6 text-center" style="border-color: rgb(0, 153, 63); background-color: rgba(0, 153, 63, 0.05);">
                                <div class="text-sm font-semibold mb-3 uppercase tracking-wide" style="color: rgb(0, 153, 63);">
                                    Wysokość urealniona
                                </div>
                                <div class="text-4xl md:text-5xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                                    {{ simulationResult.economic_context ? formatCurrency(simulationResult.economic_context.purchasing_power_today) : '—' }}
                                </div>
                                <p class="text-sm text-gray-600">
                                    Wartość w dzisiejszych cenach ({{ new Date().getFullYear() }} r.)
                                </p>
                            </div>
                        </div>

                        <!-- Informacja o czasie do emerytury -->
                        <div class="border-2 p-6 text-center mb-6" style="border-color: rgb(190, 195, 206); background-color: rgba(190, 195, 206, 0.05);">
                            <div class="text-sm font-semibold mb-2 uppercase tracking-wide text-gray-600">
                                Czas do emerytury
                            </div>
                            <div class="text-3xl font-bold" style="color: rgb(0, 65, 110);">
                                {{ simulationResult.years_to_retirement }} {{ simulationResult.years_to_retirement === 1 ? 'rok' : (simulationResult.years_to_retirement < 5 ? 'lata' : 'lat') }}
                            </div>
                        </div>

                        <div class="bg-white border shadow-sm p-6 lg:p-8 mb-8" style="border-color: rgb(190, 195, 206); background-color: rgba(190, 195, 206, 0.05);">
                            <div class="mb-4">
                                <h4 class="text-xl font-bold flex items-center gap-2" style="color: rgb(0, 65, 110);">
                                    <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(0, 153, 63);">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                            <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
                            </svg>
                                    </div>
                                    <span>Składki emerytalne</span>
                                </h4>
                            </div>
                            <div class="text-4xl font-bold mb-2" style="color: rgb(0, 153, 63);">
                                {{ formatCurrency(simulationResult.total_contributions) }}
                            </div>
                            <p class="text-gray-600 leading-relaxed">
                                Łączna wartość składek odprowadzonych do emerytury (zgromadzone + przyszłe)
                            </p>
                        </div>

                        <!-- Wyjaśnienie różnicy -->
                        <div class="border-l-4 p-6" style="background-color: rgba(255, 179, 79, 0.1); border-color: rgb(255, 179, 79);">
                            <div class="flex items-start gap-3">
                                <svg class="w-6 h-6 flex-shrink-0 mt-0.5" style="color: rgb(255, 179, 79);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-gray-700 leading-relaxed">
                                    <strong>Wysokość rzeczywista</strong> to kwota, którą otrzymasz w przyszłości. 
                                    <strong>Wysokość urealniona</strong> pokazuje, ile ta emerytura będzie warta w dzisiejszych cenach, 
                                    uwzględniając inflację i zmiany siły nabywczej pieniądza w okresie {{ simulationResult.years_to_retirement }} lat.
                                </p>
                            </div>
                        </div>
                </div>

                <!-- Porównanie z oczekiwaną emeryturą -->
                <div v-if="simulationResult.expected_pension_comparison" class="space-y-6">
                    <!-- Case 2: BIG GREEN - Exceeds expectations -->
                    <div v-if="simulationResult.expected_pension_comparison.exceeds_expectations"
                          class="bg-white border border-gray-200 shadow-sm p-8 md:p-12 text-center">
                            <div class="flex justify-center mb-6">
                                <div class="w-20 h-20 flex items-center justify-center" style="background-color: rgb(0, 153, 63);">
                                    <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            
                            <h3 class="text-3xl md:text-4xl font-bold mb-4" style="color: rgb(0, 65, 110);">
                                🎉 Gratulacje!
                            </h3>
                            
                            <div class="text-2xl md:text-3xl font-bold mb-6" style="color: rgb(0, 153, 63);">
                                Twoje obecne perspektywy przewyższają oczekiwania!
                            </div>
                            
                            <div class="grid md:grid-cols-2 gap-6 mb-6">
                                <div class="border-2 p-6" style="border-color: rgb(190, 195, 206); background-color: rgba(190, 195, 206, 0.05);">
                                    <div class="text-sm font-semibold mb-2 text-gray-600 uppercase tracking-wide">Oczekiwana emerytura</div>
                                    <div class="text-3xl font-bold" style="color: rgb(0, 65, 110);">
                                        {{ formatCurrency(simulationResult.expected_pension_comparison.expected_pension) }}
                                    </div>
                                </div>
                                
                                <div class="border-2 p-6" style="border-color: rgb(0, 153, 63); background-color: rgba(0, 153, 63, 0.05);">
                                    <div class="text-sm font-semibold mb-2 uppercase tracking-wide" style="color: rgb(0, 153, 63);">Prognozowana emerytura</div>
                                    <div class="text-3xl font-bold" style="color: rgb(0, 65, 110);">
                                        {{ formatCurrency(simulationResult.expected_pension_comparison.predicted_pension) }}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-2 p-6" style="border-color: rgb(0, 153, 63); background-color: rgba(0, 153, 63, 0.05);">
                                <div class="text-sm font-semibold mb-2 uppercase tracking-wide" style="color: rgb(0, 153, 63);">Różnica</div>
                                <div class="text-2xl font-bold" style="color: rgb(0, 153, 63);">
                                    +{{ formatCurrency(simulationResult.expected_pension_comparison.difference) }}
                                </div>
                                <div class="text-sm text-gray-600 mt-2">
                                    ({{ simulationResult.expected_pension_comparison.percentage_difference.toFixed(1) }}% więcej niż oczekiwane)
                                </div>
                            </div>
                    </div>

                    <!-- Case 1: Solutions to achieve expected pension -->
                    <div v-else class="space-y-6 mt-20">
                        <!-- Header -->
                        <div class="bg-white border border-gray-200 shadow-sm p-6 text-center">
                            <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4" style="background-color: rgb(240, 94, 94);">
                                    <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            <h3 class="text-2xl md:text-3xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                                    Jak osiągnąć oczekiwaną emeryturę?
                                </h3>
                                <p class="text-gray-600 text-lg">
                                    Twoja prognozowana emerytura wynosi {{ formatCurrency(simulationResult.expected_pension_comparison.predicted_pension) }}, 
                                    a oczekujesz {{ formatCurrency(simulationResult.expected_pension_comparison.expected_pension) }}.
                                </p>
                            <div class="mt-4 text-xl font-bold" style="color: rgb(240, 94, 94);">
                                    Brakuje: {{ formatCurrency(Math.abs(simulationResult.expected_pension_comparison.difference)) }}
                                </div>

                            <div class="mb-4 flex items-center gap-3 mt-10">
                                <svg class="w-6 h-6" style="color: rgb(0, 65, 110);" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                                </svg>
                                <h4 class="text-lg md:text-xl font-bold" style="color: rgb(0, 65, 110);">
                                    Wybierz scenariusz rozwiązania
                                </h4>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">
                                Kliknij na jedną z opcji poniżej, aby zobaczyć szczegółowe informacje
                            </p>

                            <div class="grid md:grid-cols-3 gap-4">
                                <button
                                    v-if="simulationResult.expected_pension_comparison.solutions?.extend_work_period?.additional_years > 0"
                                    @click="activeSolutionTab = 'work'"
                                    :class="[
                                        'relative p-5 pt-8 border-3 transition-all duration-300 transform hover:scale-105 hover:shadow-xl group',
                                        activeSolutionTab === 'work'
                                            ? 'border-4 shadow-lg scale-105'
                                            : 'border-3 shadow-md'
                                    ]"
                                    :style="activeSolutionTab === 'work'
                                        ? 'background-color: rgb(63, 132, 210); border-color: rgb(63, 132, 210);'
                                        : 'background-color: rgba(63, 132, 210, 0.05); border-color: rgb(63, 132, 210);'"
                                >
                                    <!-- Badge "Aktywne" -->
                                    <div v-if="activeSolutionTab === 'work'" class="absolute top-2 left-2 px-3 py-1 text-xs font-bold text-white shadow-md" style="background-color: rgb(0, 153, 63);">
                                        ✓ WYBRANE
                                    </div>

                                    <!-- Ikona wskaźnika kliknięcia (tylko dla nieaktywnych) -->
                                    <div v-else class="absolute top-2 right-2 opacity-50 group-hover:opacity-100 transition-opacity">
                                        <svg class="w-5 h-5" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>

                                    <div class="flex flex-col items-center text-center gap-3">
                                        <div class="w-16 h-16 flex items-center justify-center"
                                             :style="activeSolutionTab === 'work'
                                                ? 'background-color: rgba(255,255,255,0.2);'
                                                : 'background-color: rgba(63, 132, 210, 0.1);'">
                                            <svg class="w-10 h-10" :style="activeSolutionTab === 'work' ? 'color: white;' : 'color: rgb(63, 132, 210);'" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-bold text-base md:text-lg mb-1" :style="activeSolutionTab === 'work' ? 'color: white;' : 'color: rgb(0, 65, 110);'">
                                                Przedłużenie okresu pracy
                                            </div>
                                            <div class="text-sm font-semibold" :style="activeSolutionTab === 'work' ? 'color: rgba(255,255,255,0.9);' : 'color: rgb(63, 132, 210);'">
                                                +{{ simulationResult.expected_pension_comparison.solutions.extend_work_period.additional_years }} {{ simulationResult.expected_pension_comparison.solutions.extend_work_period.additional_years === 1 ? 'rok' : 'lata' }}
                                            </div>
                                        </div>
                                    </div>
                                </button>

                                <button
                                    v-if="simulationResult.expected_pension_comparison.solutions?.higher_salary?.required_salary > 0"
                                    @click="activeSolutionTab = 'salary'"
                                    :class="[
                                        'relative p-5 pt-8 border-3 transition-all duration-300 transform hover:scale-105 hover:shadow-xl group',
                                        activeSolutionTab === 'salary'
                                            ? 'border-4 shadow-lg scale-105'
                                            : 'border-3 shadow-md'
                                    ]"
                                    :style="activeSolutionTab === 'salary'
                                        ? 'background-color: rgb(0, 153, 63); border-color: rgb(0, 153, 63);'
                                        : 'background-color: rgba(0, 153, 63, 0.05); border-color: rgb(0, 153, 63);'"
                                >
                                    <!-- Badge "Aktywne" -->
                                    <div v-if="activeSolutionTab === 'salary'" class="absolute top-2 left-2 px-3 py-1 text-xs font-bold text-white shadow-md" style="background-color: rgb(63, 132, 210);">
                                        ✓ WYBRANE
                                    </div>

                                    <!-- Ikona wskaźnika kliknięcia (tylko dla nieaktywnych) -->
                                    <div v-else class="absolute top-2 right-2 opacity-50 group-hover:opacity-100 transition-opacity">
                                        <svg class="w-5 h-5" style="color: rgb(0, 153, 63);" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>

                                    <div class="flex flex-col items-center text-center gap-3">
                                        <div class="w-16 h-16 flex items-center justify-center"
                                             :style="activeSolutionTab === 'salary'
                                                ? 'background-color: rgba(255,255,255,0.2);'
                                                : 'background-color: rgba(0, 153, 63, 0.1);'">
                                            <svg class="w-10 h-10" :style="activeSolutionTab === 'salary' ? 'color: white;' : 'color: rgb(0, 153, 63);'" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-bold text-base md:text-lg mb-1" :style="activeSolutionTab === 'salary' ? 'color: white;' : 'color: rgb(0, 65, 110);'">
                                                Wyższe wynagrodzenie
                                            </div>
                                            <div class="text-sm font-semibold" :style="activeSolutionTab === 'salary' ? 'color: rgba(255,255,255,0.9);' : 'color: rgb(0, 153, 63);'">
                                                +{{ ((simulationResult.expected_pension_comparison.solutions.higher_salary.percentage_increase || 0)).toFixed(0) }}% wzrost
                                            </div>
                                        </div>
                                    </div>
                                </button>

                                <button
                                    v-if="simulationResult.expected_pension_comparison.solutions?.investment_savings?.monthly_savings > 0"
                                    @click="activeSolutionTab = 'savings'"
                                    :class="[
                                        'relative p-5 pt-8 border-3 transition-all duration-300 transform hover:scale-105 hover:shadow-xl group',
                                        activeSolutionTab === 'savings'
                                            ? 'border-4 shadow-lg scale-105'
                                            : 'border-3 shadow-md'
                                    ]"
                                    :style="activeSolutionTab === 'savings'
                                        ? 'background-color: rgb(255, 179, 79); border-color: rgb(255, 179, 79);'
                                        : 'background-color: rgba(255, 179, 79, 0.05); border-color: rgb(255, 179, 79);'"
                                >
                                    <!-- Badge "Aktywne" -->
                                    <div v-if="activeSolutionTab === 'savings'" class="absolute top-2 left-2 px-3 py-1 text-xs font-bold text-white shadow-md" style="background-color: rgb(0, 153, 63);">
                                        ✓ WYBRANE
                                    </div>

                                    <!-- Ikona wskaźnika kliknięcia (tylko dla nieaktywnych) -->
                                    <div v-else class="absolute top-2 right-2 opacity-50 group-hover:opacity-100 transition-opacity">
                                        <svg class="w-5 h-5" style="color: rgb(255, 179, 79);" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>

                                    <div class="flex flex-col items-center text-center gap-3">
                                        <div class="w-16 h-16 flex items-center justify-center"
                                             :style="activeSolutionTab === 'savings'
                                                ? 'background-color: rgba(255,255,255,0.2);'
                                                : 'background-color: rgba(255, 179, 79, 0.1);'">
                                            <svg class="w-10 h-10" :style="activeSolutionTab === 'savings' ? 'color: white;' : 'color: rgb(255, 179, 79);'" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-bold text-base md:text-lg mb-1" :style="activeSolutionTab === 'savings' ? 'color: white;' : 'color: rgb(0, 65, 110);'">
                                                Oszczędności i inwestycje
                                            </div>
                                            <div class="text-sm font-semibold" :style="activeSolutionTab === 'savings' ? 'color: rgba(255,255,255,0.9);' : 'color: rgb(255, 179, 79);'">
                                                {{ formatCurrency(simulationResult.expected_pension_comparison.solutions.investment_savings.monthly_savings) }}/mies.
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Tab Content -->
                        <!-- Solution 1: Extend work period -->
                        <div v-if="simulationResult.expected_pension_comparison.solutions?.extend_work_period?.additional_years > 0 && activeSolutionTab === 'work'"
                              class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                            <div class="mb-6">
                                <h4 class="text-2xl font-bold flex items-center gap-3" style="color: rgb(0, 65, 110);">
                                    <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(63, 132, 210);">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span>Rozwiązanie 1: Przedłużenie okresu pracy</span>
                                </h4>
                            </div>
                                <div class="grid md:grid-cols-3 gap-6 mb-6">
                                    <div class="border-2 p-5 text-center" style="border-color: rgb(63, 132, 210); background-color: rgba(63, 132, 210, 0.05);">
                                        <div class="text-3xl font-bold mb-2" style="color: rgb(63, 132, 210);">
                                            +{{ simulationResult.expected_pension_comparison.solutions.extend_work_period.additional_years }}
                                        </div>
                                        <div class="text-sm text-gray-600">Dodatkowych lat pracy</div>
                                    </div>
                                    
                                    <div class="border-2 p-5 text-center" style="border-color: rgb(190, 195, 206); background-color: rgba(190, 195, 206, 0.05);">
                                        <div class="text-2xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                                            {{ simulationResult.expected_pension_comparison.solutions.extend_work_period.new_retirement_year }}
                                        </div>
                                        <div class="text-sm text-gray-600">Nowy rok emerytury</div>
                                    </div>
                                    
                                    <div class="border-2 p-5 text-center" style="border-color: rgb(0, 153, 63); background-color: rgba(0, 153, 63, 0.05);">
                                        <div class="text-2xl font-bold mb-2" style="color: rgb(0, 153, 63);">
                                            {{ formatCurrency(simulationResult.expected_pension_comparison.solutions.extend_work_period.new_monthly_pension) }}
                                        </div>
                                        <div class="text-sm text-gray-600">Nowa emerytura</div>
                                    </div>
                                </div>
                                
                                <div class="border-l-4 p-5" style="background-color: rgba(63, 132, 210, 0.1); border-color: rgb(63, 132, 210);">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-6 h-6 flex-shrink-0 mt-0.5" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        <p class="text-sm text-gray-700 leading-relaxed">
                                            <strong>Jak to działa:</strong> Pracując dłużej, zwiększasz zgromadzony kapitał emerytalny (dodatkowe składki) 
                                            oraz skracasz statystyczny okres pobierania emerytury, co przekłada się na wyższe miesięczne świadczenie.
                                        </p>
                                    </div>
                                </div>
                        </div>

                        <!-- Solution 2: Higher salary -->
                        <div v-if="simulationResult.expected_pension_comparison.solutions?.higher_salary?.required_salary > 0 && activeSolutionTab === 'salary'"
                              class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                            <div class="mb-6">
                                <h4 class="text-2xl font-bold flex items-center gap-3" style="color: rgb(0, 65, 110);">
                                    <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(0, 153, 63);">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span>Rozwiązanie 2: Wyższe wynagrodzenie</span>
                                </h4>
                            </div>
                                <div class="grid md:grid-cols-3 gap-6 mb-6">
                                    <div class="bg-white p-5 border border-[rgb(190,195,206)] text-center">
                                        <div class="text-2xl font-bold text-[rgb(0,153,63)] mb-2">
                                            {{ formatCurrency(simulationResult.expected_pension_comparison.solutions.higher_salary.required_salary) }}
                                        </div>
                                        <div class="text-sm text-gray-600">Wymagane wynagrodzenie</div>
                                    </div>
                                    
                                    <div class="bg-white p-5 border border-[rgb(190,195,206)] text-center">
                                        <div class="text-2xl font-bold text-[rgb(0,65,110)] mb-2">
                                            +{{ formatCurrency(simulationResult.expected_pension_comparison.solutions.higher_salary.salary_increase) }}
                                        </div>
                                        <div class="text-sm text-gray-600">Wzrost wynagrodzenia</div>
                                    </div>
                                    
                                    <div class="bg-white p-5 border border-[rgb(190,195,206)] text-center">
                                        <div class="text-2xl font-bold text-[rgb(240,94,94)] mb-2">
                                            +{{ simulationResult.expected_pension_comparison.solutions.higher_salary.percentage_increase.toFixed(1) }}%
                                        </div>
                                        <div class="text-sm text-gray-600">Procentowy wzrost</div>
                                    </div>
                                </div>
                                
                                <div class="border-l-4 p-5" style="background-color: rgba(0, 153, 63, 0.1); border-color: rgb(0, 153, 63);">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-6 h-6 text-[rgb(0,153,63)] flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        <p class="text-sm text-gray-700 leading-relaxed">
                                            <strong>Jak to działa:</strong> Wyższe wynagrodzenie oznacza wyższe składki emerytalne, 
                                            które są podstawą do obliczenia przyszłej emerytury. Im wyższe składki, tym wyższa emerytura.
                                        </p>
                                    </div>
                                </div>
                        </div>

                        <!-- Solution 3: Investment savings -->
                        <div v-if="simulationResult.expected_pension_comparison.solutions?.investment_savings?.monthly_savings > 0 && activeSolutionTab === 'savings'"
                              class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                            <div class="mb-6">
                                <h4 class="text-2xl font-bold flex items-center gap-3" style="color: rgb(0, 65, 110);">
                                    <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(255, 179, 79);">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                            <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span>Rozwiązanie 3: Oszczędności i inwestycje</span>
                                </h4>
                            </div>
                                <div class="grid md:grid-cols-4 gap-4 mb-6">
                                    <div class="border-2 p-4 text-center" style="border-color: rgb(255, 179, 79); background-color: rgba(255, 179, 79, 0.05);">
                                        <div class="text-xl font-bold mb-1" style="color: rgb(255, 179, 79);">
                                            {{ formatCurrency(simulationResult.expected_pension_comparison.solutions.investment_savings.monthly_savings) }}
                                        </div>
                                        <div class="text-xs text-gray-600">Miesięczne oszczędności</div>
                                    </div>
                                    
                                    <div class="border-2 p-4 text-center" style="border-color: rgb(190, 195, 206); background-color: rgba(190, 195, 206, 0.05);">
                                        <div class="text-xl font-bold mb-1" style="color: rgb(0, 65, 110);">
                                            {{ simulationResult.expected_pension_comparison.solutions.investment_savings.percentage_of_salary.toFixed(1) }}%
                                        </div>
                                        <div class="text-xs text-gray-600">Od wynagrodzenia</div>
                                    </div>
                                    
                                    <div class="border-2 p-4 text-center" style="border-color: rgb(0, 153, 63); background-color: rgba(0, 153, 63, 0.05);">
                                        <div class="text-xl font-bold mb-1" style="color: rgb(0, 153, 63);">
                                            {{ simulationResult.expected_pension_comparison.solutions.investment_savings.investment_return_rate.toFixed(1) }}%
                                        </div>
                                        <div class="text-xs text-gray-600">Roczny zwrot</div>
                                    </div>
                                    
                                    <div class="border-2 p-4 text-center" style="border-color: rgb(63, 132, 210); background-color: rgba(63, 132, 210, 0.05);">
                                        <div class="text-lg font-bold mb-1" style="color: rgb(63, 132, 210);">
                                            {{ formatCurrency(simulationResult.expected_pension_comparison.solutions.investment_savings.total_investment_needed) }}
                                        </div>
                                        <div class="text-xs text-gray-600">Łączna kwota</div>
                                    </div>
                                </div>
                                
                                <div class="border-l-4 p-5" style="background-color: rgba(255, 179, 79, 0.1); border-color: rgb(255, 179, 79);">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-6 h-6 flex-shrink-0 mt-0.5" style="color: rgb(255, 179, 79);" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        <p class="text-sm text-gray-700 leading-relaxed">
                                            <strong>Jak to działa:</strong> Oszczędzając i inwestując {{ simulationResult.expected_pension_comparison.solutions.investment_savings.percentage_of_salary.toFixed(1) }}% 
                                            swojego wynagrodzenia z rocznym zwrotem {{ simulationResult.expected_pension_comparison.solutions.investment_savings.investment_return_rate.toFixed(1) }}% powyżej inflacji, 
                                            zgromadzisz wystarczające środki, aby uzupełnić różnicę między prognozowaną a oczekiwaną emeryturą.
                                        </p>
                                    </div>
                                </div>
                    </div>
                </div>
                                </div>


                <!-- Kluczowe wskaźniki ekonomiczne -->
                <div v-if="simulationResult.economic_context" class="space-y-6 mt-30">
                    <!-- Tytuł sekcji -->
                    <div class="text-center">
                        <h3 class="text-2xl md:text-3xl font-bold text-[rgb(0,65,110)] mb-2">
                            Kluczowe wskaźniki Twojej emerytury
                        </h3>
                        <p class="text-gray-600">
                            Porównanie Twojego świadczenia z prognozami ekonomicznymi
                        </p>
                    </div>

                    <!-- Współczynnik zastąpienia - duża karta -->
                    <div class="bg-white border border-gray-200 shadow-sm p-8 md:p-10">
                            <div class="flex flex-col md:flex-row items-center gap-6">
                                <div class="w-20 h-20 flex items-center justify-center" style="background-color: rgb(63, 132, 210);">
                                    <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                    </svg>
                                </div>
                                <div class="flex-1 text-center md:text-left">
                                    <h4 class="text-lg font-semibold mb-2" style="color: rgb(0, 65, 110);">
                                        Stopa zastąpienia
                                    </h4>
                                    <div class="flex items-baseline gap-3 justify-center md:justify-start">
                                        <span class="text-5xl md:text-6xl font-bold" style="color: rgb(63, 132, 210);">
                                            {{ simulationResult.economic_context.replacement_rate.toFixed(1) }}%
                                        </span>
                                        <span class="text-lg text-gray-600">Twojego wynagrodzenia</span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-3 leading-relaxed">
                                        Twoja emerytura będzie stanowić <strong>{{ simulationResult.economic_context.replacement_rate.toFixed(1) }}%</strong> 
                                        ostatniego wynagrodzenia przed przejściem na emeryturę 
                                        ({{ formatCurrency(simulationResult.economic_context.future_gross_salary) }}).
                                    </p>
                                </div>
                            </div>
                    </div>

                    <!-- Porównanie ze średnią emeryturą -->
                    <div class="bg-white border border-gray-200 shadow-sm p-8 md:p-10">
                            <div class="flex flex-col md:flex-row items-center gap-6">
                                <div class="w-20 h-20 flex items-center justify-center" style="background-color: rgb(0, 153, 63);">
                                    <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                        <path d="M13 6a1 1 0 011 1v3a1 1 0 11-2 0V7a1 1 0 011-1z" />
                                    </svg>
                                </div>
                                <div class="flex-1 text-center md:text-left">
                                    <h4 class="text-lg font-semibold mb-2" style="color: rgb(0, 65, 110);">
                                        Porównanie ze średnią krajową
                                    </h4>
                                    <div class="flex items-baseline gap-3 justify-center md:justify-start mb-3">
                                        <span class="text-5xl md:text-6xl font-bold" style="color: rgb(0, 153, 63);">
                                            {{ simulationResult.economic_context.pension_to_average_ratio.toFixed(0) }}%
                                        </span>
                                        <span class="text-lg text-gray-600">średniej emerytury</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 mt-4">
                                        <div class="border-2 p-4" style="border-color: rgb(190, 195, 206); background-color: rgba(190, 195, 206, 0.05);">
                                            <p class="text-xs text-gray-600 mb-1">Twoja emerytura</p>
                                            <p class="text-xl font-bold" style="color: rgb(0, 65, 110);">
                                                {{ formatCurrency(simulationResult.monthly_pension) }}
                                            </p>
                                        </div>
                                        <div class="border-2 p-4" style="border-color: rgb(0, 153, 63); background-color: rgba(0, 153, 63, 0.05);">
                                            <p class="text-xs text-gray-600 mb-1">Średnia w {{ formData.retirement_year }}</p>
                                            <p class="text-xl font-bold" style="color: rgb(0, 153, 63);">
                                                {{ formatCurrency(simulationResult.economic_context.average_pension_in_retirement_year) }}
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-3 leading-relaxed">
                                        <template v-if="simulationResult.economic_context.pension_to_average_ratio > 100">
                                            Twoja prognozowana emerytura będzie <strong>wyższa</strong> o 
                                            <strong>{{ (simulationResult.economic_context.pension_to_average_ratio - 100).toFixed(0) }}%</strong> 
                                            od prognozowanej średniej emerytury w Polsce w {{ formData.retirement_year }} roku.
                                        </template>
                                        <template v-else-if="simulationResult.economic_context.pension_to_average_ratio < 100">
                                            Twoja prognozowana emerytura będzie <strong>niższa</strong> o 
                                            <strong>{{ (100 - simulationResult.economic_context.pension_to_average_ratio).toFixed(0) }}%</strong> 
                                            od prognozowanej średniej emerytury w Polsce w {{ formData.retirement_year }} roku.
                                        </template>
                                        <template v-else>
                                            Twoja prognozowana emerytura będzie na poziomie średniej emerytury w Polsce w {{ formData.retirement_year }} roku.
                                        </template>
                                    </p>
                                </div>
                            </div>
                    </div>

                    <!-- Inflacja - mniejsza karta -->
                    <div class="bg-white border border-gray-200 shadow-sm p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 flex items-center justify-center" style="background-color: rgb(240, 94, 94);">
                                    <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold mb-1" style="color: rgb(0, 65, 110);">
                                        Inflacja skumulowana do roku emerytury
                                    </h4>
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-3xl font-bold" style="color: rgb(240, 94, 94);">
                                            {{ simulationResult.economic_context.cumulative_inflation.toFixed(1) }}%
                                        </span>
                                        <span class="text-sm text-gray-600">w okresie {{ simulationResult.years_to_retirement }} lat</span>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>

                <!-- Prognozy makroekonomiczne -->
                <div v-if="simulationResult.economic_context" class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                    <div class="mb-6">
                        <h4 class="text-2xl font-bold flex items-center gap-3" style="color: rgb(0, 65, 110);">
                            <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(0, 153, 63);">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                </svg>
                            </div>
                            <div>
                                <div>Prognozy ekonomiczne ZUS</div>
                                <div class="text-sm font-normal text-gray-600 mt-1">{{ simulationResult.economic_context.variant_name }}</div>
                            </div>
                        </h4>
                    </div>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="border-2 p-5" style="border-color: rgb(0, 153, 63); background-color: rgba(0, 153, 63, 0.05);">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-gray-600 font-medium">Średni wzrost PKB (rocznie)</p>
                                    <svg class="w-5 h-5" style="color: rgb(0, 153, 63);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="text-2xl font-bold" style="color: rgb(0, 153, 63);">
                                    {{ simulationResult.economic_context.avg_gdp_growth.toFixed(2) }}%
                                </div>
                                <p class="text-xs text-gray-500 mt-2">W okresie do emerytury</p>
                            </div>

                            <div class="border-2 p-5" style="border-color: rgb(63, 132, 210); background-color: rgba(63, 132, 210, 0.05);">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-gray-600 font-medium">Średnia stopa bezrobocia</p>
                                    <svg class="w-5 h-5" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="text-2xl font-bold" style="color: rgb(63, 132, 210);">
                                    {{ simulationResult.economic_context.avg_unemployment_rate.toFixed(1) }}%
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Prognoza ZUS do {{ formData.retirement_year }}</p>
                            </div>
                        </div>

                        <div class="mt-6 border-l-4 p-5" style="background-color: rgba(63, 132, 210, 0.1); border-color: rgb(63, 132, 210);">
                            <div class="flex items-start gap-3">
                                <svg class="w-6 h-6 flex-shrink-0 mt-0.5" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-gray-700 leading-relaxed">
                                    Prognozy bazują na oficjalnych danych ZUS ({{ simulationResult.economic_context.variant_name }}), 
                                    uwzględniających realistyczne scenariusze rozwoju gospodarczego Polski do roku {{ formData.retirement_year }}.
                                </p>
                            </div>
                        </div>
                </div>

                <!-- Wpływ zwolnień lekarskich - ZAWSZE pokazuj -->
                <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                    <div class="mb-6">
                        <h4 class="text-2xl font-bold flex items-center gap-3" style="color: rgb(0, 65, 110);">
                            <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(255, 179, 79);">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>Wpływ zwolnień lekarskich na emeryturę</span>
                        </h4>
                    </div>
                        <!-- Porównanie z/bez zwolnień -->
                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div class="border-2 p-5" style="border-color: rgb(0, 153, 63); background-color: rgba(0, 153, 63, 0.05);">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5" style="color: rgb(0, 153, 63);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-gray-600 font-bold">Bez uwzględnienia zwolnień</p>
                                </div>
                                <div class="text-3xl font-bold mb-2" style="color: rgb(0, 153, 63);">
                                    {{ formatCurrency(simulationResult.monthly_pension_without_sick_leave) }}
                                </div>
                                <p class="text-xs text-gray-600">Przy idealnej frekwencji</p>
                            </div>
                            
                            <div class="border-2 p-5" style="border-color: rgb(240, 94, 94); background-color: rgba(240, 94, 94, 0.05);">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5" style="color: rgb(240, 94, 94);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-gray-600 font-bold">Z uwzględnieniem zwolnień</p>
                                </div>
                                <div class="text-3xl font-bold mb-2" style="color: rgb(240, 94, 94);">
                                    {{ formatCurrency(simulationResult.monthly_pension) }}
                                </div>
                                <p class="text-xs text-gray-600">
                                    -{{ formatCurrency(simulationResult.sick_leave_impact.pension_reduction) }} 
                                    ({{ simulationResult.sick_leave_impact.percentage_reduction.toFixed(2) }}%)
                                </p>
                            </div>
                        </div>

                        <div class="border p-5 mb-6" style="border-color: rgb(190, 195, 206); background-color: rgba(190, 195, 206, 0.05);">
                            <div class="flex items-center gap-3 mb-3">
                                <svg class="w-6 h-6" style="color: rgb(0, 65, 110);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-gray-700 font-bold">Statystyka zwolnień lekarskich</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-600 mb-1">Łączna liczba dni</p>
                                    <p class="text-2xl font-bold" style="color: rgb(0, 65, 110);">{{ simulationResult.sick_leave_impact.average_days }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 mb-1">W całej karierze zawodowej</p>
                                    <p class="text-2xl font-bold" style="color: rgb(0, 65, 110);">
                                        {{ (simulationResult.sick_leave_impact.average_days / (simulationResult.years_to_retirement + (formData.age ? parseInt(formData.age) - 25 : 0))).toFixed(0) }} dni/rok
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="border-l-4 p-5" style="background-color: rgba(255, 179, 79, 0.1); border-color: rgb(255, 179, 79);">
                            <div class="flex items-start gap-3">
                                <svg class="w-6 h-6 flex-shrink-0 mt-0.5" style="color: rgb(255, 179, 79);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1 a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-gray-700 leading-relaxed">
                                    <strong>Wyjaśnienie:</strong> Podczas zwolnienia lekarskiego składki emerytalne są odprowadzane w niższej wysokości 
                                    (około 80% utraty składki). Średnio {{ formData.gender === 'male' ? 'mężczyzna' : 'kobieta' }} pracujący w Polsce 
                                    przebywa przez całą karierę na zwolnieniu przez około {{ simulationResult.sick_leave_impact.average_days }} dni, 
                                    co realnie wpływa na wysokość przyszłej emerytury.
                                    <template v-if="formData.include_sick_leave">
                                        <br><br><strong>W Twojej symulacji uwzględniono zwolnienia lekarskie.</strong>
                                    </template>
                                    <template v-else>
                                        <br><br><strong>W Twojej symulacji NIE uwzględniono zwolnień lekarskich</strong> - wyświetlana emerytura zakłada idealną frekwencję.
                                    </template>
                                </p>
                            </div>
                        </div>
                </div>

                <!-- Opcje odroczenia emerytury -->
                <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                    <div class="mb-6">
                        <h4 class="text-2xl font-bold flex items-center gap-3" style="color: rgb(0, 65, 110);">
                            <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(63, 132, 210);">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span>Co zyskasz odkładając emeryturę?</span>
                        </h4>
                    </div>
                        <p class="text-gray-700 mb-6 leading-relaxed">
                            Sprawdź, o ile wzrośnie Twoja emerytura, jeśli zdecydujesz się pracować dłużej po osiągnięciu wieku emerytalnego.
                        </p>
                        
                        <div class="space-y-4">
                            <div v-for="option in simulationResult.delayed_retirement_options" :key="option.delay_years"
                                class="border-2 p-5 transition-all duration-300" style="border-color: rgb(63, 132, 210); background-color: rgba(63, 132, 210, 0.02);">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="w-10 h-10 flex items-center justify-center text-white font-bold text-lg" style="background-color: rgb(63, 132, 210);">
                                                +{{ option.delay_years }}
                                            </div>
                                            <div>
                                                <p class="text-lg font-bold" style="color: rgb(0, 65, 110);">
                                                    Odroczenie o {{ option.delay_years }} {{ option.delay_years === 1 ? 'rok' : 'lata' }}
                                                </p>
                                                <p class="text-sm text-gray-600">Przejście na emeryturę w {{ option.retirement_year }} roku</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
                                        <div class="text-center md:text-right">
                                            <p class="text-xs text-gray-600 mb-1">Miesięczna emerytura</p>
                                            <p class="text-2xl md:text-3xl font-bold" style="color: rgb(63, 132, 210);">
                                                {{ formatCurrency(option.monthly_pension) }}
                                            </p>
                                        </div>
                                        <div class="px-4 py-2" style="background-color: rgba(0, 153, 63, 0.1);">
                                            <p class="text-xs text-gray-600 mb-1">Zysk</p>
                                            <p class="text-xl font-bold" style="color: rgb(0, 153, 63);">
                                                +{{ formatCurrency(option.monthly_pension - simulationResult.monthly_pension) }}
                                            </p>
                                            <p class="text-xs font-semibold" style="color: rgb(0, 153, 63);">
                                                +{{ ((option.monthly_pension / simulationResult.monthly_pension - 1) * 100).toFixed(1) }}%
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 border-l-4 p-5" style="background-color: rgba(63, 132, 210, 0.1); border-color: rgb(63, 132, 210);">
                            <div class="flex items-start gap-3">
                                <svg class="w-6 h-6 flex-shrink-0 mt-0.5" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-gray-700 leading-relaxed">
                                    <strong>Dlaczego warto odroczyć emeryturę?</strong> Pracując dłużej zwiększasz zgromadzony kapitał emerytalny 
                                    (dodatkowe składki) oraz skracasz statystyczny okres pobierania emerytury, co przekłada się na wyższe miesięczne świadczenie. 
                                    Dodatkowo emerytury rosną wraz ze wzrostem wynagrodzeń w gospodarce.
                                </p>
                            </div>
                        </div>
                </div>

                <!-- Ważne informacje -->
                <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                    <div class="mb-6">
                        <h4 class="text-2xl font-bold flex items-center gap-3" style="color: rgb(0, 65, 110);">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <span>Ważne informacje o symulacji</span>
                        </h4>
                    </div>
                        <ul class="space-y-3">
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 flex items-center justify-center flex-shrink-0 mt-0.5" style="background-color: rgba(0, 153, 63, 0.1);">
                                    <svg class="w-4 h-4" style="color: rgb(0, 153, 63);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Obliczenia bazują na oficjalnych prognozach demograficzno-ekonomicznych ZUS do 2080 roku z uwzględnieniem realnych wskaźników PKB, inflacji i bezrobocia</span>
                            </li>
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 flex items-center justify-center flex-shrink-0 mt-0.5" style="background-color: rgba(0, 153, 63, 0.1);">
                                    <svg class="w-4 h-4" style="color: rgb(0, 153, 63);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Składka emerytalna wynosi 19,52% wynagrodzenia brutto, z czego 12,22% trafia na Twoje indywidualne konto w ZUS</span>
                            </li>
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 flex items-center justify-center flex-shrink-0 mt-0.5" style="background-color: rgba(0, 153, 63, 0.1);">
                                    <svg class="w-4 h-4" style="color: rgb(0, 153, 63);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Wysokość emerytury jest ilorazem zgromadzonego kapitału i średniego dalszego trwania życia według tablic GUS (kobiety: 25 lat, mężczyźni: 20 lat)</span>
                            </li>
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 flex items-center justify-center flex-shrink-0 mt-0.5" style="background-color: rgba(255, 179, 79, 0.1);">
                                    <svg class="w-4 h-4" style="color: rgb(255, 179, 79);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Symulacja uwzględnia waloryzację składek (wzrost o 5% rocznie) oraz prognozy wzrostu płac, ale nie może przewidzieć zmian prawnych w systemie emerytalnym</span>
                            </li>
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 flex items-center justify-center flex-shrink-0 mt-0.5" style="background-color: rgba(63, 132, 210, 0.1);">
                                    <svg class="w-4 h-4" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Jeśli nie podano salda kont, system oszacował je na podstawie zakładanego rozpoczęcia pracy w wieku 25 lat i stałych wpłat składek od Twojego obecnego wynagrodzenia</span>
                            </li>
                            <li class="flex items-start gap-3 text-gray-700">
                                <div class="w-6 h-6 flex items-center justify-center flex-shrink-0 mt-0.5" style="background-color: rgba(240, 94, 94, 0.1);">
                                    <svg class="w-4 h-4" style="color: rgb(240, 94, 94);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="leading-relaxed">Rzeczywista emerytura może się różnić z uwagi na: przerwy w zatrudnieniu, zmiany wynagrodzenia, reformy emerytalne, faktyczny wzrost gospodarczy oraz realną długość życia</span>
                            </li>
                        </ul>
                </div>

                <!-- Akcje -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <button
                        @click="resetForm"
                        class="flex-1 px-8 py-4 text-lg font-semibold border-2 transition-all shadow-sm hover:shadow-md flex items-center justify-center"
                        style="color: rgb(0, 65, 110); border-color: rgb(0, 65, 110); background-color: white;"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Wykonaj nową symulację
                    </button>
                    <Link
                        :href="getAdvancedDashboardUrl()"
                        class="flex-1 px-8 py-4 text-lg font-semibold text-white transition-all shadow-sm hover:shadow-md flex items-center justify-center hover:opacity-90"
                        style="background-color: rgb(63, 132, 210);"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Zaawansowany Dashboard
                    </Link>
                    <Link
                        :href="home()"
                        class="flex-1 px-8 py-4 text-lg font-semibold text-white transition-all shadow-sm hover:shadow-md flex items-center justify-center hover:opacity-90"
                        style="background-color: rgb(0, 153, 63);"
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
        <footer class="mt-12 pb-8 text-center text-gray-600">
            <p class="text-sm">
                © 2025 Zakład Ubezpieczeń Społecznych - Symulator służy wyłącznie celom informacyjnym
            </p>
        </footer>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap');
</style>

<style scoped>
/* Czcionka Lato dla całej strony ZUS */
.zus-page {
  font-family: "Lato Regular", "Helvetica Neue", Helvetica, Arial, sans-serif;
}

.zus-page * {
  font-family: "Lato", "Helvetica Neue", Helvetica, Arial, sans-serif;
}

a {
  text-decoration: none;
}

button:focus,
a:focus {
  outline: 2px solid rgb(0, 153, 63);
  outline-offset: 2px;
}

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
input:focus {
    border-color: rgb(0, 153, 63) !important;
    box-shadow: 0 0 0 3px rgba(0, 153, 63, 0.2);
}

button:focus {
    outline: 2px solid rgb(0, 153, 63);
    outline-offset: 2px;
}

/* Hover dla przycisków */
button[type="submit"]:not(:disabled):hover {
    opacity: 0.9;
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

/* Header responsywność */
header {
  position: relative;
}

header .flex.items-center.justify-between {
  flex-wrap: nowrap;
  gap: 1rem;
}

header .flex.items-center.flex-wrap {
  justify-content: flex-end;
}

/* Logo responsywność */
@media (max-width: 640px) {
  header img[alt="ZUS Logo"] {
    height: 3rem;
  }
}

/* Bardzo małe ekrany mobilne */
@media (max-width: 640px) {
  .grid.grid-cols-1 {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
  }

  main h1 {
    font-size: 1.875rem;
    line-height: 2.25rem;
  }

  main h2 {
    font-size: 1.25rem;
    line-height: 1.75rem;
  }
}
</style>
