<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { IconBuildingStore, IconMessageCircle } from '@tabler/icons-vue'

const props = defineProps({
    today: { type: String, required: true },
})

const page = usePage()
const form = useForm({
    station_code: '',
    station_address: '',
    description: '',
})

function submitForm() {
    form.post('/keluhan', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <main class="public-page inventory-workspace">
        <header class="public-header">
            <div class="brand-mark"><IconBuildingStore :size="22" /></div>
            <div>
                <strong>Saluran Keluhan SPBU</strong>
                <span>Pertamina · Layanan untuk SPBU</span>
            </div>
        </header>

        <section class="form-card form-modal">
            <div class="form-title">
                <h1>Sampaikan Keluhan SPBU</h1>
                <p>Isi informasi SPBU dan jelaskan keluhan yang ingin disampaikan kepada Pertamina.</p>
            </div>

            <div v-if="page.props.flash?.success" class="success-notice" role="status">
                <IconMessageCircle :size="20" />
                <span>{{ page.props.flash.success }}</span>
            </div>

            <form class="complaint-form" @submit.prevent="submitForm">
                <div class="field-group">
                    <label for="public-station-code">Nomor / kode SPBU <span>*</span></label>
                    <input
                        id="public-station-code"
                        v-model="form.station_code"
                        type="text"
                        maxlength="50"
                        autocomplete="organization"
                        placeholder="Contoh: 31.401.01"
                        required
                    />
                    <small v-if="form.errors.station_code" class="field-error">{{ form.errors.station_code }}</small>
                </div>

                <div class="field-group">
                    <label for="public-station-address">Lokasi / alamat SPBU <span>*</span></label>
                    <textarea
                        id="public-station-address"
                        v-model="form.station_address"
                        rows="2"
                        maxlength="2000"
                        placeholder="Masukkan alamat lengkap SPBU"
                        required
                    ></textarea>
                    <small v-if="form.errors.station_address" class="field-error">{{ form.errors.station_address }}</small>
                </div>

                <div class="field-group">
                    <label for="public-complaint-date">Tanggal keluhan <span class="auto-label">Terisi otomatis</span></label>
                    <input id="public-complaint-date" type="date" :value="today" disabled />
                </div>

                <div class="field-group">
                    <label for="public-description">Keterangan keluhan <span>*</span></label>
                    <textarea
                        id="public-description"
                        v-model="form.description"
                        rows="4"
                        maxlength="10000"
                        placeholder="Jelaskan keluhan secara rinci agar dapat ditindaklanjuti."
                        required
                    ></textarea>
                    <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
                </div>

                <button class="submit-button primary-button" type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Mengirim...' : 'Kirim keluhan' }}
                </button>
            </form>

        </section>
    </main>
</template>

<style scoped>
.public-page { min-height: 100vh; padding: 28px 16px 50px; color: #182433; background: #f2f4f8; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
.public-header { display: flex; align-items: center; gap: 11px; width: min(100%, 620px); margin: 0 auto 20px; }
.brand-mark { display: grid; width: 42px; height: 42px; place-items: center; color: #045db0; background: #e9f0f9; border-radius: 14px; }
.public-header strong, .public-header span { display: block; }
.public-header strong { font-size: 14px; }
.public-header span { margin-top: 3px; color: #667382; font-size: 11px; }
.form-card { width: min(100%, 620px); margin: auto; padding: 26px; background: #fff; border: 1px solid #e6e7e9; border-radius: 20px; }
.eyebrow { color: #066fd1; font-size: 10px; font-weight: 600; letter-spacing: 1px; }
.form-title h1 { font-size: 24px; font-weight: 650; letter-spacing: -.025em; }
.form-title p { margin: 8px 0 0; color: #667382; font-size: 13px; line-height: 1.55; }
.complaint-form { display: flex; flex-direction: column; gap: 17px; margin-top: 23px; }
.field-group { display: flex; flex-direction: column; gap: 7px; }
.field-group label { color: #344054; font-size: 12px; font-weight: 500; }
.field-group label > span:not(.auto-label) { color: #d63939; }
.auto-label { margin-left: 5px; color: #8793a1; font-size: 10px; font-weight: 400; }
.field-group input, .field-group textarea { width: 100%; min-height: 41px; padding: 10px 11px; color: #182433; border: 1px solid #dce0e5; border-radius: 4px; outline: 0; background: #fff; font: inherit; font-size: 13px; resize: vertical; }
.field-group input:focus, .field-group textarea:focus { border-color: #066fd1; box-shadow: 0 0 0 2px rgb(6 111 209 / 10%); }
.field-group input:disabled { color: #667382; background: #f6f8fb; }
.field-error { color: #c02c2c; font-size: 11px; }
.field-hint { color: #8793a1; font-size: 11px; }
.submit-button { min-height: 43px; margin-top: 2px; padding: 0 16px; color: #fff; background: #066fd1; border: 1px solid #066fd1; border-radius: 4px; font: inherit; font-size: 13px; font-weight: 500; cursor: pointer; }
.submit-button:disabled { opacity: .6; cursor: wait; }
.success-notice { display: flex; align-items: center; gap: 9px; margin-top: 18px; padding: 12px 14px; color: #195c2a; background: #eaf7ed; border: 1px solid #ccebd2; border-radius: 5px; font-size: 12px; }
.privacy-note { margin: 18px 0 0; color: #8793a1; font-size: 11px; line-height: 1.5; text-align: center; }
@media (max-width: 600px) { .form-card { padding: 20px 16px; } .form-title h1 { font-size: 21px; } }
</style>

<style scoped src="../../../css/inventory.css"></style>
