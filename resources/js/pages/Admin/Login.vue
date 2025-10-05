<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const email = ref('');
const password = ref('');
const remember = ref(false);
const errors = ref<{ email?: string; password?: string }>({});
const processing = ref(false);

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post('/admin/login', {
        email: email.value,
        password: password.value,
        remember: remember.value,
    }, {
        onError: (err) => {
            errors.value = err;
            processing.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <div style="min-height: 100vh; background: linear-gradient(135deg, rgb(0, 65, 110) 0%, rgb(0, 153, 63) 100%); display: flex; align-items: center; justify-content: center; padding: 20px; font-family: Arial, sans-serif;">
        <div style="width: 100%; max-width: 450px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); padding: 48px 40px;">
            
            <!-- Logo i nagłówek -->
            <div style="text-align: center; margin-bottom: 40px;">
                <div style="width: 80px; height: 80px; margin: 0 auto 20px; background-color: rgb(0, 153, 63); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 40px; height: 40px; fill: white;" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <h1 style="font-size: 28px; font-weight: 700; color: rgb(0, 65, 110); margin: 0 0 8px 0;">
                    Panel Administratora
                </h1>
                <p style="font-size: 15px; color: #6b7280; margin: 0;">
                    Symulator Emerytalny ZUS
                </p>
            </div>

            <!-- Formularz -->
            <form @submit.prevent="submit">
                
                <!-- Email -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #374151;">
                        Adres email
                    </label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="admin@admin.pl"
                        style="width: 100%; padding: 14px 16px; font-size: 15px; border: 2px solid #e5e7eb; border-radius: 10px; outline: none; transition: all 0.2s; box-sizing: border-box; background-color: #ffffff; color: #111827;"
                    />
                    <div v-if="errors.email" style="margin-top: 8px; font-size: 13px; color: rgb(240, 94, 94);">
                        {{ errors.email }}
                    </div>
                </div>

                <!-- Hasło -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #374151;">
                        Hasło
                    </label>
                    <input
                        v-model="password"
                        type="password"
                        required
                        placeholder="••••••••"
                        style="width: 100%; padding: 14px 16px; font-size: 15px; border: 2px solid #e5e7eb; border-radius: 10px; outline: none; transition: all 0.2s; box-sizing: border-box; background-color: #ffffff; color: #111827;"
                    />
                    <div v-if="errors.password" style="margin-top: 8px; font-size: 13px; color: rgb(240, 94, 94);">
                        {{ errors.password }}
                    </div>
                </div>

                <!-- Zapamiętaj -->
                <div style="margin-bottom: 32px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input
                            v-model="remember"
                            type="checkbox"
                            style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;"
                        />
                        <span style="font-size: 14px; color: #374151;">
                            Zapamiętaj mnie
                        </span>
                    </label>
                </div>

                <!-- Przycisk -->
                <button
                    type="submit"
                    :disabled="processing"
                    style="width: 100%; padding: 16px; font-size: 16px; font-weight: 600; color: #ffffff; background: linear-gradient(135deg, rgb(0, 153, 63) 0%, rgb(0, 130, 53) 100%); border: none; border-radius: 10px; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0, 153, 63, 0.3);"
                    :style="{ opacity: processing ? 0.7 : 1, cursor: processing ? 'not-allowed' : 'pointer' }"
                >
                    <span v-if="processing">Logowanie...</span>
                    <span v-else>Zaloguj się</span>
                </button>
            </form>

            <!-- Info -->
            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; text-align: center;">
                <p style="font-size: 13px; color: #9ca3af; margin: 0;">
                    Dostęp tylko dla administratorów systemu
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
input {
    background-color: #ffffff !important;
    color: #111827 !important;
}

input::placeholder {
    color: #9ca3af !important;
    opacity: 1 !important;
}

input:focus {
    border-color: rgb(63, 132, 210) !important;
    box-shadow: 0 0 0 3px rgba(63, 132, 210, 0.1);
}

input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
    -webkit-text-fill-color: #111827 !important;
    -webkit-box-shadow: 0 0 0px 1000px #ffffff inset !important;
    box-shadow: 0 0 0px 1000px #ffffff inset !important;
}

button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 153, 63, 0.4);
}

button:active:not(:disabled) {
    transform: translateY(0);
}
</style>

