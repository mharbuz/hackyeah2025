<template>
  <div class="min-h-screen bg-white zus-page">
    <!-- Top Header Bar -->
    <header class="bg-white border-b">
      <div class="max-w-[1400px] mx-auto px-2 sm:px-4 lg:px-8">
        <div class="flex items-center  py-5 sm:py-5 gap-2">
          <!-- Logo -->
          <div class="flex items-center shrink-0 mr-5">
            <img
              src="/logo_zus_darker_with_text.svg"
              alt="ZUS Logo"
              class="h-10 sm:h-12 md:h-14 lg:h-12 w-auto"
            />
          </div>

          <!-- Right Side Navigation -->
          <div class="flex items-center gap-1 sm:gap-2 flex-wrap justify-end">
            <!-- Kontakt -->
            <a href="#" class="text-sm font-medium text-gray-700 hover:text-gray-900 hidden xl:block">
              Kontakt
            </a>

            <!-- Language Selector -->
            <div class="relative hidden xl:block">
              <button class="flex items-center space-x-1 text-sm font-medium text-gray-700 hover:text-gray-900">
                <span>PL</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            </div>

            <!-- Accessibility Icons -->
            <div class="flex items-center gap-1 sm:gap-3">
              <!-- Icon - Słuch -->
              <button
                class="p-1.5 sm:p-2 rounded hover:opacity-90 transition-colors"
                style="background-color: rgb(0, 65, 110);"
                aria-label="Wersja dla osób niesłyszących"
              >
                <img src="/ikona_ucho.svg" alt="Ikona ucha" class="h-5 w-5 sm:h-6 sm:w-6" />
              </button>

              <!-- Icon - Wózek -->
              <button
                class="p-1.5 sm:p-2 rounded hover:opacity-90 transition-colors"
                style="background-color: rgb(0, 65, 110);"
                aria-label="Wersja dla osób niepełnosprawnych"
              >
                <img src="/ikona_wozek.svg" alt="Ikona wózka" class="h-5 w-5 sm:h-6 sm:w-6" />
              </button>

              <!-- BIP Icon -->
              <button class="p-1.5 sm:p-2 rounded hover:bg-gray-100 transition-colors" aria-label="BIP">
                <img src="/bip_simple.svg" alt="BIP" class="h-8 w-8 sm:h-9 sm:w-9" />
              </button>
            </div>

            <!-- Login Buttons -->
            <div class="flex items-center gap-1 sm:gap-2">
              <button
                class="hidden lg:flex px-3 xl:px-4 py-1.5 xl:py-2 text-xs xl:text-sm font-semibold border-2 rounded hover:opacity-90 transition-colors items-center"
                style="color: rgb(0, 65, 110); border-color: rgb(0, 65, 110); white-space: nowrap;"
              >
                <span>Zarejestruj w PUE/eZUS</span>
                <svg class="w-3 h-3 xl:w-4 xl:h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
              <button
                class="flex px-2 sm:px-3 xl:px-4 py-1.5 xl:py-2 text-xs xl:text-sm font-semibold text-gray-900 rounded hover:opacity-90 transition-colors items-center border-2"
                style="background-color: rgb(250, 184, 86); border-color: rgb(0, 65, 110); white-space: nowrap;"
              >
                <span class="hidden sm:inline" style="color: rgb(0, 65, 110);">Zaloguj do PUE/eZUS</span>
                <span class="sm:hidden">Zaloguj</span>
                <svg class="w-3 h-3 xl:w-4 xl:h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>

            <!-- Search Icon -->
            <button
              class="p-1.5 sm:p-2 rounded-full hover:opacity-90 transition-colors"
              style="background-color: rgb(17, 120, 59);"
              aria-label="Szukaj"
            >
              <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </button>

            <!-- EU Logo -->
            <div class="hidden 2xl:flex items-center ml-2">

              <img src="/eu_pl_chromatic.jpg" alt="Unia Europejska" class="h-12 w-auto" />
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Navigation Menu -->
    <nav class="border-b" style="background-color: rgb(245, 245, 245);">
      <div class="max-w-[1400px] mx-auto">
        <div class="flex items-center overflow-x-auto no-scrollbar">
          <div class="flex">
            <a
              v-for="item in menuItems"
              :key="item.name"
              :href="item.href"
              class="px-4 sm:px-5 lg:px-6 py-3 sm:py-4 text-sm sm:text-base font-semibold hover:bg-white transition-colors relative whitespace-nowrap border-b-4 border-transparent hover:border-green-600"
              :class="item.active ? 'bg-white' : ''"
              :style="item.active ? { borderBottomColor: 'rgb(0, 153, 63)', color: 'rgb(0, 0, 0)' } : { color: 'rgb(60, 60, 60)' }"
            >
              {{ item.name }}
            </a>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Main Content -->
        <div class="lg:col-span-2">
          <h1 class="text-4xl font-bold mb-6" style="color: rgb(0, 0, 0);">
            Dni Seniora w ZUS
          </h1>

          <div class="space-y-4 mb-6">
            <h2 class="text-xl font-bold mb-4">Przyjdź i skorzystaj z:</h2>
            <ul class="space-y-2 ml-4">
              <li class="flex items-start">
                <span class="mr-2">→</span>
                <span>porad ekspertów ZUS</span>
              </li>
              <li class="flex items-start">
                <span class="mr-2">→</span>
                <span>porad specjalistów z instytucji partnerskich</span>
              </li>
              <li class="flex items-start">
                <span class="mr-2">→</span>
                <span>wykładów, prelekcji i warsztatów</span>
              </li>
              <li class="flex items-start">
                <span class="mr-2">→</span>
                <span>materiałów informacyjnych i edukacyjnych</span>
              </li>
              <li class="flex items-start">
                <span class="mr-2">→</span>
                <span>atrakcji kulturalnych</span>
              </li>
            </ul>
          </div>

          <div class="mb-6">
            <p class="font-bold mb-2">Sprawdź, co się będzie działo w Twojej okolicy!</p>
            <p class="mb-2">
              Wydarzenia w ramach Dni Seniora w ZUS odbywają się na terenie całego kraju. Szczegółowy harmonogram znajdziesz na
              <a href="#" class="text-blue-600 hover:underline">stronie internetowej Dni Seniora</a>.
            </p>
            <p>
              Wszystkie wydarzenia są <span class="font-bold">otwarte</span>, a udział w nich jest <span class="font-bold">bezpłatny</span>.
            </p>
          </div>

          <div class="mb-8">
            <a
              href="#"
              class="inline-flex items-center text-sm hover:underline"
              style="color: rgb(0, 153, 63);"
            >
              Harmonogram wydarzeń (plik xlsx, 201 kb)
            </a>
          </div>
        </div>

        <!-- Right Column - Image and Info -->
        <div class="lg:col-span-1">
          <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-lg overflow-hidden mb-6">
            <img
              src="https://images.unsplash.com/photo-1609220136736-443140cffec6?w=600&h=400&fit=crop"
              alt="Seniorzy"
              class="w-full h-auto object-cover"
            />
          </div>

          <!-- Podcast Info Box -->
          <div class="bg-white border rounded-lg p-6 mb-6">
            <h3 class="text-xl font-bold mb-2">„ZUS na głos"</h3>
            <p class="text-sm text-gray-600 mb-4">29.09.2025</p>
            <p class="text-sm mb-4">
              Gościem tego odcinka jest Rzecznik Finansowy, dr Michał Ziemiak. Rozmawiamy o bezpieczeństwie transakcji bankowych, jak ustrzec się przed internetowymi oszustami oraz o tym, co możemy zrobić, kiedy padniemy ofiarą takiego przestępstwa.
            </p>
            <div class="mb-4">
              <div class="bg-white rounded-lg overflow-hidden">
                <img
                  src="https://images.unsplash.com/photo-1478737270239-2f02b77fc618?w=400&h=300&fit=crop"
                  alt="ZUS na głos podcast"
                  class="w-full h-auto"
                />
              </div>
            </div>
            <a href="#" class="text-sm hover:underline" style="color: rgb(0, 153, 63);">
              Więcej o tym odcinku
            </a>
          </div>

          <!-- Navigation Arrows -->
          <div class="flex justify-center space-x-4 mb-6">
            <button
              class="p-3 rounded-full border-2 hover:bg-gray-50 transition-colors"
              style="border-color: rgb(0, 153, 63);"
              aria-label="Poprzedni"
            >
              <svg class="w-6 h-6" style="color: rgb(0, 153, 63);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <button
              class="p-3 rounded-full border-2 hover:bg-gray-50 transition-colors"
              style="border-color: rgb(0, 153, 63);"
              aria-label="Następny"
            >
              <svg class="w-6 h-6" style="color: rgb(0, 153, 63);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom Navigation Tabs -->
      <div class="mt-12 border-t" style="border-color: rgb(0, 153, 63); border-width: 4px;">
        <div class="flex flex-wrap -mt-1">
          <a
            v-for="tab in bottomTabs"
            :key="tab.name"
            :href="tab.href"
            class="px-6 py-3 text-sm font-medium text-white hover:opacity-90 transition-colors text-center flex-grow"
            :style="{ backgroundColor: tab.color }"
          >
            {{ tab.name }}
          </a>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const menuItems = ref([
  { name: 'Świadczenia', href: '#', active: false },
  { name: 'Firmy', href: '#', active: false },
  { name: 'Pracujący', href: '#', active: false },
  { name: 'Lekarze', href: '#', active: false },
  { name: 'Wzory formularzy', href: '#', active: false },
  { name: 'Baza wiedzy', href: '#', active: false },
  { name: 'O ZUS', href: '#', active: false },
])

