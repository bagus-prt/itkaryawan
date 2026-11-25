<script setup>
import { ref, reactive } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { IconEye, IconEyeOff } from "@tabler/icons-vue";

import danantaraLogo from "../../../../danantara.webp";
import pertaminaLogo from "../../../../pertamina_patra.webp";

const showPassword = ref(false);
const toast = ref("");
const clientErrors = reactive({
    email: "",
    password: "",
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

function clearFieldError(field) {
    clientErrors[field] = "";
    form.clearErrors(field);
    toast.value = "";
}

function submit() {
    clientErrors.email = "";
    clientErrors.password = "";
    toast.value = "";

    const email = form.email.trim();

    if (!email) {
        clientErrors.email = "Email wajib diisi.";
    }

    if (!form.password) {
        clientErrors.password = "Password wajib diisi.";
    }

    if (clientErrors.email || clientErrors.password) {
        toast.value = [clientErrors.email, clientErrors.password]
            .filter(Boolean)
            .join(" ");
        return;
    }

    form.email = email;

    form.post("/login", {
        onError: (errors) => {
            toast.value =
                errors.email ||
                errors.password ||
                "Login gagal. Periksa kembali data Anda.";
        },
    });
}
</script>

<template>
    <Head title="Masuk" />

    <main class="login-page">
        <header class="login-brands">
            <div class="brand-logo brand-logo--danantara">
                <img :src="danantaraLogo" alt="Danantara Indonesia" />
            </div>
            <div class="brand-logo brand-logo--pertamina">
                <img :src="pertaminaLogo" alt="Pertamina Patra Niaga" />
            </div>
        </header>

        <section class="login-card" aria-labelledby="login-heading">
            <div class="form-heading">
                <h1 id="login-heading">Masuk ke akun Anda</h1>
                <p>Masukkan email dan password untuk melanjutkan.</p>
            </div>

            <form novalidate @submit.prevent="submit">
                <div class="field-group">
                    <label for="email">Alamat email</label>

                    <div
                        class="input-wrap"
                        :class="{
                            'input-invalid':
                                clientErrors.email || form.errors.email,
                        }"
                    >
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            placeholder="nama@perusahaan.com"
                            autocomplete="username"
                            :aria-invalid="
                                !!(
                                    clientErrors.email ||
                                    form.errors.email
                                )
                            "
                            :aria-describedby="clientErrors.email || form.errors.email ? 'email-error' : undefined"
                            @input="clearFieldError('email')"
                        />
                    </div>

                    <p
                        v-if="clientErrors.email || form.errors.email"
                        id="email-error"
                        class="sr-only"
                    >
                        {{ clientErrors.email || form.errors.email }}
                    </p>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>

                    <div
                        class="input-wrap"
                        :class="{
                            'input-invalid':
                                clientErrors.password ||
                                form.errors.password,
                        }"
                    >
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            :aria-invalid="
                                !!(
                                    clientErrors.password ||
                                    form.errors.password
                                )
                            "
                            :aria-describedby="clientErrors.password || form.errors.password ? 'password-error' : undefined"
                            @input="clearFieldError('password')"
                        />

                        <button
                            type="button"
                            class="password-toggle"
                            :aria-label="
                                showPassword
                                    ? 'Sembunyikan password'
                                    : 'Tampilkan password'
                            "
                            :aria-pressed="showPassword"
                            @click="showPassword = !showPassword"
                        >
                            <IconEyeOff v-if="showPassword" :size="18" aria-hidden="true" />
                            <IconEye v-else :size="18" aria-hidden="true" />
                        </button>
                    </div>

                    <p
                        v-if="
                            clientErrors.password ||
                            form.errors.password
                        "
                        id="password-error"
                        class="sr-only"
                    >
                        {{
                            clientErrors.password ||
                            form.errors.password
                        }}
                    </p>
                </div>

                <div class="form-options">
                    <label class="remember-option">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            name="remember"
                        />
                        <span>Ingat saya</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="submit-button"
                    :disabled="form.processing"
                >
                    <span>
                        {{
                            form.processing
                                ? "Memeriksa akun..."
                                : "Masuk"
                        }}
                    </span>

                </button>
            </form>

            <div class="help-note">
                Mengalami kendala masuk?
                <span>Hubungi administrator.</span>
            </div>
        </section>

        <p class="copyright">© {{ new Date().getFullYear() }} IT Karyawan</p>

        <div class="toast-region">
            <div v-if="toast" class="toast-message">
                <span role="alert">{{ toast }}</span>
                <button type="button" aria-label="Tutup notifikasi" @click="toast = ''">
                    Tutup
                </button>
            </div>
        </div>
    </main>
</template>

<style scoped>
.login-page {
    --primary: #3a4c63;
    --primary-hover: #495e79;
    --text: #182433;
    --muted: #485568;
    --danger: #d63939;
    --page-gutter: clamp(16px, 4vw, 64px);

    position: relative;
    isolation: isolate;
    display: grid;
    place-items: center;
    min-height: 100dvh;
    padding: 108px var(--page-gutter);
    background: #363a3d;
    color: var(--text);
    font-size: 14px;
}

