<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login.store'), {
        onFinish: () => {
            form.password = '';
        },
    });
};

// Event handlers dla stylów
const handleButtonMouseOver = (event: MouseEvent) => {
    if (!form.processing) {
        (event.target as HTMLElement).style.backgroundColor = 'rgb(0, 130, 53)';
    }
};

const handleButtonMouseOut = (event: MouseEvent) => {
    (event.target as HTMLElement).style.backgroundColor = 'rgb(0, 153, 63)';
};

const handleButtonFocus = (event: FocusEvent) => {
    (event.target as HTMLElement).style.boxShadow = '0 0 0 3px rgba(0, 153, 63, 0.3)';
};

const handleButtonBlur = (event: FocusEvent) => {
    (event.target as HTMLElement).style.boxShadow = 'none';
};

const handleEmailFocus = (event: FocusEvent) => {
    (event.target as HTMLInputElement).style.borderColor = 'rgb(63, 132, 210)';
};

const handleEmailBlur = (event: FocusEvent) => {
    (event.target as HTMLInputElement).style.borderColor = form.errors.email ? 'rgb(240, 94, 94)' : '#d1d5db';
};

const handlePasswordFocus = (event: FocusEvent) => {
    (event.target as HTMLInputElement).style.borderColor = 'rgb(63, 132, 210)';
};

const handlePasswordBlur = (event: FocusEvent) => {
    (event.target as HTMLInputElement).style.borderColor = form.errors.password ? 'rgb(240, 94, 94)' : '#d1d5db';
};

const handleLinkMouseOver = (event: MouseEvent) => {
    (event.target as HTMLElement).style.textDecoration = 'underline';
};

const handleLinkMouseOut = (event: MouseEvent) => {
    (event.target as HTMLElement).style.textDecoration = 'none';
};

const handleHomeLinkMouseOver = (event: MouseEvent) => {
    (event.target as HTMLElement).style.color = '#374151';
};

const handleHomeLinkMouseOut = (event: MouseEvent) => {
    (event.target as HTMLElement).style.color = '#6b7280';
};
</script>

<template>
    <Head title="Logowanie" />

    <div style="min-height: 100vh; background-color: #ffffff; display: flex; align-items: center; justify-content: center; padding: 24px;">
        <div style="width: 100%; max-width: 400px;">
            <!-- Logo i nagłówek -->
            <div style="text-align: center; margin-bottom: 32px;">
                <h1 style="font-size: 28px; font-weight: 600; color: rgb(0, 65, 110); margin-bottom: 8px;">
                    Symulator Emerytalny ZUS
                </h1>
                <p style="font-size: 16px; color: #6b7280;">
                    Zaloguj się do panelu
                </p>
            </div>

            <!-- Status message -->
            <div v-if="status" style="margin-bottom: 16px; padding: 12px; background-color: #d1fae5; border: 1px solid #6ee7b7; border-radius: 8px; color: #065f46; text-align: center;">
                {{ status }}
            </div>

            <!-- Formularz -->
            <form @submit.prevent="submit" style="background-color: #ffffff; padding: 32px; border: 2px solid rgb(190, 195, 206); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                
                <!-- Email -->
                <div style="margin-bottom: 20px;">
                    <label for="email" style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #374151;">
                        Adres email
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        style="width: 100%; padding: 12px 16px; font-size: 16px; border: 2px solid #d1d5db; border-radius: 8px; outline: none; transition: all 0.2s;"
                        :style="{ borderColor: form.errors.email ? 'rgb(240, 94, 94)' : '' }"
                        @focus="handleEmailFocus"
                        @blur="handleEmailBlur"
                    />
                    <div v-if="form.errors.email" style="margin-top: 6px; font-size: 14px; color: rgb(240, 94, 94);">
                        {{ form.errors.email }}
                    </div>
                </div>

                <!-- Hasło -->
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label for="password" style="font-size: 14px; font-weight: 500; color: #374151;">
                            Hasło
                        </label>
                        <a 
                            v-if="canResetPassword" 
                            :href="route('password.request')"
                            style="font-size: 14px; color: rgb(63, 132, 210); text-decoration: none;"
                            @mouseover="handleLinkMouseOver"
                            @mouseout="handleLinkMouseOut"
                        >
                            Zapomniałeś hasła?
                        </a>
                    </div>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        style="width: 100%; padding: 12px 16px; font-size: 16px; border: 2px solid #d1d5db; border-radius: 8px; outline: none; transition: all 0.2s;"
                        :style="{ borderColor: form.errors.password ? 'rgb(240, 94, 94)' : '' }"
                        @focus="handlePasswordFocus"
                        @blur="handlePasswordBlur"
                    />
                    <div v-if="form.errors.password" style="margin-top: 6px; font-size: 14px; color: rgb(240, 94, 94);">
                        {{ form.errors.password }}
                    </div>
                </div>

                <!-- Zapamiętaj mnie -->
                <div style="margin-bottom: 24px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            style="width: 18px; height: 18px; margin-right: 8px; cursor: pointer; accent-color: rgb(0, 153, 63);"
                        />
                        <span style="font-size: 14px; color: #374151;">
                            Zapamiętaj mnie
                        </span>
                    </label>
                </div>

                <!-- Przycisk logowania -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    style="width: 100%; padding: 14px 24px; font-size: 16px; font-weight: 600; color: #ffffff; background-color: rgb(0, 153, 63); border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; outline: none;"
                    :style="{ 
                        opacity: form.processing ? '0.7' : '1',
                        cursor: form.processing ? 'not-allowed' : 'pointer'
                    }"
                    @mouseover="handleButtonMouseOver"
                    @mouseout="handleButtonMouseOut"
                    @focus="handleButtonFocus"
                    @blur="handleButtonBlur"
                >
                    <span v-if="form.processing">Logowanie...</span>
                    <span v-else>Zaloguj się</span>
                </button>
            </form>

            <!-- Link do rejestracji -->
            <div style="margin-top: 24px; text-align: center; font-size: 14px; color: #6b7280;">
                Nie masz konta?
                <a 
                    :href="route('register')"
                    style="color: rgb(63, 132, 210); text-decoration: none; font-weight: 500;"
                    @mouseover="handleLinkMouseOver"
                    @mouseout="handleLinkMouseOut"
                >
                    Zarejestruj się
                </a>
            </div>

            <!-- Link powrotu -->
            <div style="margin-top: 16px; text-align: center;">
                <a 
                    :href="route('home')"
                    style="font-size: 14px; color: #6b7280; text-decoration: none;"
                    @mouseover="handleHomeLinkMouseOver"
                    @mouseout="handleHomeLinkMouseOut"
                >
                    ← Powrót do strony głównej
                </a>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Reset i style bazowe */
* {
    box-sizing: border-box;
}

/* Wymuszenie jasnego tła dla całej strony */
html, body {
    background-color: #ffffff !important;
    color: #111827 !important;
}

/* Dostępność - focus states */
input:focus,
button:focus,
a:focus {
    outline: 3px solid rgb(63, 132, 210);
    outline-offset: 2px;
}

/* Responsywność */
@media (max-width: 640px) {
    div[style*="padding: 32px"] {
        padding: 24px !important;
    }
}
</style>
