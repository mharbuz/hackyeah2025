<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch, reactive, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { home } from '@/routes';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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
        wage_indexation_rate: number;
        historical_data?: YearlyData[];
        future_data?: YearlyData[];
    };
    existingSimulationResults?: any;
}

const props = defineProps<Props>();

// Toast notifications
const toast = useToast();

// Typy
interface YearlyData {
    year: number;
    gross_salary: number;
    sick_leave_days: number;
}

interface FormData {
    age: string;
    gender: 'male' | 'female' | '';
    gross_salary: string;
    retirement_year: string;
    account_balance: string;
    subaccount_balance: string;
    wage_indexation_rate: string;
}

interface AccountGrowthData {
    year: number;
    age: number;
    account_balance: number;
    subaccount_balance: number;
    total_balance: number;
    annual_contribution: number;
    cumulative_contribution: number;
}

interface SimulationResult {
    monthly_pension: number;
    total_contributions: number;
    years_to_retirement: number;
    account_growth_forecast: AccountGrowthData[];
    economic_context?: {
        future_gross_salary: number;
        replacement_rate: number;
        purchasing_power_today: number;
        avg_gdp_growth: number;
        avg_unemployment_rate: number;
        cumulative_inflation: number;
        variant_name: string;
        average_pension_in_retirement_year: number;
        pension_to_average_ratio: number;
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
    wage_indexation_rate: '5.0'
});

// Dane historyczne (przeszłość)
const historicalData = ref<YearlyData[]>([]);

// Dane przyszłościowe (prognoza)
const futureData = ref<YearlyData[]>([]);

const errors = ref<any>({});
const isSubmitting = ref(false);
const showResults = ref(false);
const simulationResult = ref<SimulationResult | null>(null);
const activeDataView = ref<'history' | 'future'>('future'); // Przełącznik między historią a przyszłością
const hoveredPoint = ref<number | null>(null); // Index punktu na którym jest kursor
const isGeneratingPDF = ref(false); // Czy generuje się PDF

// Obliczenie wieku emerytalnego
const retirementAge = computed(() => {
    if (formData.value.gender === 'male') return 65;
    if (formData.value.gender === 'female') return 60;
    return null;
});

// Expected pension from session
const expectedPension = computed(() => props.expectedPension);

// Automatyczne obliczenie roku emerytalnego
watch([() => formData.value.age, () => formData.value.gender], () => {
    if (formData.value.age && retirementAge.value) {
        const currentYear = new Date().getFullYear();
        const age = parseInt(formData.value.age);
        const yearsToRetirement = retirementAge.value - age;

        if (yearsToRetirement >= 0) {
            formData.value.retirement_year = (currentYear + yearsToRetirement).toString();
            initializeFutureData();
        }
    }
});

// Wiek rozpoczęcia pracy (domyślnie 25 lat)
const workStartAge = ref(25);

// Inicjalizacja danych historycznych
const initializeHistoricalData = () => {
    if (!formData.value.age) return;

    const currentYear = new Date().getFullYear();
    const age = parseInt(formData.value.age);
    const yearsWorked = Math.max(0, age - workStartAge.value);

    historicalData.value = [];
    for (let i = 0; i < yearsWorked; i++) {
        const year = currentYear - yearsWorked + i;
        historicalData.value.push({
            year,
            gross_salary: 0,
            sick_leave_days: 0
        });
    }
};

// Inicjalizacja danych przyszłościowych
const initializeFutureData = () => {
    if (!formData.value.age || !formData.value.retirement_year) return;

    const currentYear = new Date().getFullYear();
    const retirementYear = parseInt(formData.value.retirement_year);
    const yearsToRetirement = retirementYear - currentYear;

    futureData.value = [];
    for (let i = 1; i <= yearsToRetirement; i++) {
        const year = currentYear + i;
        futureData.value.push({
            year,
            gross_salary: 0,
            sick_leave_days: 0
        });
    }
};

// Automatyczne wypełnienie przeszłości bazując na obecnym wynagrodzeniu
const autoFillHistoricalData = () => {
    // Jeśli nie ma danych historycznych, najpierw je zainicjalizuj
    if (historicalData.value.length === 0 && formData.value.age) {
        initializeHistoricalData();
    }

    if (!formData.value.gross_salary || historicalData.value.length === 0) {
        return;
    }

    const currentSalary = parseFloat(formData.value.gross_salary);
    const avgGrowthRate = 0.05; // 5% wzrost rocznie

    for (let i = 0; i < historicalData.value.length; i++) {
        const yearsAgo = historicalData.value.length - i;
        const salary = currentSalary / Math.pow(1 + avgGrowthRate, yearsAgo);
        historicalData.value[i].gross_salary = Math.round(salary);
        historicalData.value[i].sick_leave_days = Math.floor(Math.random() * 5) + 5; // 5-10 dni
    }
};