.login-page::before {
    position: absolute;
    z-index: -1;
    inset: 0;
    background: linear-gradient(180deg, rgb(240 238 232 / 49%), transparent 34%, rgb(12 12 12 / 62%)),
        url("../../../../latar.jpg") center 63% / cover no-repeat;
    filter: grayscale(80%);
    content: "";
}

.login-brands {
    position: absolute;
    inset: clamp(14px, 3vw, 36px) var(--page-gutter) auto;
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.brand-logo {
    display: grid;
    grid-template-rows: minmax(0, 1fr);
    place-items: center;
    width: clamp(116px, 21vw, 264px);
    height: clamp(44px, 6vw, 72px);
    padding-inline: clamp(6px, 1.4vw, 18px);
    overflow: hidden;
}

.brand-logo img {
    grid-area: 1 / 1;
    align-self: center;
    display: block;
    height: auto;
}

.brand-logo--danantara img {
    width: 100%;
    transform: translateY(-3%);
}

.brand-logo--pertamina img {
    width: 72%;
}

.login-card {
    width: min(100%, 420px);
    padding: clamp(22px, 5vw, 36px);
    border: 1px solid rgb(255 255 255 / 24%);
    border-radius: 16px;
    background: linear-gradient(170deg, rgb(29 29 29 / 49%), rgb(12 12 12 / 79%));
    backdrop-filter: blur(14px);
    box-shadow: 0 12px 32px rgb(0 0 0 / 16%);
    color: #fff;
}

.form-heading {
    margin-bottom: 30px;
    text-align: center;
}

.form-heading h1 {
    font-size: 24px;
    font-weight: 600;
    line-height: 1.4;
    letter-spacing: -0.025em;
}

.form-heading p {
    margin-top: 8px;
    color: #e4e4e4;
    line-height: 1.6;
}

.toast-region {
    position: fixed;
    z-index: 10;
    inset: auto var(--page-gutter) max(16px, env(safe-area-inset-bottom)) auto;
    width: min(380px, calc(100% - 2 * var(--page-gutter)));
    pointer-events: none;
}

.toast-message {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid #f0caca;
    border-left: 4px solid var(--danger);
    border-radius: 12px;
    background: #fff4f4;
    box-shadow: 0 10px 32px rgb(12 28 47 / 26%);
    color: #922323;
    font-size: 13px;
    line-height: 1.5;
    pointer-events: auto;
}

.toast-message > span {
    flex: 1;
}

.toast-message button {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    min-height: 32px;
    padding-inline: 8px;
    border-radius: 6px;
    cursor: pointer;
}

.toast-message button:hover {
    background: #f9dfdf;
}

.field-group {
    margin-bottom: 22px;
}

.field-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 500;
}

.input-wrap {
    display: flex;
    align-items: center;
    min-height: 42px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: #fff;
    transition:
        border-color 150ms ease,
        box-shadow 150ms ease;
}

.input-wrap:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgb(139 166 201 / 45%);
}

.input-wrap.input-invalid {
    border-color: var(--danger);
}

.input-wrap input {
    width: 100%;
    min-width: 0;
    padding: 10px 12px;
    outline: none;
    border: 0;
    background: transparent;
    color: var(--text);
    font: inherit;
}

.input-wrap input::placeholder {
    color: #9ca3af;
}

.password-toggle {
    display: grid;
    place-items: center;
    width: 42px;
    height: 40px;
    flex-shrink: 0;
    border: 0;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
}

.password-toggle:hover {
    color: var(--primary);
}

.form-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 2px 0 24px;
}

.remember-option {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: #eee;
    cursor: pointer;
    font-size: 12px;
}

.remember-option input {
    width: 15px;
    height: 15px;
    accent-color: var(--primary);
}

.submit-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 44px;
    padding: 6px 16px;
    border-radius: 4px;
    background: var(--primary);
    color: white;
    font-size: 14px;
    font-weight: 500;
    transition: background 150ms ease;
}

.submit-button:hover:not(:disabled) {
    background: var(--primary-hover);
}

.submit-button:focus-visible,
.password-toggle:focus-visible,
.toast-message button:focus-visible {
    outline: 3px solid #8ba6c9;
    outline-offset: 2px;
}

.submit-button:disabled {
    cursor: wait;
    opacity: 0.7;
}

.help-note {
    margin-top: 26px;
    padding-top: 20px;
    border-top: 1px solid rgb(255 255 255 / 18%);
    color: #e4e4e4;
    text-align: center;
    font-size: 12px;
    line-height: 1.8;
}

.help-note span {
    color: #c3d2e6;
    font-weight: 500;
}

.copyright {
    position: absolute;
    bottom: 20px;
    color: #fff;
    text-align: center;
    font-size: 12px;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        transition-duration: 0.01ms !important;
    }
}
</style>
