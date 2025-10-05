<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Download, Users, Calendar, TrendingUp, LogOut } from 'lucide-vue-next';

interface Statistics {
  total: number;
  gender: {
    male: number;
    female: number;
    unknown: number;
  };
}

interface Simulation {
  id: number;
  date: string;
  pension_value: number;
  gender: string | null;
  age: number | null;
  postal_code: string | null;
}

interface Props {
  statistics: Statistics;
  recentSimulations: Simulation[];
  filters: {
    start_date?: string;
    end_date?: string;
  };
}

const props = defineProps<Props>();

const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');
const isExporting = ref(false);

// Kolory zgodne z wytycznymi ZUS
const colors = {
  primary: 'rgb(0, 65, 110)',
  secondary: 'rgb(0, 153, 63)',
  accent: 'rgb(255, 179, 79)',
  info: 'rgb(63, 132, 210)',
  gray: 'rgb(190, 195, 206)',
};

const applyFilters = () => {
  router.get(route('admin.dashboard'), {
    start_date: startDate.value,
    end_date: endDate.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  startDate.value = '';
  endDate.value = '';
  router.get(route('admin.dashboard'), {}, {
    preserveState: true,
    preserveScroll: true,
  });
};

const exportReport = async () => {
  isExporting.value = true;
  
  try {
    const params = new URLSearchParams();
    if (startDate.value) params.append('start_date', startDate.value);
    if (endDate.value) params.append('end_date', endDate.value);

    const response = await fetch(`/admin/export-report?${params.toString()}`, {
      method: 'GET',
      headers: {
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      },
    });

    if (!response.ok) {
      throw new Error('Błąd podczas pobierania raportu');
    }

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `raport_symulacji_${new Date().toISOString().split('T')[0]}.xlsx`;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
  } catch (error) {
    console.error('Błąd eksportu:', error);
    alert('Wystąpił błąd podczas eksportu raportu');
  } finally {
    isExporting.value = false;
  }
};

const genderLabel = (gender: string | null) => {
  switch (gender) {
    case 'male': return 'Mężczyzna';
    case 'female': return 'Kobieta';
    default: return 'Nie podano';
  }
};

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency: 'PLN',
  }).format(value);
};

const genderPercentage = (count: number) => {
  if (props.statistics.total === 0) return '0%';
  return ((count / props.statistics.total) * 100).toFixed(1) + '%';
};

const handleLogout = () => {
  router.post('/admin/logout', {}, {
    onSuccess: () => {
      router.visit('/admin/login');
    },
  });
};
</script>

