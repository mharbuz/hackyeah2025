<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import pensionSimulation from '@/routes/pension-simulation';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';

interface Props {
    sharedSession?: {
        uuid: string;
        pension_value: number;
        created_at: string;
    };
    sharedPensionData?: {
        pension_groups: Array<{
            name: string;
            amount: number;
            percentage: number;
            color: string;
            description: string;
        }>;
        user_group: {
            name: string;
            amount: number;
            percentage: number;
            color: string;
            description: string;
        };
        average_pension: number;
        percentage_difference: number;
    };
}

const props = defineProps<Props>();

// Toast notifications
const toast = useToast();

// Dane o grupach emerytalnych
const pensionGroups = [
    {
        name: 'Poniżej minimalnej',
        amount: 1200,
        percentage: 15,
        color: 'rgb(240, 94, 94)',
        description: 'Świadczeniobiorcy otrzymujący emeryturę w wysokości poniżej minimalnej wykazywali się niską aktywnością zawodową, nie przepracowali minimum 25 lat dla mężczyzn i 20 lat dla kobiet, w związku z tym nie nabyli prawa do gwarancji minimalnej emerytury.'
    },
    {
        name: 'Minimalna',
        amount: 1780,
        percentage: 25,
        color: 'rgb(190, 195, 206)',
        description: 'Osoby otrzymujące emeryturę minimalną przepracowały wymagany staż pracy, jednak ich składki były niskie z uwagi na niskie wynagrodzenia lub przerwy w karierze zawodowej.'
    },
    {
        name: 'Średnia krajowa',
        amount: 3500,
        percentage: 35,
        color: 'rgb(0, 153, 63)',
        description: 'Najliczniejsza grupa emerytów, którzy pracowali przez większość swojego życia zawodowego, ze średnimi wynagrodzeniami. Stanowią trzon systemu emerytalnego.'
    },
    {
        name: 'Powyżej średniej',
        amount: 5500,
        percentage: 20,
        color: 'rgb(63, 132, 210)',
        description: 'Osoby, które przez większość kariery otrzymywały wynagrodzenia powyżej średniej krajowej, często specjaliści lub kadra menedżerska.'
    },
    {
        name: 'Wysokie',
        amount: 8000,
        percentage: 5,
        color: 'rgb(255, 179, 79)',
        description: 'Grupa obejmująca osoby z długim stażem pracy i wysokimi zarobkami, często członkowie zarządów, właściciele firm lub wysocy specjaliści.'
    }
];

// Stan komponentu
const desiredPension = ref<number | null>(null);
const inputValue = ref('3600');
const hoveredGroup = ref<number | null>(null);
const currentFunFact = ref('');
const showResults = ref(false);
const isLoadingFact = ref(false);
const sessionUuid = ref<string | null>(null);
const shareUrl = ref<string | null>(null);
const isCreatingSession = ref(false);
const isMobileMenuOpen = ref(false);
const validationError = ref<string | null>(null);

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

// Funkcja blokująca nieprawidłowe znaki w input number
const handleKeyDown = (event: KeyboardEvent) => {
    // Blokuj: e, E, +, -
    const invalidChars = ['e', 'E', '+', '-'];
    if (invalidChars.includes(event.key)) {
        event.preventDefault();
    }
};

// Sprawdź czy to udostępniona sesja
const isSharedSession = computed(() => !!props.sharedSession);

// Inicjalizacja dla udostępnionej sesji
onMounted(() => {
    console.log('Welcome component mounted');
    console.log('isSharedSession:', isSharedSession.value);
    console.log('props.sharedSession:', props.sharedSession);
    console.log('props.sharedPensionData:', props.sharedPensionData);

    if (isSharedSession.value && props.sharedSession && props.sharedPensionData) {
        console.log('Initializing shared session...');
        desiredPension.value = props.sharedSession.pension_value;
        showResults.value = true;
        sessionUuid.value = props.sharedSession.uuid;
        // Pobierz ciekawostkę dla udostępnionej sesji
        fetchRandomFact();
    }
});

// Średnia krajowa
const averagePension = computed(() => {
    return props.sharedPensionData?.average_pension || 3500;
});

