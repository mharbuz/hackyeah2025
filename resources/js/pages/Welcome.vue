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

// Obsługa formularza
const handleSubmit = async () => {
    // Nie pozwalaj na tworzenie nowej sesji gdy oglądamy udostępnioną sesję
    if (isSharedSession.value) {
        console.warn('Nie można utworzyć nowej sesji podczas oglądania udostępnionej sesji');
        return;
    }
    
    const value = parseFloat(inputValue.value);
    if (value && value > 0) {
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
        } catch (error) {
            console.error('Błąd podczas tworzenia sesji:', error);
        } finally {
            isCreatingSession.value = false;
        }
        
        desiredPension.value = value;
        showResults.value = true;
        // Pobierz nową ciekawostkę przy każdym sprawdzeniu
        await fetchRandomFact();
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
    <div
        class="min-h-screen bg-gradient-to-br from-[rgb(0,65,110)] via-[rgb(63,132,210)] to-[rgb(0,153,63)] p-4 lg:p-8"
    >
        <header
            class="mb-8 w-full max-w-7xl mx-auto"
        >
            <nav class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white rounded-lg px-4 py-2">
                        <img 
                            src="/zus-logo.svg" 
                            alt="ZUS Logo" 
                            class="h-8 w-auto"
                        />
                    </div>
                    <h1 class="text-white text-2xl font-semibold hidden lg:block">Symulator Emerytury</h1>
                </div>
                <div class="flex gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-block rounded-lg bg-white px-6 py-2.5 text-base font-medium text-[rgb(0,65,110)] hover:bg-[rgb(255,179,79)] hover:text-white transition-colors"
                    >
                        Panel
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-block rounded-lg border-2 border-white px-6 py-2.5 text-base font-medium text-white hover:bg-white hover:text-[rgb(0,65,110)] transition-colors"
                        >
                            Zaloguj się
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-block rounded-lg bg-white px-6 py-2.5 text-base font-medium text-[rgb(0,65,110)] hover:bg-[rgb(255,179,79)] hover:text-white transition-colors"
                        >
                            Zarejestruj się
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <main
            class="w-full max-w-7xl mx-auto"
        >
            <!-- Informacja o udostępnionej sesji -->
            <div v-if="isSharedSession" class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 mb-8 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2">Udostępniona sesja symulacji</h2>
                        <p class="text-white/80">
                            Utworzona: {{ formatDate(sharedSession!.created_at) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-white/60">ID sesji:</p>
                        <p class="font-mono text-sm">{{ sharedSession!.uuid }}</p>
                    </div>
                </div>
            </div>

            <!-- Sekcja wprowadzania danych (tylko dla głównego symulatora) -->
            <div
                v-if="!isSharedSession"
                class="bg-white rounded-2xl shadow-2xl p-8 lg:p-12 mb-8"
            >
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-3xl lg:text-4xl font-bold text-[rgb(0,65,110)] mb-4">
                        Jaką emeryturę chciałbyś otrzymywać w przyszłości?
                    </h2>
                    <p class="text-lg text-gray-600 mb-8">
                        Wprowadź kwotę, którą chciałbyś otrzymywać jako emeryturę. Pomożemy Ci zrozumieć, jak wypada ona na tle statystyk krajowych.
                    </p>

                    <form @submit.prevent="handleSubmit" class="flex flex-col lg:flex-row gap-4 items-center justify-center mb-6">
                        <div class="relative flex-1 max-w-md">
                            <input
                                v-model="inputValue"
                                type="number"
                                step="100"
                                min="500"
                                placeholder="np. 3500"
                                class="w-full text-2xl lg:text-3xl font-semibold px-6 py-4 border-3 border-[rgb(190,195,206)] rounded-xl focus:outline-none focus:border-[rgb(0,153,63)] transition-colors text-black"
                                required
                            />
                            <span class="absolute right-6 top-1/2 -translate-y-1/2 text-2xl lg:text-3xl font-semibold text-gray-400">zł</span>
                        </div>
                        <button
                            type="submit"
                            :disabled="isCreatingSession"
                            class="px-8 py-4 bg-[rgb(0,153,63)] text-white text-xl font-semibold rounded-xl hover:bg-[rgb(0,65,110)] transition-colors shadow-lg hover:shadow-xl transform hover:scale-105 transition-transform disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                        >
                            <span v-if="isCreatingSession" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Tworzenie sesji...
                            </span>
                            <span v-else>Sprawdź</span>
                        </button>
                    </form>

                    <div class="flex items-center justify-center gap-2 text-gray-500 mb-6">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span>Średnia emerytura w Polsce: <strong class="text-[rgb(0,153,63)]">{{ formatCurrency(averagePension) }}</strong></span>
                    </div>

                    <!-- Przycisk do symulacji przyszłej emerytury -->
                    <div class="border-t-2 border-gray-100 pt-6">
                        <Link
                            :href="getPensionSimulationUrl()"
                            class="inline-flex items-center gap-3 rounded-xl bg-gradient-to-r from-[rgb(255,179,79)] to-[rgb(255,179,79)]/80 px-8 py-4 text-xl font-bold text-white hover:from-[rgb(255,179,79)]/90 hover:to-[rgb(255,179,79)]/70 transition-all shadow-lg hover:shadow-xl transform hover:scale-105"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span>Symulacja przyszłej emerytury</span>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </Link>
                        <p class="text-sm text-gray-500 mt-3">
                            Oblicz szacunkową wysokość swojej przyszłej emerytury na podstawie wieku, wynagrodzenia i planów zawodowych
                        </p>
                    </div>
                </div>
            </div>

            <!-- Wyniki -->
            <div
                v-if="showResults && desiredPension"
                class="space-y-8"
            >
                <!-- Porównanie z średnią -->
                <div class="bg-white rounded-2xl shadow-2xl p-8 lg:p-12">
                    <h3 class="text-2xl lg:text-3xl font-bold text-[rgb(0,65,110)] mb-6 text-center">
                        Twoja emerytura w porównaniu do średniej
                    </h3>

                    <div class="grid lg:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gradient-to-br from-[rgb(63,132,210)] to-[rgb(0,153,63)] rounded-xl p-6 text-white text-center">
                            <div class="text-sm font-medium mb-2 opacity-90">Twoja docelowa emerytura</div>
                            <div class="text-4xl font-bold mb-1">{{ formatCurrency(desiredPension) }}</div>
                        </div>

                        <div class="bg-gradient-to-br from-[rgb(0,153,63)] to-[rgb(0,65,110)] rounded-xl p-6 text-white text-center">
                            <div class="text-sm font-medium mb-2 opacity-90">Średnia krajowa</div>
                            <div class="text-4xl font-bold mb-1">{{ formatCurrency(averagePension) }}</div>
                        </div>

                        <div :class="[
                            'rounded-xl p-6 text-white text-center',
                            parseFloat(percentageDifference) >= 0
                                ? 'bg-gradient-to-br from-[rgb(0,153,63)] to-[rgb(255,179,79)]'
                                : 'bg-gradient-to-br from-[rgb(240,94,94)] to-[rgb(190,195,206)]'
                        ]">
                            <div class="text-sm font-medium mb-2 opacity-90">Różnica</div>
                            <div class="text-4xl font-bold mb-1">
                                {{ parseFloat(percentageDifference) >= 0 ? '+' : '' }}{{ percentageDifference }}%
                            </div>
                            <div class="text-sm opacity-90">
                                {{ parseFloat(percentageDifference) >= 0 ? 'powyżej' : 'poniżej' }} średniej
                            </div>
                        </div>
                    </div>

                    <div v-if="userGroup" class="bg-gradient-to-r from-[rgb(190,195,206)]/20 to-transparent rounded-xl p-6 border-l-4" :style="{ borderColor: userGroup.color }">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-3 h-3 rounded-full mt-1.5" :style="{ backgroundColor: userGroup.color }"></div>
                            <div>
                                <h4 class="text-xl font-bold text-[rgb(0,65,110)] mb-2">Należysz do grupy: {{ userGroup.name }}</h4>
                                <p class="text-gray-700 text-base leading-relaxed">{{ userGroup.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wykres grup emerytalnych -->
                <div class="bg-white rounded-2xl shadow-2xl p-8 lg:p-12">
                    <h3 class="text-2xl lg:text-3xl font-bold text-[rgb(0,65,110)] mb-3 text-center">
                        Rozkład emerytur w Polsce
                    </h3>
                    <p class="text-center text-gray-600 mb-8 text-lg">
                        Najedź na grupę, aby zobaczyć szczegóły
                    </p>

                    <div class="space-y-4 mb-8">
                        <div
                            v-for="(group, index) in currentPensionGroups"
                            :key="index"
                            @mouseenter="hoveredGroup = index"
                            @mouseleave="hoveredGroup = null"
                            class="relative cursor-pointer transition-all duration-300 transform hover:scale-105"
                        >
                            <div class="flex items-center gap-4 mb-2">
                                <div class="flex-1">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-bold text-lg text-[rgb(0,65,110)]">{{ group.name }}</span>
                                        <div class="flex items-center gap-3">
                                            <span class="text-base font-semibold text-gray-700">{{ group.percentage }}%</span>
                                            <span class="text-base font-bold" :style="{ color: group.color }">{{ formatCurrency(group.amount) }}</span>
                                        </div>
                                    </div>
                                    <div class="h-12 bg-gray-100 rounded-full overflow-hidden shadow-inner">
                                        <div
                                            class="h-full rounded-full transition-all duration-500 flex items-center justify-end pr-4"
                                            :style="{
                                                width: group.percentage + '%',
                                                backgroundColor: group.color,
                                                opacity: hoveredGroup === index ? 1 : 0.8
                                            }"
                                        >
                                            <span v-if="group.percentage > 15" class="text-white font-bold text-sm drop-shadow-lg">
                                                {{ group.percentage }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tooltip z opisem -->
                            <div
                                v-if="hoveredGroup === index"
                                class="mt-3 p-4 bg-gradient-to-r from-[rgb(190,195,206)]/10 to-transparent rounded-xl border-l-4 animate-fadeIn"
                                :style="{ borderColor: group.color }"
                            >
                                <p class="text-gray-700 text-base leading-relaxed">{{ group.description }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Legenda -->
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 pt-6 border-t-2 border-gray-100">
                        <div
                            v-for="(group, index) in currentPensionGroups"
                            :key="'legend-' + index"
                            class="flex items-center gap-2"
                        >
                            <div class="w-4 h-4 rounded-full flex-shrink-0" :style="{ backgroundColor: group.color }"></div>
                            <span class="text-sm text-gray-600">{{ group.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Ciekawostka -->
                <div class="bg-gradient-to-br from-[rgb(255,179,79)] to-[rgb(255,179,79)]/80 rounded-2xl shadow-2xl p-8 lg:p-12 text-white">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <svg v-if="!isLoadingFact" class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            <svg v-else class="w-12 h-12 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold mb-3">Czy wiesz, że...</h3>
                            <p class="text-lg leading-relaxed">{{ currentFunFact || 'Ładowanie ciekawostki...' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Udostępnianie sesji (tylko dla głównego symulatora) -->
                <div v-if="shareUrl && !isSharedSession" class="bg-gradient-to-r from-[rgb(0,153,63)] to-[rgb(63,132,210)] rounded-2xl shadow-2xl p-8 lg:p-12 text-center text-white mb-8">
                    <h3 class="text-2xl lg:text-3xl font-bold mb-4">
                        🎉 Twoja sesja została utworzona!
                    </h3>
                    <p class="text-lg mb-6 opacity-90">
                        Udostępnij wyniki swojej symulacji z innymi osobami
                    </p>
                    <div class="bg-white/20 rounded-xl p-4 mb-6">
                        <p class="text-sm opacity-80 mb-2">Link do udostępnienia:</p>
                        <div class="flex items-center justify-center gap-2">
                            <input 
                                :value="shareUrl" 
                                readonly 
                                class="flex-1 bg-white/20 border border-white/30 rounded-lg px-3 py-2 text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/50"
                            />
                            <button
                                @click="copyShareLink"
                                class="bg-white/20 hover:bg-white/30 border border-white/30 rounded-lg px-4 py-2 transition-colors"
                                title="Kopiuj link"
                            >
                                📋
                            </button>
                        </div>
                    </div>
                    <p class="text-sm opacity-80">
                        Link będzie aktywny przez 30 dni
                    </p>
                </div>

                <!-- Podsumowanie i CTA -->
                <div class="bg-white rounded-2xl shadow-2xl p-8 lg:p-12 text-center">
                    <h3 v-if="!isSharedSession" class="text-2xl lg:text-3xl font-bold text-[rgb(0,65,110)] mb-4">
                        Chcesz osiągnąć swoją docelową emeryturę?
                    </h3>
                    <h3 v-else class="text-2xl lg:text-3xl font-bold text-[rgb(0,65,110)] mb-4">
                        Chcesz sprawdzić swoją emeryturę?
                    </h3>
                    
                    <p v-if="!isSharedSession" class="text-lg text-gray-600 mb-8 max-w-2xl mx-auto">
                        Zarejestruj się w naszym systemie, aby uzyskać spersonalizowane wskazówki dotyczące oszczędzania i planowania emerytury.
                    </p>
                    <p v-else class="text-lg text-gray-600 mb-8 max-w-2xl mx-auto">
                        Użyj naszego symulatora, aby sprawdzić, jaką emeryturę możesz otrzymać w przyszłości.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            :href="getPensionSimulationUrl()"
                            class="inline-block rounded-xl bg-[rgb(0,153,63)] px-8 py-4 text-xl font-semibold text-white hover:bg-[rgb(0,65,110)] transition-colors shadow-lg hover:shadow-xl"
                        >
                            <span v-if="!isSharedSession">Zacznij planować swoją przyszłość</span>
                            <span v-else>Sprawdź swoją emeryturę</span>
                        </Link>
                        <button
                            v-if="!isSharedSession"
                            @click="showResults = false; desiredPension = null; inputValue = ''; sessionUuid = null; shareUrl = null"
                            class="inline-block rounded-xl border-2 border-[rgb(0,65,110)] px-8 py-4 text-xl font-semibold text-[rgb(0,65,110)] hover:bg-[rgb(0,65,110)] hover:text-white transition-colors"
                        >
                            Sprawdź inną kwotę
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <footer class="mt-12 pb-8 text-center text-white">
            <p class="text-sm opacity-80">
                © 2025 Zakład Ubezpieczeń Społecznych - Symulator służy wyłącznie celom informacyjnym
            </p>
        </footer>
    </div>
</template>

<style scoped>
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
    box-shadow: 0 0 0 3px rgba(0, 153, 63, 0.3);
}

button:focus {
    outline: 3px solid rgba(255, 255, 255, 0.8);
    outline-offset: 2px;
}
</style>
