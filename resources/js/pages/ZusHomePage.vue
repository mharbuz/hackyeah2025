<template>
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




    <main>



     
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'


const isMobileMenuOpen = ref(false)

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}
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
</style>