// Oblicz procentową różnicę
const percentageDifference = computed(() => {
    if (props.sharedPensionData) {
        return props.sharedPensionData.percentage_difference.toFixed(1);
    }
    if (!desiredPension.value) return 0;
    return ((desiredPension.value - averagePension.value) / averagePension.value * 100).toFixed(1);
});

// Znajdź grupę użytkownika
const userGroup = computed(() => {
    if (props.sharedPensionData) {
        return props.sharedPensionData.user_group;
    }
    if (!desiredPension.value) return null;
    return pensionGroups.find(group => desiredPension.value! <= group.amount) || pensionGroups[pensionGroups.length - 1];
});

// Użyj grup z props lub domyślnych
const currentPensionGroups = computed(() => {
    return props.sharedPensionData?.pension_groups || pensionGroups;
});

// Formatowanie daty dla udostępnionej sesji
const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('pl-PL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Formatowanie waluty
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency: 'PLN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};

// Pobierz losową ciekawostkę z backendu
const fetchRandomFact = async () => {
    console.log('Fetching random fact...');
    isLoadingFact.value = true;
    try {
        const response = await axios.get('/api/pension-fact/random');
        console.log('Fact received:', response.data.fact);
        currentFunFact.value = response.data.fact;
    } catch (error) {
        console.error('Błąd podczas pobierania ciekawostki:', error);
        currentFunFact.value = 'Nie udało się załadować ciekawostki. Spróbuj ponownie później.';
    } finally {
        isLoadingFact.value = false;
        console.log('Fact loading completed');
    }
};

// Obsługa formularza z walidacją
const handleSubmit = async () => {
    // Wyczyść poprzednie błędy
    validationError.value = null;

    // Nie pozwalaj na tworzenie nowej sesji gdy oglądamy udostępnioną sesję
    if (isSharedSession.value) {
        console.warn('Nie można utworzyć nowej sesji podczas oglądania udostępnionej sesji');
        return;
    }

    // Walidacja - sprawdź czy pole nie jest puste
    if (!inputValue.value || inputValue.value === '') {
        validationError.value = 'Proszę wprowadzić kwotę emerytury';
        return;
    }

    const value = parseFloat(String(inputValue.value));

    // Walidacja - sprawdź czy wartość jest liczbą
    if (isNaN(value)) {
        validationError.value = 'Wprowadzona wartość musi być liczbą';
        return;
    }

    // Walidacja - sprawdź minimalną wartość
    if (value < 500) {
        validationError.value = 'Kwota emerytury musi wynosić co najmniej 500 zł';
        return;
    }

    // Walidacja - sprawdź maksymalną wartość (np. 50000 zł)
    if (value > 27000) {
        validationError.value = 'Roczna kwota składek na ubezpieczenie emerytalne nie może przekroczyć trzydziestokrotności prognozowanego przeciętnego wynagrodzenia miesięcznego.';
        return;
    }

    isCreatingSession.value = true;

    try {
        // Utwórz sesję w bazie danych
        const response = await axios.post('/api/pension-simulation', {
            pension_value: value
        });

        if (response.data.success) {
            sessionUuid.value = response.data.session_uuid;
            shareUrl.value = response.data.share_url;

            // Zaktualizuj URL w przeglądarce
            window.history.pushState({}, '', shareUrl.value);
        }

        desiredPension.value = value;
        showResults.value = true;
        // Pobierz nową ciekawostkę przy każdym sprawdzeniu
        await fetchRandomFact();
    } catch (error) {
        console.error('Błąd podczas tworzenia sesji:', error);
        validationError.value = 'Wystąpił błąd podczas tworzenia sesji. Spróbuj ponownie.';
        toast.error('Wystąpił błąd podczas tworzenia sesji. Spróbuj ponownie.');
    } finally {
        isCreatingSession.value = false;
    }
};

// Funkcja do kopiowania linku
const copyShareLink = async () => {
    if (shareUrl.value) {
        try {
            await navigator.clipboard.writeText(shareUrl.value);
            toast.success('Link został skopiowany do schowka!');
        } catch (error) {
            console.error('Błąd podczas kopiowania linku:', error);
        }
    }
};

// Funkcja do generowania URL symulacji z zachowaniem parametru sesji
const getPensionSimulationUrl = () => {
    const currentSessionUuid = sessionUuid.value || (props.sharedSession?.uuid);

    if (currentSessionUuid) {
        return `/symulacja-emerytury?session=${currentSessionUuid}`;
    }

    return '/symulacja-emerytury';
};
</script>

<template>
    <Head title="Symulator Emerytury - ZUS">
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
                        <img
                            src="/logo_zus_darker_with_text.svg"
                            alt="ZUS Logo"
                            class="h-12 w-auto"
                        />
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
                            <div class=" 2xl:flex items-center">
                                <img src="/eu_pl_chromatic.jpg" alt="Unia Europejska" class="h-12 w-auto" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Header (below lg) -->
                <div class="lg:hidden flex justify-between  py-3">
                    <!-- First Row: Logo and Links -->
                    <div class="flex items-center justify-between mb-2">
                        <!-- Logo -->
                        <div class="flex items-center shrink-0">
                            <img
                                src="/logo_zus_darker_with_text.svg"
                                alt="ZUS Logo"
                                class="h-10 sm:h-12 w-auto"
                            />
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

                        <!-- Menu Icon - vertical layout -->
                        <button
                            @click="toggleMobileMenu"
                            class="flex flex-col justify-center items-center gap-0.5 px-2 py-1 rounded hover:opacity-80 transition-colors min-w-[60px]"
                            aria-label="Menu"
                        >
                            <div class="flex flex-col gap-1 mb-1">
                                <span class="block w-7 h-0.5" style="background-color: rgb(17, 120, 59);"></span>
                                <span class="block w-7 h-0.5" style="background-color: rgb(17, 120, 59);"></span>
                                <span class="block w-7 h-0.5" style="background-color: rgb(0, 153, 63);"></span>
                            </div>
                            <span class="text-xs font-semibold leading-none" style="color: rgb(17, 120, 59);">Menu</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main
            class="w-full max-w-7xl mx-auto p-4 lg:p-8"
        >
            <!-- Informacja o udostępnionej sesji -->
            <div v-if="isSharedSession" class="border-2 p-6 mb-8" style="border-color: rgb(63, 132, 210); background-color: rgb(63, 132, 210, 0.05);">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 flex items-center justify-center" style="background-color: rgb(63, 132, 210);">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h2 class="text-xl lg:text-2xl font-bold mb-2" style="color: rgb(0, 65, 110);">Udostępniona sesja symulacji</h2>
                            <p class="text-sm lg:text-base text-gray-600">
                                Utworzona: {{ formatDate(sharedSession!.created_at) }}
                            </p>
                        </div>
                    </div>
                    <div class="text-left md:text-right">
                        <p class="text-xs lg:text-sm font-semibold text-gray-500 uppercase tracking-wide">ID sesji:</p>
                        <p class="font-mono text-sm text-gray-700 mt-1">{{ sharedSession!.uuid }}</p>
                    </div>
                </div>
            </div>

            <!-- Sekcja wprowadzania danych (tylko dla głównego symulatora) -->
            <div
                v-if="!isSharedSession"
                class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12 mb-8"
            >
                <div class="max-w-4xl mx-auto">
                    <!-- Nagłówek sekcji -->
                    <div class="text-center mb-8">
                        <h2 class="text-3xl lg:text-4xl font-bold mb-4" style="color: rgb(0, 65, 110);">
                        Jaką emeryturę chciałbyś otrzymywać w przyszłości?
                    </h2>
                        <p class="text-base lg:text-lg text-gray-700 max-w-2xl mx-auto">
                        Wprowadź kwotę, którą chciałbyś otrzymywać jako emeryturę. Pomożemy Ci zrozumieć, jak wypada ona na tle statystyk krajowych.
                    </p>
                    </div>

                    <!-- Formularz z inputem i przyciskiem -->
                    <form @submit.prevent="handleSubmit" class="mb-6">
                        <div class="flex flex-col lg:flex-row gap-4 items-stretch justify-center max-w-3xl mx-auto">
                            <div class="relative flex-1">
                                <input
                                    v-model="inputValue"
                                    type="number"
                                    placeholder="np. 3500"
                                    :class="[
                                        'w-full text-2xl lg:text-3xl font-semibold px-6 py-4 border-2 focus:outline-none transition-colors text-gray-900',
                                        validationError ? 'border-red-500' : ''
                                    ]"
                                    :style="validationError ? '' : 'border-color: rgb(190, 195, 206);'"
                                    @input="validationError = null"
                                    @keydown="handleKeyDown"
                                />
                                <span class="absolute right-6 top-1/2 -translate-y-1/2 text-2xl lg:text-3xl font-semibold text-gray-400">zł</span>
                            </div>
                        <button
                            type="submit"
                            :disabled="isCreatingSession"
                                class="px-8 py-4 text-lg lg:text-xl font-semibold text-white transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                                style="background-color: rgb(0, 153, 63);"
                        >
                                <span v-if="isCreatingSession" class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Tworzenie sesji...
                            </span>
                            <span v-else>Sprawdź</span>
                        </button>
                        </div>
                    </form>

                    <!-- Komunikat błędu walidacji -->
                    <div v-if="validationError" class="max-w-3xl mx-auto mb-8">
                        <div class="flex items-center gap-2 p-4 border-2 border-red-500 bg-red-50 text-red-700">
                            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-medium">{{ validationError }}</span>
                        </div>
                    </div>



                </div>
            </div>

            <!-- Wyniki -->
            <div
                v-if="showResults && desiredPension"
                class="space-y-6"
            >
                <!-- Porównanie z średnią -->
                <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                    <h3 class="text-2xl lg:text-3xl font-bold mb-8 text-center" style="color: rgb(0, 65, 110);">
                        Twoja emerytura w porównaniu do średniej
                    </h3>

                    <div class="grid lg:grid-cols-3 gap-4 mb-8">
                        <div class="border-2 p-6 text-center" style="border-color: rgb(63, 132, 210); background-color: rgb(63, 132, 210, 0.05);">
                            <div class="text-sm font-semibold mb-3 uppercase tracking-wide" style="color: rgb(63, 132, 210);">Twoja docelowa emerytura</div>
                            <div class="text-3xl lg:text-4xl font-bold" style="color: rgb(0, 65, 110);">{{ formatCurrency(desiredPension) }}</div>
                        </div>

                        <div class="border-2 p-6 text-center" style="border-color: rgb(0, 153, 63); background-color: rgb(0, 153, 63, 0.05);">
                            <div class="text-sm font-semibold mb-3 uppercase tracking-wide" style="color: rgb(0, 153, 63);">Średnia krajowa</div>
                            <div class="text-3xl lg:text-4xl font-bold" style="color: rgb(0, 65, 110);">{{ formatCurrency(averagePension) }}</div>
                        </div>

                        <div
                            class="border-2 p-6 text-center"
                            :style="{
                                borderColor: parseFloat(percentageDifference) >= 0 ? 'rgb(0, 153, 63)' : 'rgb(240, 94, 94)',
                                backgroundColor: parseFloat(percentageDifference) >= 0 ? 'rgb(0, 153, 63, 0.05)' : 'rgb(240, 94, 94, 0.05)'
                            }"
                        >
                            <div class="text-sm font-semibold mb-3 uppercase tracking-wide" :style="{ color: parseFloat(percentageDifference) >= 0 ? 'rgb(0, 153, 63)' : 'rgb(240, 94, 94)' }">
                                Różnica
                            </div>
                            <div class="text-3xl lg:text-4xl font-bold mb-1" style="color: rgb(0, 65, 110);">
                                {{ parseFloat(percentageDifference) >= 0 ? '+' : '' }}{{ percentageDifference }}%
                            </div>
                            <div class="text-sm font-medium" :style="{ color: parseFloat(percentageDifference) >= 0 ? 'rgb(0, 153, 63)' : 'rgb(240, 94, 94)' }">
                                {{ parseFloat(percentageDifference) >= 0 ? 'powyżej' : 'poniżej' }} średniej
                            </div>
                        </div>
                    </div>

                    <div v-if="userGroup" class="border-l-4 p-6" style="background-color: rgb(190, 195, 206, 0.1);" :style="{ borderLeftColor: userGroup.color }">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 flex items-center justify-center" :style="{ backgroundColor: userGroup.color }">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-bold mb-3" style="color: rgb(0, 65, 110);">Należysz do grupy: {{ userGroup.name }}</h4>
                                <p class="text-gray-700 text-base leading-relaxed">{{ userGroup.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wykres grup emerytalnych -->
                <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12">
                    <h3 class="text-2xl lg:text-3xl font-bold mb-3 text-center" style="color: rgb(0, 65, 110);">
                        Rozkład emerytur w Polsce
                    </h3>
                    <p class="text-center text-gray-600 mb-8 text-base">
                        Twój przedział jest podświetlony kolorem
                    </p>

                    <div class="space-y-3 mb-8">
                        <div
                            v-for="(group, index) in currentPensionGroups"
                            :key="index"
                            class="relative transition-all duration-200 border-2 p-4"
                            :style="{
                                borderColor: userGroup && userGroup.name === group.name ? group.color : 'rgb(209, 213, 219)',
                                backgroundColor: userGroup && userGroup.name === group.name ? `${group.color}20` : 'rgb(249, 250, 251)'
                            }"
                        >
                            <div class="flex items-center gap-4 mb-2">
                                <div class="flex-1">
                                    <div class="flex justify-between items-center mb-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-6 h-6 flex-shrink-0"
                                                :style="{
                                                    backgroundColor: userGroup && userGroup.name === group.name ? group.color : 'rgb(156, 163, 175)'
                                                }"
                                            ></div>
                                            <span class="font-bold text-lg" :style="{ color: userGroup && userGroup.name === group.name ? 'rgb(0, 65, 110)' : 'rgb(75, 85, 99)' }">
                                                {{ group.name }}
                                                <span v-if="userGroup && userGroup.name === group.name" class="ml-2 text-base font-bold" :style="{ color: group.color }">
                                                    ← Twój przedział
                                                </span>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <span class="text-base font-semibold" :style="{ color: userGroup && userGroup.name === group.name ? 'rgb(55, 65, 81)' : 'rgb(107, 114, 128)' }">
                                                {{ group.percentage }}%
                                            </span>
                                            <span class="text-base font-bold" :style="{ color: userGroup && userGroup.name === group.name ? group.color : 'rgb(107, 114, 128)' }">
                                                {{ formatCurrency(group.amount) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="h-10 overflow-hidden" :style="{ backgroundColor: userGroup && userGroup.name === group.name ? 'rgb(243, 244, 246)' : 'rgb(229, 231, 235)' }">
                                        <div
                                            class="h-full transition-all duration-500 flex items-center justify-end pr-4"
                                            :style="{
                                                width: group.percentage + '%',
                                                backgroundColor: userGroup && userGroup.name === group.name ? group.color : 'rgb(156, 163, 175)'
                                            }"
                                        >
                                            <span v-if="group.percentage > 15" class="text-white font-bold text-sm">
                                                {{ group.percentage }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Opis grupy - zawsze widoczny -->
                            <div class="mt-4 pt-4 border-t-2" :style="{ borderColor: userGroup && userGroup.name === group.name ? group.color : 'rgb(209, 213, 219)' }">
                                <p class="text-base leading-relaxed" :style="{
                                    color: userGroup && userGroup.name === group.name ? 'rgb(55, 65, 81)' : 'rgb(107, 114, 128)',
                                    fontWeight: userGroup && userGroup.name === group.name ? '500' : '400'
                                }">
                                    {{ group.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ciekawostka -->
                <div class="border-2 p-8 lg:p-12" style="border-color: rgb(255, 179, 79); background-color: rgb(255, 179, 79, 0.05);">
                    <div class="flex items-start gap-4 lg:gap-6">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 lg:w-16 lg:h-16 flex items-center justify-center" style="background-color: rgb(255, 179, 79);">
                                <svg v-if="!isLoadingFact" class="w-6 h-6 lg:w-8 lg:h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <svg v-else class="w-6 h-6 lg:w-8 lg:h-8 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl lg:text-2xl font-bold mb-3" style="color: rgb(0, 65, 110);">Czy wiesz, że...</h3>
                            <p class="text-base lg:text-lg text-gray-700 leading-relaxed">{{ currentFunFact || 'Ładowanie ciekawostki...' }}</p>
                        </div>
                    </div>
                </div>


                <!-- Podsumowanie i CTA -->
                <div class="bg-white border border-gray-200 shadow-sm p-8 lg:p-12 text-center">
                    <h3 v-if="!isSharedSession" class="text-2xl lg:text-3xl font-bold mb-4" style="color: rgb(0, 65, 110);">
                        Chcesz dokładnie obliczyć swoją przyszłą emeryturę?
                    </h3>
                    <h3 v-else class="text-2xl lg:text-3xl font-bold mb-4" style="color: rgb(0, 65, 110);">
                        Chcesz sprawdzić swoją emeryturę?
                    </h3>

                    <p v-if="!isSharedSession" class="text-base lg:text-lg text-gray-700 mb-8 max-w-2xl mx-auto">
                        Skorzystaj z naszego szczegółowego kalkulatora, aby obliczyć szacunkową wysokość swojej emerytury na podstawie wieku, wynagrodzenia i planów zawodowych.
                    </p>
                    <p v-else class="text-base lg:text-lg text-gray-700 mb-8 max-w-2xl mx-auto">
                        Użyj naszego szczegółowego kalkulatora, aby obliczyć szacunkową wysokość swojej emerytury na podstawie wieku, wynagrodzenia i planów zawodowych.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            :href="getPensionSimulationUrl()"
                            class="inline-block px-8 py-4 text-lg lg:text-xl font-semibold text-white transition-colors hover:opacity-90"
                            style="background-color: rgb(0, 153, 63);"
                        >
                            <span v-if="!isSharedSession">Oblicz swoją emeryturę</span>
                            <span v-else>Oblicz swoją emeryturę</span>
                        </Link>

                    </div>
                </div>
            </div>
        </main>

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

/* Menu hover effect */
nav a:hover {
  background-color: white;
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

/* Menu główne - responsywność */
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.no-scrollbar::-webkit-scrollbar {
  display: none;
}

nav .overflow-x-auto {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

/* Ukryj scrollbar ale pozwól na scroll */
nav .flex {
  scrollbar-width: none;
}

nav .flex::-webkit-scrollbar {
  display: none;
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

/* Mobile menu - responsywność */
@media (max-width: 768px) {
  /* Zmniejsz odstępy na bardzo małych ekranach */
  .lg\:hidden .flex.items-center.gap-4 {
    gap: 0.75rem;
  }
}

@media (max-width: 480px) {
  /* Mniejsze gap na bardzo małych ekranach */
  .lg\:hidden .flex.items-center.gap-4 {
    gap: 0.5rem;
  }

  /* Zmniejsz rozmiar logo */
  .lg\:hidden img[alt="ZUS Logo"] {
    height: 2rem;
  }

  /* Zmniejsz rozmiar flag EU na bardzo małych ekranach */
  .lg\:hidden img[alt="Unia Europejska"] {
    height: 2rem;
  }
}

/* Bottom tabs responsywność */
@media (max-width: 768px) {
  .flex.flex-wrap.-mt-1 a {
    flex-basis: calc(50% - 0.5rem);
    font-size: 0.875rem;
    padding: 0.75rem 0.5rem;
  }
}

@media (max-width: 480px) {
  .flex.flex-wrap.-mt-1 a {
    flex-basis: 100%;
    font-size: 0.875rem;
    padding: 0.75rem 1rem;
  }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeIn {
    animation: fadeIn 0.5s ease-out forwards;
}

/* Lepsze dostosowanie dla osób starszych - większe elementy */
@media (max-width: 768px) {
    input[type="number"] {
        font-size: 1.5rem;
    }

    button {
        min-height: 56px;
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
