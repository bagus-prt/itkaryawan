<script setup>
import { ref, reactive } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import {
    IconEye,
    IconEyeOff,
    IconMail,
    IconLock,
    IconArrowRight,
    IconShieldCheck,
} from "@tabler/icons-vue";

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
        toast.value = "Periksa kembali kolom yang bertanda merah.";
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

    <main class="login-page min-h-screen">
        <div class="login-shell">
            <section class="brand-panel">
                <div class="brand-lockup">
                    <div class="brand-mark">IT</div>
                    <span>IT Karyawan</span>
                </div>

                <div class="brand-content">
                    <span class="eyebrow">SISTEM INFORMASI KARYAWAN</span>

                    <h1>Kelola pekerjaan dengan lebih teratur.</h1>

                    <p>
                        Akses layanan kepegawaian, dokumen, inventaris, dan
                        informasi pekerjaan melalui satu sistem.
                    </p>

                    <div class="brand-note">
                        <span class="note-icon">
                            <IconShieldCheck :size="22" :stroke="1.8" />
                        </span>
                        <div>
                            <strong>Akses sesuai peran</strong>
                            <p>
                                Informasi dan fitur disesuaikan dengan hak akses
                                akun Anda.
                            </p>
                        </div>
                    </div>
                </div>

                <p class="brand-footer">Sistem internal · IT Karyawan</p>
            </section>

            <section class="form-panel">
                <div class="login-card">
                    <div class="mobile-brand">
                        <div class="brand-mark">IT</div>
                        <span>IT Karyawan</span>
                    </div>

                    <div class="form-heading">
                        <span class="eyebrow">SELAMAT DATANG</span>
                        <h2>Masuk ke akun Anda</h2>
                        <p>Masukkan email dan password untuk melanjutkan.</p>
                    </div>

                    <div
                        v-if="toast"
                        class="toast-message"
                        role="alert"
                        aria-live="polite"
                    >
                        <span class="toast-dot"></span>
                        <span>{{ toast }}</span>
                        <button
                            type="button"
                            aria-label="Tutup notifikasi"
                            @click="toast = ''"
                        >
                            ×
                        </button>
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
                                <IconMail
                                    class="field-icon"
                                    :size="18"
                                    :stroke="1.8"
                                />

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
                                    aria-describedby="email-error"
                                    @input="clearFieldError('email')"
                                />
                            </div>

                            <p
                                v-if="clientErrors.email || form.errors.email"
                                id="email-error"
                                class="field-error"
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
                                <IconLock
                                    class="field-icon"
                                    :size="18"
                                    :stroke="1.8"
                                />

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
                                    aria-describedby="password-error"
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
                                    <IconEyeOff
                                        v-if="showPassword"
                                        :size="18"
                                        :stroke="1.8"
                                    />
                                    <IconEye v-else :size="18" :stroke="1.8" />
                                </button>
                            </div>

                            <p
                                v-if="
                                    clientErrors.password ||
                                    form.errors.password
                                "
                                id="password-error"
                                class="field-error"
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

                            <span class="submit-icon">
                                <IconArrowRight :size="18" :stroke="2" />
                            </span>
                        </button>
                    </form>

                    <div class="help-note">
                        Mengalami kendala masuk?
                        <span>Hubungi administrator.</span>
                    </div>
                </div>

                <p class="copyright">
                    © {{ new Date().getFullYear() }} IT Karyawan
                </p>
            </section>
        </div>
    </main>
</template>

<style scoped>
.login-page {
    --primary: #066fd1;
    --primary-hover: #045db0;
    --surface: #ffffff;
    --border: #e6e7e9;
    --text: #182433;
    --muted: #667382;
    --danger: #d63939;

    background: #f6f8fb;
    color: var(--text);
    font-size: 14px;
}

.login-shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr);
}

.brand-panel {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 40px clamp(28px, 5vw, 76px);
    background: #edf4fb;
    border-right: 1px solid #e1eaf4;
}

.brand-lockup,
.mobile-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 15px;
    font-weight: 600;
}