// Automatyczne wypełnienie przyszłości
const autoFillFutureData = () => {
    if (!formData.value.gross_salary || futureData.value.length === 0) return;

    const currentSalary = parseFloat(formData.value.gross_salary);
    const indexationRate = parseFloat(formData.value.wage_indexation_rate) / 100;

    for (let i = 0; i < futureData.value.length; i++) {
        const salary = currentSalary * Math.pow(1 + indexationRate, i + 1);
        futureData.value[i].gross_salary = Math.round(salary);
        futureData.value[i].sick_leave_days = 7; // Średnio 7 dni rocznie
    }
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

// Walidacja formularza
const validateForm = (): boolean => {
    errors.value = {};

    if (!formData.value.age) {
        errors.value.age = 'Wiek jest wymagany';
        return false;
    }

    if (!formData.value.gender) {
        errors.value.gender = 'Płeć jest wymagana';
        return false;
    }

    if (!formData.value.gross_salary) {
        errors.value.gross_salary = 'Wynagrodzenie jest wymagane';
        return false;
    }

    return true;
};

// Obsługa symulacji
const handleSimulate = async () => {
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
            wage_indexation_rate: parseFloat(formData.value.wage_indexation_rate),
            historical_data: historicalData.value,
            future_data: futureData.value
        };

        // Include session UUID if present
        if (props.sessionUuid) {
            requestBody.session_uuid = props.sessionUuid;
        }

        const response = await fetch('/api/pension/advanced-simulate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(requestBody)
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Wystąpił błąd podczas przetwarzania');
        }

        simulationResult.value = data;
        showResults.value = true;

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

// Resetowanie formularza
const resetForm = () => {
    formData.value = {
        age: '',
        gender: '',
        gross_salary: '',
        retirement_year: '',
        account_balance: '',
        subaccount_balance: '',
        wage_indexation_rate: '5.0'
    };
    historicalData.value = [];
    futureData.value = [];
    showResults.value = false;
    simulationResult.value = null;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Dodanie roku historycznego
const addHistoricalYear = () => {
    const currentYear = new Date().getFullYear();
    const lastYear = historicalData.value.length > 0
        ? historicalData.value[historicalData.value.length - 1].year + 1
        : currentYear - 1;

    historicalData.value.push({
        year: lastYear,
        gross_salary: 0,
        sick_leave_days: 0
    });
};

// Usunięcie roku historycznego
const removeHistoricalYear = (index: number) => {
    historicalData.value.splice(index, 1);
};

// Generowanie ścieżki linii dla wykresu SVG
const generateLinePath = (data: AccountGrowthData[], field: keyof AccountGrowthData): string => {
    if (!data || data.length === 0) return '';

    const maxValue = Math.max(...data.map(item => item.total_balance));
    const points = data.map((item, index) => {
        const x = (index / (data.length - 1)) * 100;
        const y = 100 - ((item[field] as number) / maxValue * 100);
        return `${x},${y}`;
    });

    return `M ${points.join(' L ')}`;
};

// Generowanie ścieżki obszaru dla wykresu SVG
const generateAreaPath = (data: AccountGrowthData[], field: keyof AccountGrowthData): string => {
    if (!data || data.length === 0) return '';

    const maxValue = Math.max(...data.map(item => item.total_balance));
    const points = data.map((item, index) => {
        const x = (index / (data.length - 1)) * 100;
        const y = 100 - ((item[field] as number) / maxValue * 100);
        return `${x},${y}`;
    });

    // Tworzymy obszar: linia + zamknięcie do dołu
    const linePath = `M ${points.join(' L ')}`;
    const closePath = ` L 100,100 L 0,100 Z`;

    return linePath + closePath;
};

// Generowanie URL do udostępnienia
const getShareUrl = () => {
    if (!props.sessionUuid) return '';
    const baseUrl = window.location.origin;
    return `${baseUrl}/dashboard-prognozowania?session=${props.sessionUuid}`;
};

// Kopiowanie linku do schowka
const copyShareLink = async () => {
    try {
        await navigator.clipboard.writeText(getShareUrl());
        toast.success('Link został skopiowany do schowka!');
    } catch (err) {
        console.error('Błąd kopiowania do schowka:', err);
        // Fallback dla starszych przeglądarek
        const textArea = document.createElement('textarea');
        textArea.value = getShareUrl();
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        toast.success('Link został skopiowany do schowka!');
    }
};

// Generowanie raportu PDF
const generatePDF = async () => {
    if (!simulationResult.value) return;

    isGeneratingPDF.value = true;

    try {
        const response = await fetch('/api/pension/generate-pdf-report', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                // Dane wejściowe
                profile: {
                    age: parseInt(formData.value.age),
                    gender: formData.value.gender,
                    work_start_age: workStartAge.value,
                    current_gross_salary: parseFloat(formData.value.current_gross_salary),
                    retirement_year: parseInt(formData.value.retirement_year),
                    account_balance: formData.value.account_balance ? parseFloat(formData.value.account_balance) : null,
                    subaccount_balance: formData.value.subaccount_balance ? parseFloat(formData.value.subaccount_balance) : null,
                    wage_indexation_rate: parseFloat(formData.value.wage_indexation_rate),
                },
                // Wyniki symulacji
                simulation_results: simulationResult.value,
                // Dane historyczne i przyszłościowe
                historical_data: historicalData.value,
                future_data: futureData.value,
            })
        });

        if (!response.ok) {
            throw new Error('Błąd podczas generowania raportu PDF');
        }

        // Pobierz PDF jako blob
        const blob = await response.blob();

        // Utwórz link do pobrania
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Raport_Emerytalny_${formData.value.age}lat_${new Date().toISOString().split('T')[0]}.pdf`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        // Pokaż komunikat sukcesu
        toast.success('Raport PDF został pomyślnie wygenerowany i pobrany!');

    } catch (error) {
        console.error('Błąd generowania PDF:', error);
        toast.error('Wystąpił błąd podczas generowania raportu PDF. Spróbuj ponownie.');
    } finally {
        isGeneratingPDF.value = false;
    }
};

// Populate form with existing data if available
onMounted(() => {
    if (props.existingFormData) {
        formData.value = {
            age: props.existingFormData.age?.toString() || '',
            gender: props.existingFormData.gender || '',
            gross_salary: props.existingFormData.gross_salary?.toString() || '',
            retirement_year: props.existingFormData.retirement_year?.toString() || '',
            account_balance: props.existingFormData.account_balance?.toString() || '',
            subaccount_balance: props.existingFormData.subaccount_balance?.toString() || '',
            wage_indexation_rate: props.existingFormData.wage_indexation_rate?.toString() || '5.0'
        };

        // Populate historical data if available
        if (props.existingFormData.historical_data && Array.isArray(props.existingFormData.historical_data)) {
            historicalData.value = props.existingFormData.historical_data;
        }

        // Populate future data if available
        if (props.existingFormData.future_data && Array.isArray(props.existingFormData.future_data)) {
            futureData.value = props.existingFormData.future_data;
        }
    }

    // If there are existing simulation results, show them
    if (props.existingSimulationResults) {
        simulationResult.value = props.existingSimulationResults;
        showResults.value = true;
    }
});
</script>

<template>
    <Head title="Zaawansowany Dashboard Prognozowania - ZUS">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-white zus-page">
        <!-- Header -->
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
                        Rozbudowana prognoza emerytury
                    </h2>
                    <p class="text-base lg:text-lg text-gray-700 max-w-2xl mx-auto">
                        Wprowadź szczegółowe dane o swojej karierze zawodowej dla dokładnej prognozy emerytury
                    </p>
                    <!-- Expected Pension Display -->
                    <div v-if="expectedPension" class="mt-8 bg-white border border-gray-200 shadow-sm p-6 rounded-lg">
                        <div class="text-center">
                            <div class="w-16 h-16 flex items-center justify-center mx-auto mb-4" style="background-color: rgb(0, 153, 63);">
                                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold mb-2" style="color: rgb(0, 65, 110);">Twoja oczekiwana emerytura</h3>
                            <p class="text-3xl font-bold mb-2" style="color: rgb(0, 153, 63);">
                                {{ expectedPension.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} zł
                            </p>
                            <p class="text-sm text-gray-600">Cel do osiągnięcia w Twojej rozbudowanej prognozie</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Główny formularz -->
            <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12 mb-8">
                <div class="mb-8">
                    <h3 class="text-2xl md:text-3xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                        Dane do prognozy
                    </h3>
                    <p class="text-gray-600 text-base md:text-lg">
                        Wypełnij formularz, aby otrzymać szczegółową prognozę emerytury
                    </p>
                </div>

                <form @submit.prevent="handleSimulate" class="space-y-10">
                        <!-- SEKCJA 1: DANE PODSTAWOWE (wspólne z symulacją podstawową) -->
                        <div class="space-y-6">
                            <div class="flex items-center gap-3 pb-3 border-b-2" style="border-bottom-color: rgb(0, 153, 63);">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm" style="background-color: rgb(0, 153, 63);">
                                    1
                                </div>
                                <h3 class="text-xl md:text-2xl font-bold" style="color: rgb(0, 65, 110);">
                                    Informacje podstawowe
                                </h3>
                                <span class="text-sm text-gray-600 bg-gray-100 px-3 py-1 rounded-full">
                                    Wspólne z symulacją podstawową
                                </span>
                            </div>

                            <!-- Wiek, Płeć, Rok emerytury -->
                            <div class="grid md:grid-cols-3 gap-6">
                                <!-- Wiek -->
                                <div class="space-y-3">
                                    <Label for="age" class="text-base font-semibold" style="color: rgb(0, 65, 110);">
                                        Obecny wiek <span style="color: rgb(240, 94, 94);">*</span>
                                    </Label>
                                    <Input
                                        id="age"
                                        v-model="formData.age"
                                        type="number"
                                        min="18"
                                        max="100"
                                        placeholder="np. 35"
                                        @blur="initializeHistoricalData"
                                        class="text-lg h-14 font-bold bg-white border-2"
                                        style="color: rgb(0, 65, 110);"
                                        required
                                    />
                                </div>

                                <!-- Płeć -->
                                <div class="space-y-3">
                                    <Label class="text-base font-semibold" style="color: rgb(0, 65, 110);">
                                        Płeć <span style="color: rgb(240, 94, 94);">*</span>
                                    </Label>
                                    <div class="flex gap-3">
                                        <button
                                            type="button"
                                            @click="formData.gender = 'male'"
                                            :class="[
                                                'flex-1 h-14 rounded-lg border-2 font-semibold text-base transition-all duration-300 hover:scale-105',
                                                formData.gender === 'male'
                                                    ? 'text-white border-transparent shadow-lg'
                                                    : 'border-[rgb(190,195,206)] hover:border-[rgb(63,132,210)] hover:shadow-md'
                                            ]"
                                            :style="formData.gender === 'male' ? 'background-color: rgb(63, 132, 210); color: white;' : 'background-color: white; color: rgb(0, 65, 110);'"
                                        >
                                            Mężczyzna
                                        </button>
                                        <button
                                            type="button"
                                            @click="formData.gender = 'female'"
                                            :class="[
                                                'flex-1 h-14 rounded-lg border-2 font-semibold text-base transition-all duration-300 hover:scale-105',
                                                formData.gender === 'female'
                                                    ? 'text-white border-transparent shadow-lg'
                                                    : 'border-[rgb(190,195,206)] hover:border-[rgb(63,132,210)] hover:shadow-md'
                                            ]"
                                            :style="formData.gender === 'female' ? 'background-color: rgb(63, 132, 210); color: white;' : 'background-color: white; color: rgb(0, 65, 110);'"
                                        >
                                            Kobieta
                                        </button>
                                    </div>
                                    <p v-if="retirementAge" class="text-sm font-medium" style="color: rgb(0, 153, 63);">
                                        Wiek emerytalny: {{ retirementAge }} lat
                                    </p>
                                </div>

                                <!-- Rok emerytury -->
                                <div class="space-y-3">
                                    <Label for="retirement_year" class="text-base font-semibold" style="color: rgb(0, 65, 110);">
                                        Rok emerytury <span style="color: rgb(240, 94, 94);">*</span>
                                    </Label>
                                    <Input
                                        id="retirement_year"
                                        v-model="formData.retirement_year"
                                        type="number"
                                        :min="new Date().getFullYear()"
                                        placeholder="np. 2055"
                                        class="text-lg h-14 font-bold bg-white border-2"
                                        style="color: rgb(0, 65, 110);"
                                        required
                                    />
                                </div>
                            </div>

                            <!-- Wynagrodzenie i salda -->
                            <div class="grid md:grid-cols-3 gap-6">
                                <div class="space-y-3">
                                    <Label for="gross_salary" class="text-base font-semibold" style="color: rgb(0, 65, 110);">
                                        Obecne wynagrodzenie brutto <span style="color: rgb(240, 94, 94);">*</span>
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="gross_salary"
                                            v-model="formData.gross_salary"
                                            type="number"
                                            step="100"
                                            placeholder="np. 5000"
                                            class="text-lg h-14 pr-12 font-bold bg-white border-2"
                                            style="color: rgb(0, 65, 110);"
                                            required
                                        />
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-gray-400">zł</span>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <Label for="account_balance" class="text-base font-semibold" style="color: rgb(0, 65, 110);">
                                        Saldo konta ZUS
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="account_balance"
                                            v-model="formData.account_balance"
                                            type="number"
                                            step="100"
                                            placeholder="opcjonalnie"
                                            class="text-lg h-14 pr-12 font-bold bg-white border-2"
                                            style="color: rgb(0, 65, 110);"
                                        />
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-gray-400">zł</span>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <Label for="subaccount_balance" class="text-base font-semibold" style="color: rgb(0, 65, 110);">
                                        Saldo subkonta ZUS
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="subaccount_balance"
                                            v-model="formData.subaccount_balance"
                                            type="number"
                                            step="100"
                                            placeholder="opcjonalnie"
                                            class="text-lg h-14 pr-12 font-bold bg-white border-2"
                                            style="color: rgb(0, 65, 110);"
                                        />
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-gray-400">zł</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SEKCJA 2: DANE ZAAWANSOWANE (tylko dla dashboard prognozowania) -->
                        <div class="space-y-6">
                            <div class="flex items-center gap-3 pb-3 border-b-2" style="border-bottom-color: rgb(255, 179, 79);">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm" style="background-color: rgb(255, 179, 79);">
                                    2
                                </div>
                                <h3 class="text-xl md:text-2xl font-bold" style="color: rgb(0, 65, 110);">
                                    Dane zaawansowane
                                </h3>
                                <span class="text-sm text-gray-600 bg-orange-100 px-3 py-1 rounded-full">
                                    Tylko dla prognozy szczegółowej
                                </span>
                            </div>

                            <!-- Wiek rozpoczęcia pracy i wskaźnik indeksacji -->
                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Wiek rozpoczęcia pracy -->
                                <div class="space-y-3">
                                    <Label for="work_start_age" class="text-base font-semibold" style="color: rgb(0, 65, 110);">
                                        Wiek rozpoczęcia pracy
                                    </Label>
                                    <Input
                                        id="work_start_age"
                                        v-model.number="workStartAge"
                                        type="number"
                                        min="15"
                                        max="65"
                                        placeholder="np. 25"
                                        @blur="initializeHistoricalData"
                                        class="text-lg h-14 font-bold bg-white border-2"
                                        style="color: rgb(0, 65, 110);"
                                    />
                                    <p class="text-xs text-gray-600">
                                        Od tego wieku generujemy historię
                                    </p>
                                </div>

                                <!-- Wskaźnik indeksacji -->
                                <div class="space-y-3">
                                    <Label for="wage_indexation_rate" class="text-base font-semibold" style="color: rgb(0, 65, 110);">
                                        Wskaźnik indeksacji wynagrodzeń (% rocznie)
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="wage_indexation_rate"
                                            v-model="formData.wage_indexation_rate"
                                            type="number"
                                            step="0.1"
                                            placeholder="5.0"
                                            class="text-lg h-14 pr-12 font-bold bg-white border-2"
                                            style="color: rgb(0, 65, 110);"
                                        />
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-gray-400">%</span>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        Prognozowany średnioroczny wzrost wynagrodzeń
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- SEKCJA 3: DANE HISTORYCZNE I PRZYSZŁOŚCIOWE -->
                        <div class="space-y-6">
                            <div class="flex items-center gap-3 pb-3 border-b-2" style="border-bottom-color: rgb(63, 132, 210);">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm" style="background-color: rgb(63, 132, 210);">
                                    3
                                </div>
                                <h3 class="text-xl md:text-2xl font-bold" style="color: rgb(0, 65, 110);">
                                    Dane historyczne i przyszłościowe
                                </h3>
                                <span class="text-sm text-gray-600 bg-blue-100 px-3 py-1 rounded-full">
                                    Szczegółowa analiza zatrudnienia
                                </span>
                            </div>

                            <!-- Przełącznik: Historia vs Przyszłość -->
                            <div class="flex gap-3 bg-gray-100 p-2 rounded-lg">
                                <button
                                    type="button"
                                    @click="activeDataView = 'history'"
                                    :class="[
                                        'flex-1 h-12 rounded-lg font-semibold text-base transition-all duration-300',
                                        activeDataView === 'history'
                                            ? 'bg-white shadow-md'
                                            : 'text-gray-600'
                                    ]"
                                    :style="activeDataView === 'history' ? 'color: rgb(0, 65, 110);' : ''"
                                >
                                    📅 Historia zatrudnienia
                                </button>
                                <button
                                    type="button"
                                    @click="activeDataView = 'future'"
                                    :class="[
                                        'flex-1 h-12 rounded-lg font-semibold text-base transition-all duration-300',
                                        activeDataView === 'future'
                                            ? 'bg-white shadow-md'
                                            : 'text-gray-600'
                                    ]"
                                    :style="activeDataView === 'future' ? 'color: rgb(0, 65, 110);' : ''"
                                >
                                    🔮 Przyszłe prognozy
                                </button>
                            </div>

                            <!-- Historia -->
                            <div v-show="activeDataView === 'history'" class="space-y-6">
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-xl font-bold" style="color: rgb(0, 65, 110);">
                                                Dane historyczne
                                            </h3>
                                            <p v-if="historicalData.length > 0" class="text-sm text-gray-600 mt-1">
                                                Historia zatrudnienia od {{ workStartAge }} do {{ formData.age }} lat
                                                ({{ historicalData.length }} {{ historicalData.length === 1 ? 'rok' : (historicalData.length < 5 ? 'lata' : 'lat') }})
                                            </p>
                                        </div>
                                    <div class="flex gap-3">
                                        <Button
                                            type="button"
                                            @click="autoFillHistoricalData"
                                            variant="outline"
                                            class="h-12"
                                            style="color: rgb(0, 65, 110); border-color: rgb(0, 65, 110); background-color: white;"
                                        >
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            Wypełnij automatycznie
                                        </Button>
                                        <Button
                                            type="button"
                                            @click="addHistoricalYear"
                                            variant="outline"
                                            class="h-12"
                                            style="color: rgb(0, 65, 110); border-color: rgb(0, 65, 110); background-color: white;"
                                        >
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Dodaj rok
                                        </Button>
                                    </div>
                                </div>
                                </div>

                                <div v-if="historicalData.length === 0" class="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-gray-600 font-medium mb-2">
                                        Brak danych historycznych
                                    </p>
                                    <p class="text-gray-500 text-sm">
                                        Wprowadź obecny wiek i wiek rozpoczęcia pracy, aby wygenerować historię zatrudnienia
                                    </p>
                                </div>

                                <div v-else class="space-y-4 max-h-96 overflow-y-auto pr-2">
                                    <!-- Nagłówki kolumn -->
                                    <div class="grid grid-cols-[auto_1fr_1fr_auto] gap-4 items-center px-4 pb-2 border-b-2 sticky top-0 bg-white z-10" style="border-bottom-color: rgb(190, 195, 206);">
                                        <div class="font-bold text-sm w-20" style="color: rgb(0, 65, 110);">
                                            ROK
                                        </div>
                                        <div class="font-bold text-sm" style="color: rgb(0, 65, 110);">
                                            WYNAGRODZENIE BRUTTO (miesięcznie)
                                        </div>
                                        <div class="font-bold text-sm" style="color: rgb(0, 65, 110);">
                                            ZWOLNIENIA LEKARSKIE (dni rocznie)
                                        </div>
                                        <div class="w-12"></div>
                                    </div>

                                    <!-- Wiersze z danymi -->
                                    <div
                                        v-for="(item, index) in historicalData"
                                        :key="index"
                                        class="grid grid-cols-[auto_1fr_1fr_auto] gap-4 items-center bg-white p-4 rounded-lg border-2"
                                        style="border-color: rgb(190, 195, 206);"
                                    >
                                        <div class="font-bold text-lg w-20" style="color: rgb(0, 65, 110);">
                                            {{ item.year }}
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="item.gross_salary"
                                                type="number"
                                                placeholder="np. 5000"
                                                class="h-12 pr-10 font-bold border-2"
                                                style="color: rgb(0, 65, 110); background-color: white;"
                                            />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">zł</span>
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="item.sick_leave_days"
                                                type="number"
                                                placeholder="np. 7"
                                                class="h-12 pr-10 font-bold border-2"
                                                style="color: rgb(0, 65, 110); background-color: white;"
                                            />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">dni</span>
                                        </div>
                                        <Button
                                            type="button"
                                            @click="removeHistoricalYear(index)"
                                            variant="ghost"
                                            size="icon"
                                            class="h-12 w-12 hover:bg-[rgb(240,94,94)]/10"
                                            style="color: rgb(240, 94, 94);"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Przyszłość -->
                            <div v-show="activeDataView === 'future'" class="space-y-6">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-xl font-bold" style="color: rgb(0, 65, 110);">
                                        Prognozy przyszłościowe
                                    </h3>
                                    <Button
                                        type="button"
                                        @click="autoFillFutureData"
                                        variant="outline"
                                        class="h-12"
                                        style="color: rgb(0, 65, 110); border-color: rgb(0, 65, 110); background-color: white;"
                                    >
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        Wypełnij automatycznie
                                    </Button>
                                </div>

                                <div v-if="futureData.length === 0" class="text-center py-12 bg-gray-50 rounded-lg">
                                    <p class="text-gray-500">
                                        Wprowadź wiek i rok emerytury, aby zainicjalizować prognozy
                                    </p>
                                </div>

                                <div v-else class="space-y-4 max-h-96 overflow-y-auto pr-2">
                                    <!-- Nagłówki kolumn -->
                                    <div class="grid grid-cols-[auto_1fr_1fr] gap-4 items-center px-4 pb-2 border-b-2 sticky top-0 bg-white z-10" style="border-bottom-color: rgb(0, 153, 63);">
                                        <div class="font-bold text-sm w-20" style="color: rgb(0, 153, 63);">
                                            ROK
                                        </div>
                                        <div class="font-bold text-sm" style="color: rgb(0, 153, 63);">
                                            PROGNOZOWANE WYNAGRODZENIE BRUTTO (miesięcznie)
                                        </div>
                                        <div class="font-bold text-sm" style="color: rgb(0, 153, 63);">
                                            SPODZIEWANE ZWOLNIENIA (dni rocznie)
                                        </div>
                                    </div>

                                    <!-- Wiersze z danymi -->
                                    <div
                                        v-for="(item, index) in futureData"
                                        :key="index"
                                        class="grid grid-cols-[auto_1fr_1fr] gap-4 items-center p-4 rounded-lg border-2"
                                        style="border-color: rgb(0, 153, 63, 0.3);"
                                    >
                                        <div class="font-bold text-lg w-20" style="color: rgb(0, 153, 63);">
                                            {{ item.year }}
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="item.gross_salary"
                                                type="number"
                                                placeholder="np. 6000"
                                                class="h-12 pr-10 font-bold border-2"
                                                style="color: rgb(0, 65, 110); background-color: white;"
                                            />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">zł</span>
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="item.sick_leave_days"
                                                type="number"
                                                placeholder="np. 7"
                                                class="h-12 pr-10 font-bold border-2"
                                                style="color: rgb(0, 65, 110); background-color: white;"
                                            />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">dni</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Przycisk symulacji -->
                        <div class="pt-6">
                            <Button
                                type="submit"
                                :disabled="isSubmitting"
                                class="w-full h-16 text-xl font-bold text-white transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                style="background-color: rgb(255, 179, 79);"
                            >
                                <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-3 h-6 w-6 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                {{ isSubmitting ? 'Obliczam szczegółową prognozę...' : 'Zaprognozuj szczegółową emeryturę' }}
                            </Button>
                        </div>
                    </form>
            </div>

            <!-- Wyniki - Wykres wzrostu konta -->
            <div v-if="showResults && simulationResult" id="results" class="space-y-6 animate-slideUp">
                <!-- Główny wynik -->
                <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                    <div class="text-center mb-8">
                        <h3 class="text-2xl md:text-3xl font-bold mb-4" style="color: rgb(0, 65, 110);">
                            Twoja zaawansowana prognoza emerytury
                        </h3>
                    </div>

                    <!-- Dwie karty z kwotami -->
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <!-- Wysokość rzeczywista (nominalna) -->
                        <div class="border-2 p-6 text-center" style="border-color: rgb(63, 132, 210); background-color: rgb(63, 132, 210, 0.05);">
                            <div class="flex items-center justify-center gap-2 mb-3">
                                <svg class="w-6 h-6" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                </svg>
                                <h3 class="text-lg font-bold" style="color: rgb(63, 132, 210);">Wysokość rzeczywista</h3>
                            </div>
                            <div class="text-4xl md:text-5xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                                {{ formatCurrency(simulationResult.monthly_pension) }}
                            </div>
                            <p class="text-gray-600 text-sm leading-relaxed">
                                Kwota nominalna w {{ formData.retirement_year }} roku
                            </p>
                        </div>

                        <!-- Wysokość urealniona (siła nabywcza) -->
                        <div v-if="simulationResult.economic_context" class="border-2 p-6 text-center" style="border-color: rgb(255, 179, 79); background-color: rgb(255, 179, 79, 0.05);">
                            <div class="flex items-center justify-center gap-2 mb-3">
                                <svg class="w-6 h-6" style="color: rgb(255, 179, 79);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <h3 class="text-lg font-bold" style="color: rgb(255, 179, 79);">Wysokość urealniona</h3>
                            </div>
                            <div class="text-4xl md:text-5xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                                {{ formatCurrency(simulationResult.economic_context.purchasing_power_today) }}
                            </div>
                            <p class="text-gray-600 text-sm leading-relaxed">
                                Wartość w dzisiejszych cenach ({{ new Date().getFullYear() }} r.)
                            </p>
                        </div>
                    </div>

                    <!-- Informacja o czasie do emerytury -->
                    <div class="flex items-center justify-center gap-3 text-lg md:text-xl text-gray-700 bg-gray-50 rounded-lg py-4 px-6">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                        </svg>
                        <span>
                            Za {{ simulationResult.years_to_retirement }} {{ simulationResult.years_to_retirement === 1 ? 'rok' : (simulationResult.years_to_retirement < 5 ? 'lata' : 'lat') }}
                        </span>
                    </div>

                    <!-- Wyjaśnienie różnicy -->
                    <div v-if="simulationResult.economic_context" class="mt-6 border-l-4 p-6" style="background-color: rgba(255, 179, 79, 0.1); border-color: rgb(255, 179, 79);">
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" style="color: rgb(255, 179, 79);">
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

                <!-- Szczegóły -->
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="bg-white border border-gray-200 shadow-sm p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(0, 153, 63);">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                    <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold" style="color: rgb(0, 65, 110);">Składki emerytalne</h4>
                        </div>
                        <div class="text-4xl font-bold mb-2" style="color: rgb(0, 153, 63);">
                            {{ formatCurrency(simulationResult.total_contributions) }}
                        </div>
                        <p class="text-gray-600 leading-relaxed">
                            Łączna wartość składek odprowadzonych do emerytury (zgromadzone + przyszłe)
                        </p>
                    </div>

                    <div class="bg-white border border-gray-200 shadow-sm p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-10 h-10 flex items-center justify-center" style="background-color: rgb(63, 132, 210);">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold" style="color: rgb(0, 65, 110);">Czas do emerytury</h4>
                        </div>
                        <div class="text-4xl font-bold mb-2" style="color: rgb(63, 132, 210);">
                            {{ simulationResult.years_to_retirement }} {{ simulationResult.years_to_retirement === 1 ? 'rok' : (simulationResult.years_to_retirement < 5 ? 'lata' : 'lat') }}
                        </div>
                        <p class="text-gray-600 leading-relaxed">
                            Czas pozostały do osiągnięcia wieku emerytalnego i przejścia na emeryturę
                        </p>
                    </div>
                </div>

                <!-- Kluczowe wskaźniki ekonomiczne -->
                <div v-if="simulationResult.economic_context" class="space-y-6">
                    <!-- Tytuł sekcji -->
                    <div class="text-center">
                        <h3 class="text-2xl md:text-3xl font-bold mb-2" style="color: rgb(0, 65, 110);">
                            Kluczowe wskaźniki Twojej emerytury
                        </h3>
                        <p class="text-gray-600">
                            Porównanie Twojego świadczenia z prognozami ekonomicznymi
                        </p>
                    </div>

                    <!-- Współczynnik zastąpienia - duża karta -->
                    <Card class="shadow-2xl border-none bg-gradient-to-br from-[rgb(63,132,210)]/10 via-white to-[rgb(0,153,63)]/5 backdrop-blur-sm overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-[rgb(63,132,210)]/10 to-transparent rounded-full -mr-32 -mt-32"></div>
                        <CardContent class="p-8 md:p-10 relative">
                            <div class="flex flex-col md:flex-row items-center gap-6">
                                <div class="w-20 h-20 bg-gradient-to-br from-[rgb(63,132,210)] to-[rgb(0,65,110)] rounded-2xl flex items-center justify-center shadow-xl flex-shrink-0">
                                    <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                    </svg>
                                </div>
                                <div class="flex-1 text-center md:text-left">
                                    <h4 class="text-lg font-semibold text-[rgb(0,65,110)] mb-2">
                                        Stopa zastąpienia
                                    </h4>
                                    <div class="flex items-baseline gap-3 justify-center md:justify-start">
                                        <span class="text-5xl md:text-6xl font-bold text-[rgb(63,132,210)]">
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
                        </CardContent>
                    </Card>

                    <!-- Porównanie ze średnią emeryturą -->
                    <div class="bg-white border border-gray-200 shadow-sm p-8 md:p-10">
                            <div class="flex flex-col md:flex-row items-center gap-6">
                                <div class="w-20 h-20 flex items-center justify-center rounded-lg shadow-xl flex-shrink-0" style="background-color: rgb(0, 153, 63);">
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
                                        <span class="text-5xl md:text-6xl font-bold text-[rgb(0,153,63)]">
                                            {{ simulationResult.economic_context.pension_to_average_ratio.toFixed(0) }}%
                                        </span>
                                        <span class="text-lg text-gray-600">średniej emerytury</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 mt-4">
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <p class="text-xs text-gray-600 mb-1">Twoja emerytura</p>
                                            <p class="text-xl font-bold" style="color: rgb(0, 65, 110);">
                                                {{ formatCurrency(simulationResult.monthly_pension) }}
                                            </p>
                                        </div>
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <p class="text-xs text-gray-600 mb-1">Średnia w {{ formData.retirement_year }}</p>
                                            <p class="text-xl font-bold" style="color: rgb(0, 153, 63);">
                                                {{ formatCurrency(simulationResult.economic_context.average_pension_in_retirement_year) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>

                    <!-- Inflacja -->
                    <Card class="shadow-xl border-none hover:shadow-2xl transition-all duration-300 bg-gradient-to-br from-[rgb(240,94,94)]/10 to-white backdrop-blur-sm">
                        <CardContent class="p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-[rgb(240,94,94)] to-[rgb(240,94,94)]/80 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold text-[rgb(0,65,110)] mb-1">
                                        Inflacja skumulowana do roku emerytury
                                    </h4>
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-3xl font-bold text-[rgb(240,94,94)]">
                                            {{ simulationResult.economic_context.cumulative_inflation.toFixed(1) }}%
                                        </span>
                                        <span class="text-sm text-gray-600">w okresie {{ simulationResult.years_to_retirement }} lat</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Prognozy makroekonomiczne -->
                <div v-if="simulationResult.economic_context" class="bg-white border border-gray-200 shadow-sm">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background-color: rgb(0, 153, 63);">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xl font-bold" style="color: rgb(0, 65, 110);">Prognozy ekonomiczne ZUS</div>
                                <div class="text-sm font-normal text-gray-600 mt-1">{{ simulationResult.economic_context.variant_name }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-gray-600 font-medium">Średni wzrost PKB (rocznie)</p>
                                    <svg class="w-5 h-5" style="color: rgb(0, 153, 63);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="text-2xl font-bold text-[rgb(0,153,63)]">
                                    {{ simulationResult.economic_context.avg_gdp_growth.toFixed(2) }}%
                                </div>
                                <p class="text-xs text-gray-500 mt-2">W okresie do emerytury</p>
                            </div>

                            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-gray-600 font-medium">Średnia stopa bezrobocia</p>
                                    <svg class="w-5 h-5" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="text-2xl font-bold text-[rgb(63,132,210)]">
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
                </div>

                <!-- Wpływ zwolnień lekarskich - ZAWSZE pokazuj -->
                <div class="bg-white border border-gray-200 shadow-sm">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 flex items-center justify-center rounded-lg" style="background-color: rgb(255, 179, 79);">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <span class="text-xl font-bold" style="color: rgb(0, 65, 110);">Wpływ zwolnień lekarskich na emeryturę</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Porównanie z/bez zwolnień -->
                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
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

                            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
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

                        <div class="bg-gray-50 p-5 rounded-lg border border-gray-200 mb-6">
                            <div class="flex items-center gap-3 mb-3">
                                <svg class="w-6 h-6" style="color: rgb(0, 65, 110);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-gray-700 font-bold">Statystyka zwolnień lekarskich</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-600 mb-1">Łączna liczba dni</p>
                                    <p class="text-2xl font-bold text-[rgb(0,65,110)]">{{ simulationResult.sick_leave_impact.average_days }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 mb-1">Średnio rocznie</p>
                                    <p class="text-2xl font-bold text-[rgb(0,65,110)]">
                                        {{ (simulationResult.sick_leave_impact.average_days / (simulationResult.years_to_retirement + (parseInt(formData.age) - workStartAge))).toFixed(1) }} dni/rok
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
                                    co realnie wpływa na wysokość przyszłej emerytury. W zaawansowanej symulacji możesz wprowadzić dokładne dni zwolnień
                                    w danych historycznych i przyszłościowych dla bardziej precyzyjnej prognozy.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wykres wzrostu kapitału - WIZUALIZACJA -->
                <div v-if="simulationResult.account_growth_forecast && simulationResult.account_growth_forecast.length > 0" class="bg-white border border-gray-200 shadow-sm">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 flex items-center justify-center rounded-lg" style="background-color: rgb(63, 132, 210);">
                                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-2xl font-bold" style="color: rgb(0, 65, 110);">Wzrost Twojego kapitału emerytalnego</div>
                                <div class="text-sm font-normal text-gray-600 mt-1">Prognoza rok po roku do emerytury</div>
                            </div>
                        </div>
                    </div>
                    <div class="p-8">
                        <!-- Wykres liniowy z obszarem -->
                        <div class="mb-8">
                            <h4 class="text-lg font-bold mb-4 flex items-center gap-2" style="color: rgb(0, 65, 110);">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                </svg>
                                Wykres wzrostu kapitału w czasie
                            </h4>
                            <div class="bg-gray-50 p-8 rounded-lg border border-gray-200">
                                <!-- Obszar wykresu -->
                                <div class="relative" style="height: 500px;">
                                    <!-- Linie siatki poziomej i wartości -->
                                    <div class="absolute inset-0">
                                        <div v-for="i in 5" :key="i" class="absolute w-full border-t border-gray-200" :style="{ bottom: ((i - 1) * 25) + '%' }">
                                            <span class="absolute -left-2 -translate-x-full -translate-y-1/2 text-sm font-semibold text-gray-600">
                                                {{ formatCurrency(Math.max(...simulationResult.account_growth_forecast.map(item => item.total_balance)) * (i - 1) / 4) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- SVG dla linii i obszarów -->
                                    <svg class="absolute inset-0 w-full h-full" style="overflow: visible;">
                                        <defs>
                                            <!-- Gradient dla obszaru całkowitego -->
                                            <linearGradient id="areaGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                                <stop offset="0%" style="stop-color:rgb(0,153,63);stop-opacity:0.3" />
                                                <stop offset="100%" style="stop-color:rgb(0,153,63);stop-opacity:0.05" />
                                            </linearGradient>
                                            <!-- Gradient dla subkonta -->
                                            <linearGradient id="subaccountGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                                <stop offset="0%" style="stop-color:rgb(63,132,210);stop-opacity:0.3" />
                                                <stop offset="100%" style="stop-color:rgb(63,132,210);stop-opacity:0.05" />
                                            </linearGradient>
                                        </defs>

                                        <!-- Obszar dla kapitału łącznego -->
                                        <path
                                            :d="generateAreaPath(simulationResult.account_growth_forecast, 'total_balance')"
                                            fill="url(#areaGradient)"
                                            stroke="none"
                                        />

                                        <!-- Linia dla kapitału łącznego -->
                                        <path
                                            :d="generateLinePath(simulationResult.account_growth_forecast, 'total_balance')"
                                            fill="none"
                                            stroke="rgb(0,153,63)"
                                            stroke-width="3"
                                            class="drop-shadow-lg"
                                        />

                                        <!-- Punkty na linii kapitału łącznego -->
                                        <g v-for="(item, index) in simulationResult.account_growth_forecast" :key="'point-' + item.year">
                                            <circle
                                                :cx="(index / (simulationResult.account_growth_forecast.length - 1)) * 100 + '%'"
                                                :cy="(100 - (item.total_balance / Math.max(...simulationResult.account_growth_forecast.map(i => i.total_balance)) * 100)) + '%'"
                                                r="5"
                                                fill="rgb(0,153,63)"
                                                stroke="white"
                                                stroke-width="2"
                                                class="cursor-pointer hover:r-8 transition-all"
                                                @mouseenter="hoveredPoint = index"
                                                @mouseleave="hoveredPoint = null"
                                            />

                                            <!-- Tooltip na wykresie -->
                                            <g v-if="hoveredPoint === index">
                                                <foreignObject
                                                    :x="(index / (simulationResult.account_growth_forecast.length - 1)) * 100 + '%'"
                                                    :y="Math.max(0, (100 - (item.total_balance / Math.max(...simulationResult.account_growth_forecast.map(i => i.total_balance)) * 100)) - 15) + '%'"
                                                    width="200"
                                                    height="120"
                                                    class="overflow-visible"
                                                >
                                                    <div class="bg-gray-900 text-white text-xs rounded-lg py-3 px-4 shadow-2xl -translate-x-1/2 -translate-y-full mb-2">
                                                        <div class="font-bold text-base mb-2">{{ item.year }} ({{ item.age }} lat)</div>
                                                        <div class="space-y-1">
                                                            <div class="flex justify-between gap-4">
                                                                <span class="text-gray-300">Kapitał:</span>
                                                                <span class="font-bold">{{ formatCurrency(item.total_balance) }}</span>
                                                            </div>
                                                            <div class="flex justify-between gap-4" style="color: rgb(0, 153, 63);">
                                                                <span>Konto:</span>
                                                                <span class="font-semibold">{{ formatCurrency(item.account_balance) }}</span>
                                                            </div>
                                                            <div class="flex justify-between gap-4" style="color: rgb(63, 132, 210);">
                                                                <span>Subkonto:</span>
                                                                <span class="font-semibold">{{ formatCurrency(item.subaccount_balance) }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </foreignObject>
                                            </g>
                                        </g>
                                    </svg>

                                    <!-- Etykiety osi X (lata) -->
                                    <div class="absolute bottom-0 left-0 right-0 flex justify-between px-12 translate-y-8">
                                        <div
                                            v-for="(item, index) in simulationResult.account_growth_forecast.filter((_, i) => i % Math.max(1, Math.floor(simulationResult.account_growth_forecast.length / 10)) === 0 || i === simulationResult.account_growth_forecast.length - 1)"
                                            :key="'label-' + item.year"
                                            class="text-sm font-bold"
                                            style="color: rgb(0, 65, 110);"
                                        >
                                            <div>{{ item.year }}</div>
                                            <div class="text-xs text-gray-500 font-normal">{{ item.age }} lat</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Legenda rozszerzona -->
                                <div class="mt-12 pt-6 border-t-2 border-gray-300">
                                    <div class="grid grid-cols-3 gap-6">
                                        <div class="bg-white p-4 rounded-lg border-2 shadow-sm" style="border-color: rgb(0, 153, 63);">
                                            <div class="flex items-center gap-2 mb-2">
                                                <div class="w-4 h-4 bg-[rgb(0,153,63)] rounded-full"></div>
                                                <span class="text-sm font-bold" style="color: rgb(0, 65, 110);">Kapitał łączny</span>
                                            </div>
                                            <p class="text-xs text-gray-600">Suma konta głównego i subkonta</p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border-2 shadow-sm" style="border-color: rgba(0, 153, 63, 0.5);">
                                            <div class="flex items-center gap-2 mb-2">
                                                <div class="w-4 h-4 bg-[rgb(0,153,63)]/70 rounded"></div>
                                                <span class="text-sm font-bold" style="color: rgb(0, 65, 110);">Konto główne (77.78%)</span>
                                            </div>
                                            <p class="text-xs text-gray-600">Główna część składki emerytalnej</p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border-2 shadow-sm" style="border-color: rgb(63, 132, 210);">
                                            <div class="flex items-center gap-2 mb-2">
                                                <div class="w-4 h-4 bg-[rgb(63,132,210)] rounded"></div>
                                                <span class="text-sm font-bold" style="color: rgb(0, 65, 110);">Subkonto (22.22%)</span>
                                            </div>
                                            <p class="text-xs text-gray-600">Część składki do wypłaty jednorazowej</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Szczegółowa tabela -->
                        <div>
                            <h4 class="text-lg font-bold mb-4 flex items-center gap-2" style="color: rgb(0, 65, 110);">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1h4a1 1 0 001-1v-1h-5v2zm0-4h5V8h-5v2zM9 8H4v2h5V8z" clip-rule="evenodd" />
                                </svg>
                                Szczegółowa tabela wzrostu
                            </h4>
                            <div class="overflow-x-auto rounded-lg border-2 border-gray-200">
                                <table class="w-full">
                                    <thead class="text-white" style="background-color: rgb(63, 132, 210);">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-bold">ROK</th>
                                            <th class="px-4 py-3 text-left font-bold">WIEK</th>
                                            <th class="px-4 py-3 text-right font-bold">KONTO GŁÓWNE</th>
                                            <th class="px-4 py-3 text-right font-bold">SUBKONTO</th>
                                            <th class="px-4 py-3 text-right font-bold">ROCZNA SKŁADKA</th>
                                            <th class="px-4 py-3 text-right font-bold">KAPITAŁ ŁĄCZNY</th>
                                            <th class="px-4 py-3 text-right font-bold">WZROST</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(item, index) in simulationResult.account_growth_forecast"
                                            :key="item.year"
                                            :class="[
                                                'border-b border-gray-200 hover:bg-[rgb(63,132,210)]/5 transition-colors',
                                                index % 2 === 0 ? 'bg-white' : 'bg-gray-50'
                                            ]"
                                        >
                                            <td class="px-4 py-3 font-bold" style="color: rgb(0, 65, 110);">{{ item.year }}</td>
                                            <td class="px-4 py-3 text-gray-700">{{ item.age }} lat</td>
                                            <td class="px-4 py-3 text-right font-semibold" style="color: rgb(0, 153, 63);">
                                                {{ formatCurrency(item.account_balance) }}
                                            </td>
                                            <td class="px-4 py-3 text-right font-semibold" style="color: rgb(63, 132, 210);">
                                                {{ formatCurrency(item.subaccount_balance) }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-700">
                                                {{ formatCurrency(item.annual_contribution) }}
                                            </td>
                                            <td class="px-4 py-3 text-right font-bold text-lg" style="color: rgb(0, 65, 110);">
                                                {{ formatCurrency(item.total_balance) }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <span v-if="index > 0" class="inline-flex items-center gap-1 font-semibold" style="color: rgb(0, 153, 63);">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                    {{ formatCurrency(item.total_balance - simulationResult.account_growth_forecast[index - 1].total_balance) }}
                                                </span>
                                                <span v-else class="text-gray-400 text-sm">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="text-white font-bold" style="background-color: rgb(0, 153, 63);">
                                        <tr>
                                            <td colspan="5" class="px-4 py-4 text-right text-lg">KAPITAŁ KOŃCOWY:</td>
                                            <td class="px-4 py-4 text-right text-2xl">
                                                {{ formatCurrency(simulationResult.account_growth_forecast[simulationResult.account_growth_forecast.length - 1].total_balance) }}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Kluczowe statystyki -->
                        <div class="grid md:grid-cols-3 gap-4 mt-6">
                            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5" style="color: rgb(0, 153, 63);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-gray-600 font-medium">Średni wzrost roczny</p>
                                </div>
                                <p class="text-3xl font-bold" style="color: rgb(0, 153, 63);">
                                    {{formatCurrency((simulationResult.account_growth_forecast[simulationResult.account_growth_forecast.length - 1].total_balance - simulationResult.account_growth_forecast[0].total_balance) / simulationResult.account_growth_forecast.length) }}
                                </p>
                            </div>

                            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5" style="color: rgb(63, 132, 210);" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-gray-600 font-medium">Łączne składki</p>
                                </div>
                                <p class="text-3xl font-bold" style="color: rgb(63, 132, 210);">
                                    {{ formatCurrency(simulationResult.account_growth_forecast.reduce((sum, item) => sum + item.annual_contribution, 0)) }}
                                </p>
                            </div>

                            <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5" style="color: rgb(255, 179, 79);" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11 4a1 1 0 10-2 0v4a1 1 0 102 0V7z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm text-gray-600 font-medium">Wzrost % całkowity</p>
                                </div>
                                <p class="text-3xl font-bold text-[rgb(255,179,79)]">
                                    {{ ((simulationResult.account_growth_forecast[simulationResult.account_growth_forecast.length - 1].total_balance / simulationResult.account_growth_forecast[0].total_balance - 1) * 100).toFixed(1) }}%
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Raport PDF -->
                <div class="bg-white border border-gray-200 shadow-sm">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 flex items-center justify-center rounded-lg" style="background-color: rgb(255, 179, 79);">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-2xl font-bold" style="color: rgb(0, 65, 110);">Pobierz raport PDF</div>
                                <div class="text-sm font-normal text-gray-600 mt-1">Szczegółowy raport z Twojej prognozy emerytalnej</div>
                            </div>
                        </div>
                    </div>
                    <div class="p-8">
                        <div class="space-y-6">
                            <!-- Informacja o zawartości raportu -->
                            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                                <h4 class="text-lg font-bold mb-4" style="color: rgb(0, 65, 110);">📄 Raport będzie zawierał:</h4>
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" style="color: rgb(0, 153, 63);">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold" style="color: rgb(0, 65, 110);">Twój profil emerytalny</p>
                                            <p class="text-sm text-gray-600">Wszystkie dane wejściowe</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" style="color: rgb(0, 153, 63);">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold" style="color: rgb(0, 65, 110);">Wyniki prognozy</p>
                                            <p class="text-sm text-gray-600">Emerytura rzeczywista i urealniona</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" style="color: rgb(0, 153, 63);">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold" style="color: rgb(0, 65, 110);">Wykres wzrostu kapitału</p>
                                            <p class="text-sm text-gray-600">Wizualizacja rok po roku</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" style="color: rgb(0, 153, 63);">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold" style="color: rgb(0, 65, 110);">Porównania ze średnią</p>
                                            <p class="text-sm text-gray-600">Kontekst krajowy</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" style="color: rgb(0, 153, 63);">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold" style="color: rgb(0, 65, 110);">Scenariusze odroczenia</p>
                                            <p class="text-sm text-gray-600">Co jeśli pracujesz dłużej</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" style="color: rgb(0, 153, 63);">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <p class="font-semibold" style="color: rgb(0, 65, 110);">Komentarze edukacyjne</p>
                                            <p class="text-sm text-gray-600">Wyjaśnienia i rekomendacje</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Przycisk pobierania -->
                            <button
                                @click="generatePDF"
                                :disabled="isGeneratingPDF"
                                class="w-full px-8 py-4 text-lg lg:text-xl font-semibold text-white transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                                style="background-color: rgb(255, 179, 79);"
                            >
                                <svg v-if="isGeneratingPDF" class="animate-spin -ml-1 mr-3 h-6 w-6 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span v-if="isGeneratingPDF">Generuję raport PDF...</span>
                                <span v-else>Pobierz szczegółowy raport PDF</span>
                            </button>

                            <p class="text-center text-sm text-gray-600">
                                Raport zostanie wygenerowany w formacie PDF i automatycznie pobrany na Twoje urządzenie
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Przyciski akcji -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <Button
                        @click="resetForm"
                        class="flex-1 h-14 text-lg font-semibold bg-white border-2 transition-all duration-300 shadow-lg"
                        style="color: rgb(0, 65, 110); border-color: rgb(0, 65, 110);"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Nowa prognoza
                    </Button>
                    <Button
                        v-if="sessionUuid"
                        @click="copyShareLink"
                        class="flex-1 h-14 text-lg font-semibold text-white transition-all duration-300 shadow-lg"
                        style="background-color: rgb(0, 153, 63);"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z" />
                        </svg>
                        Udostępnij symulację
                    </Button>
                    <Link
                        :href="home()"
                        class="flex-1 h-14 text-lg font-semibold text-white rounded-md flex items-center justify-center transition-all duration-300 shadow-lg hover:shadow-xl"
                        style="background-color: rgb(255, 179, 79);"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Strona główna
                    </Link>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-16 pb-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white border border-gray-200 rounded-lg p-6 text-center">
                    <p class="text-gray-600 text-sm">
                        © 2025 Zakład Ubezpieczeń Społecznych
                    </p>
                    <p class="text-gray-500 text-xs mt-2">
                        Dashboard zaawansowanego prognozowania emerytur
                    </p>
                </div>
            </div>
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

/* Poprawa widoczności tekstu w polach input */
:deep(input) {
    color: rgb(0, 65, 110) !important;
    font-weight: 600 !important;
    background-color: white !important;
}

:deep(input::placeholder) {
    color: rgb(107, 114, 128) !important;
    opacity: 0.6 !important;
    font-weight: 400 !important;
}

:deep(input:focus) {
    color: rgb(0, 65, 110) !important;
    background-color: white !important;
}

/* Ciemniejszy tekst dla lepszej czytelności */
:deep(input:not(:placeholder-shown)) {
    color: rgb(0, 65, 110) !important;
    font-weight: 700 !important;
}

/* Zwiększenie kontrastu dla wartości */
:deep(input[type="number"]),
:deep(input[type="text"]) {
    background-color: white !important;
    border-width: 2px !important;
}

:deep(input:hover) {
    border-color: rgb(63, 132, 210) !important;
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

.animate-slideUp {
    animation: slideUp 0.5s ease-out;
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Style dla scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgb(190, 195, 206, 0.1);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: rgb(63, 132, 210, 0.5);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgb(63, 132, 210);
}

/* Wysokie kontrasty dla WCAG 2.0 */
:deep(input:focus),
:deep(button:focus) {
    outline: 3px solid rgba(0, 153, 63, 0.5);
    outline-offset: 2px;
}

/* Lepsze dostosowanie dla urządzeń mobilnych i osób starszych */
@media (max-width: 768px) {
    :deep(input),
    :deep(button) {
        min-height: 56px;
        font-size: 1.125rem;
    }
}

/* Wysoki kontrast dla WCAG 2.0 */
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

/* Ukryj strzałki w input type="number" */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>