<template>
  <Head title="Panel Administratora" />

  <div class="min-h-screen bg-white py-8 px-4 sm:px-6 lg:px-8 light">
    <div class="max-w-7xl mx-auto">
      <!-- Nagłówek z przyciskiem wylogowania -->
      <div class="mb-8 flex justify-between items-start">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 mb-2">
            Panel Administratora
          </h1>
          <p class="text-gray-600">
            Zarządzaj danymi symulacji emerytalnych i generuj raporty
          </p>
        </div>
        <Button 
          @click="handleLogout" 
          variant="outline"
          size="lg"
          class="flex items-center gap-2"
        >
          <LogOut class="h-5 w-5" />
          Wyloguj
        </Button>
      </div>

      <!-- Filtry i eksport -->
      <Card class="mb-6" :style="{ borderColor: colors.primary, borderWidth: '2px' }">
        <CardHeader>
          <CardTitle>Filtry i eksport</CardTitle>
          <CardDescription>Wybierz zakres dat i eksportuj raport do Excel</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <Label for="start_date">Data od</Label>
              <Input
                id="start_date"
                v-model="startDate"
                type="date"
                class="mt-1"
              />
            </div>
            <div>
              <Label for="end_date">Data do</Label>
              <Input
                id="end_date"
                v-model="endDate"
                type="date"
                class="mt-1"
              />
            </div>
            <div class="flex items-end gap-2">
              <Button @click="applyFilters" :style="{ backgroundColor: colors.primary }" class="text-white">
                <Calendar class="mr-2 h-4 w-4" />
                Zastosuj
              </Button>
              <Button @click="clearFilters" variant="outline">
                Wyczyść
              </Button>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <Button
              @click="exportReport"
              :disabled="isExporting"
              :style="{ backgroundColor: colors.secondary }"
              class="text-white"
              size="lg"
            >
              <Download class="mr-2 h-5 w-5" />
              {{ isExporting ? 'Eksportowanie...' : 'Eksportuj raport (XLS)' }}
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Statystyki -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <!-- Łączna liczba symulacji -->
        <Card :style="{ borderLeftColor: colors.primary, borderLeftWidth: '4px' }">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">
              Łączna liczba symulacji
            </CardTitle>
            <TrendingUp class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ statistics.total }}</div>
            <p class="text-xs text-muted-foreground mt-1">
              Wszystkie symulacje w systemie
            </p>
          </CardContent>
        </Card>

        <!-- Mężczyźni -->
        <Card :style="{ borderLeftColor: colors.info, borderLeftWidth: '4px' }">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">
              Mężczyźni
            </CardTitle>
            <Users class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ statistics.gender.male }}</div>
            <p class="text-xs text-muted-foreground mt-1">
              {{ genderPercentage(statistics.gender.male) }} wszystkich symulacji
            </p>
          </CardContent>
        </Card>

        <!-- Kobiety -->
        <Card :style="{ borderLeftColor: colors.accent, borderLeftWidth: '4px' }">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">
              Kobiety
            </CardTitle>
            <Users class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ statistics.gender.female }}</div>
            <p class="text-xs text-muted-foreground mt-1">
              {{ genderPercentage(statistics.gender.female) }} wszystkich symulacji
            </p>
          </CardContent>
        </Card>

        <!-- Nie podano -->
        <Card :style="{ borderLeftColor: colors.gray, borderLeftWidth: '4px' }">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">
              Nie podano płci
            </CardTitle>
            <Users class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ statistics.gender.unknown }}</div>
            <p class="text-xs text-muted-foreground mt-1">
              {{ genderPercentage(statistics.gender.unknown) }} wszystkich symulacji
            </p>
          </CardContent>
        </Card>
      </div>

      <!-- Ostatnie symulacje -->
      <Card>
        <CardHeader>
          <CardTitle>Ostatnie symulacje</CardTitle>
          <CardDescription>10 najnowszych symulacji w systemie</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Data i godzina</TableHead>
                  <TableHead>Płeć</TableHead>
                  <TableHead>Wiek</TableHead>
                  <TableHead>Emerytura</TableHead>
                  <TableHead>Kod pocztowy</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="simulation in recentSimulations" :key="simulation.id">
                  <TableCell class="font-medium">{{ simulation.date }}</TableCell>
                  <TableCell>{{ genderLabel(simulation.gender) }}</TableCell>
                  <TableCell>{{ simulation.age || 'Nie podano' }}</TableCell>
                  <TableCell>{{ formatCurrency(simulation.pension_value) }}</TableCell>
                  <TableCell>{{ simulation.postal_code || 'Nie podano' }}</TableCell>
                </TableRow>
                <TableRow v-if="recentSimulations.length === 0">
                  <TableCell colspan="5" class="text-center text-muted-foreground py-8">
                    Brak symulacji do wyświetlenia
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

<style scoped>
/* Wymuś jasny tryb dla panelu administratora */
.light {
  color-scheme: light;
}

.light * {
  color-scheme: light;
}

/* Wymuś jasne tło i tekst dla wszystkich kart */
.light :deep(.card),
.light :deep([class*="card"]) {
  background-color: white !important;
  color: rgb(17, 24, 39) !important;
}

/* Wymuś jasne tło dla inputów */
.light :deep(input),
.light :deep(select),
.light :deep(textarea) {
  background-color: white !important;
  color: rgb(17, 24, 39) !important;
  border-color: rgb(209, 213, 219) !important;
}

/* Wymuś jasne tło dla tabeli */
.light :deep(table) {
  background-color: white !important;
}

.light :deep(th),
.light :deep(td) {
  color: rgb(17, 24, 39) !important;
  border-color: rgb(229, 231, 235) !important;
}

.light :deep(thead) {
  background-color: rgb(249, 250, 251) !important;
}

/* Wymuś odpowiednie kolory dla tekstu pomocniczego */
.light :deep(.text-muted-foreground) {
  color: rgb(107, 114, 128) !important;
}

/* Dodatkowe style zgodne z WCAG 2.0 */
button:focus-visible,
input:focus-visible {
  outline: 3px solid rgb(63, 132, 210);
  outline-offset: 2px;
}

/* Zwiększenie kontrastu dla lepszej czytelności */
.text-muted-foreground {
  color: rgb(75, 85, 99);
}
</style>