const bottomTabs = ref([
  { name: 'Wakacje składkowe', href: '#', color: 'rgb(255, 179, 79)' },
  { name: 'Dni Seniora', href: '#', color: 'rgb(0, 153, 63)' },
  { name: 'Renta wdowia', href: '#', color: 'rgb(190, 195, 206)' },
  { name: 'Zasiłek macierzyński', href: '#', color: 'rgb(63, 132, 210)' },
  { name: 'Pomoc dla powodzian', href: '#', color: 'rgb(0, 65, 110)' },
  { name: 'Program Dobry Start', href: '#', color: 'rgb(240, 94, 94)' },
  { name: 'Wizyta w ZUS', href: '#', color: 'rgb(0, 0, 0)' },
  { name: 'ВАЖЛИВО!', href: '#', color: 'rgb(0, 153, 63)' },
])
</script>

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

/* Tablet - średnie ekrany */
@media (max-width: 1280px) {
  header .gap-2 {
    gap: 0.375rem;
  }
}

@media (max-width: 1024px) {
  header .flex.items-center.justify-between {
    gap: 0.5rem;
  }
}

/* Małe ekrany mobilne */
@media (max-width: 768px) {
  header .flex.items-center.gap-1 {
    gap: 0.25rem;
  }

  /* Zapewnij, że header się nie owija niepotrzebnie */
  header .flex.items-center.justify-between {
    flex-wrap: nowrap;
  }

  header .flex.items-center.flex-wrap {
    flex-wrap: nowrap;
  }
}

/* Bardzo małe ekrany */
@media (max-width: 640px) {
  header .flex.items-center.justify-between {
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
  }

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

/* Dodatkowe style dla buttons - zapewnienie że nie się nie owija */
button, a {
  flex-shrink: 0;
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
</style>