.brand-mark {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 4px;
    background: var(--primary);
    color: white;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: -0.04em;
}

.brand-content {
    max-width: 430px;
    margin: 60px 0;
}

.eyebrow {
    color: var(--primary);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
}

.brand-content h1 {
    margin-top: 20px;
    max-width: 410px;
    font-size: clamp(30px, 3.2vw, 42px);
    font-weight: 600;
    line-height: 1.2;
    letter-spacing: -0.04em;
}

.brand-content > p {
    max-width: 390px;
    margin-top: 18px;
    color: var(--muted);
    line-height: 1.8;
}

.brand-note {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-top: 42px;
    padding: 18px;
    border: 1px solid #dce7f2;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.72);
}

.note-icon {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border-radius: 4px;
    background: #e9f0f9;
    color: var(--primary);
}

.brand-note strong {
    display: block;
    font-size: 13px;
    font-weight: 600;
}

.brand-note p {
    margin-top: 5px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.brand-footer,
.copyright {
    color: var(--muted);
    font-size: 12px;
}

.form-panel {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 36px 24px 20px;
}

.login-card {
    width: 100%;
    max-width: 420px;
    padding: 36px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--surface);
    box-shadow: 0 0 4px rgba(24, 36, 51, 0.04);
}

.mobile-brand {
    display: none;
    margin-bottom: 32px;
}

.form-heading {
    margin-bottom: 30px;
}

.form-heading h2 {
    margin-top: 10px;
    font-size: 24px;
    font-weight: 600;
    line-height: 1.4;
    letter-spacing: -0.025em;
}

.form-heading p {
    margin-top: 8px;
    color: var(--muted);
    line-height: 1.6;
}

.toast-message {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 22px;
    padding: 12px;
    border: 1px solid #f0caca;
    border-radius: 4px;
    background: #fbebeb;
    color: #a52222;
    font-size: 12px;
    line-height: 1.6;
}

.toast-dot {
    width: 7px;
    height: 7px;
    margin-top: 5px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--danger);
}

.toast-message > span:nth-child(2) {
    flex: 1;
}

.toast-message button {
    font-size: 19px;
    line-height: 1;
    opacity: 0.75;
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
    box-shadow: 0 0 0 3px rgba(6, 111, 209, 0.14);
}

.input-wrap.input-invalid {
    border-color: var(--danger);
}

.field-icon {
    margin-left: 12px;
    flex-shrink: 0;
    color: var(--muted);
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
    color: var(--muted);
}

.password-toggle:hover {
    color: var(--primary);
}

.field-error {
    margin-top: 7px;
    color: var(--danger);
    font-size: 12px;
    line-height: 1.5;
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
    color: var(--muted);
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
    justify-content: space-between;
    width: 100%;
    min-height: 44px;
    padding: 6px 8px 6px 16px;
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
    outline: 3px solid rgba(6, 111, 209, 0.3);
    outline-offset: 2px;
}

.submit-button:disabled {
    cursor: wait;
    opacity: 0.7;
}

.submit-icon {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.15);
}

.help-note {
    margin-top: 26px;
    padding-top: 20px;
    border-top: 1px solid #eef0f2;
    color: var(--muted);
    text-align: center;
    font-size: 12px;
    line-height: 1.8;
}

.help-note span {
    color: var(--primary);
    font-weight: 500;
}

.copyright {
    margin-top: 24px;
    text-align: center;
}

@media (max-width: 900px) {
    .login-shell {
        grid-template-columns: 0.85fr 1.15fr;
    }

    .brand-panel {
        padding: 32px 28px;
    }

    .login-card {
        padding: 28px;
    }
}

@media (max-width: 700px) {
    .login-shell {
        grid-template-columns: 1fr;
    }

    .brand-panel {
        display: none;
    }

    .form-panel {
        min-height: 100vh;
        padding: 28px 18px;
    }

    .login-card {
        max-width: 440px;
        padding: 28px 24px;
    }

    .mobile-brand {
        display: flex;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        transition-duration: 0.01ms !important;
    }
}
</style>
