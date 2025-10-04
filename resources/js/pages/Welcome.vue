<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

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
const inputValue = ref('');
const hoveredGroup = ref<number | null>(null);
const currentFunFact = ref('');
const showResults = ref(false);
const isLoadingFact = ref(false);

// Średnia krajowa
const averagePension = 3500;

// Oblicz procentową różnicę
const percentageDifference = computed(() => {
    if (!desiredPension.value) return 0;
    return ((desiredPension.value - averagePension) / averagePension * 100).toFixed(1);
});

// Znajdź grupę użytkownika
const userGroup = computed(() => {
    if (!desiredPension.value) return null;
    return pensionGroups.find(group => desiredPension.value! <= group.amount) || pensionGroups[pensionGroups.length - 1];
});

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
    isLoadingFact.value = true;
    try {
        const response = await axios.get('/api/pension-fact/random');
        currentFunFact.value = response.data.fact;
    } catch (error) {
        console.error('Błąd podczas pobierania ciekawostki:', error);
        currentFunFact.value = 'Nie udało się załadować ciekawostki. Spróbuj ponownie później.';
    } finally {
        isLoadingFact.value = false;
    }
};

// Obsługa formularza
const handleSubmit = async () => {
    const value = parseFloat(inputValue.value);
    if (value && value > 0) {
        desiredPension.value = value;
        showResults.value = true;
        // Pobierz nową ciekawostkę przy każdym sprawdzeniu
        await fetchRandomFact();
    }
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
                    <div class="bg-white rounded-lg px-4 py-2 font-bold text-[rgb(0,65,110)] text-xl">
                        ZUS
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
            <!-- Sekcja wprowadzania danych -->
            <div
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
                                class="w-full text-2xl lg:text-3xl font-semibold px-6 py-4 border-3 border-[rgb(190,195,206)] rounded-xl focus:outline-none focus:border-[rgb(0,153,63)] transition-colors"
                                required
                            />
                            <span class="absolute right-6 top-1/2 -translate-y-1/2 text-2xl lg:text-3xl font-semibold text-gray-400">zł</span>
                        </div>
                        <button
                            type="submit"
                            class="px-8 py-4 bg-[rgb(0,153,63)] text-white text-xl font-semibold rounded-xl hover:bg-[rgb(0,65,110)] transition-colors shadow-lg hover:shadow-xl transform hover:scale-105 transition-transform"
                        >
                            Sprawdź
                        </button>
                    </form>

                    <div class="flex items-center justify-center gap-2 text-gray-500">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span>Średnia emerytura w Polsce: <strong class="text-[rgb(0,153,63)]">{{ formatCurrency(averagePension) }}</strong></span>
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
                            v-for="(group, index) in pensionGroups"
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
                            v-for="(group, index) in pensionGroups"
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

                <!-- Podsumowanie i CTA -->
                <div class="bg-white rounded-2xl shadow-2xl p-8 lg:p-12 text-center">
                    <h3 class="text-2xl lg:text-3xl font-bold text-[rgb(0,65,110)] mb-4">
                        Chcesz osiągnąć swoją docelową emeryturę?
                    </h3>
                    <p class="text-lg text-gray-600 mb-8 max-w-2xl mx-auto">
                        Zarejestruj się w naszym systemie, aby uzyskać spersonalizowane wskazówki dotyczące oszczędzania i planowania emerytury.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            :href="register()"
                            class="inline-block rounded-xl bg-[rgb(0,153,63)] px-8 py-4 text-xl font-semibold text-white hover:bg-[rgb(0,65,110)] transition-colors shadow-lg hover:shadow-xl"
                        >
                            Zacznij planować swoją przyszłość
                        </Link>
                        <button
                            @click="showResults = false; desiredPension = null; inputValue = ''"
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
