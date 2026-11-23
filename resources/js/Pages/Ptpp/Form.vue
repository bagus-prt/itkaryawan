<script setup>
import { useForm, Head, Link } from "@inertiajs/vue3";
import { IconArrowLeft, IconDeviceFloppy } from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import LeaveSignaturePad from "@/Components/LeaveSignaturePad.vue";

defineOptions({ layout: AppLayout });

const props = defineProps({
    ptppRequest: { type: Object, default: null },
    action: { type: String, required: true },
    recipients: { type: Array, required: true },
    sourceCategories: { type: Object, required: true },
    today: { type: String, required: true },
});

const form = useForm({
    ptpp_number: props.ptppRequest?.ptpp_number ?? "",
    recipient_id: props.ptppRequest?.recipient_id ?? "",
    to_function: props.ptppRequest?.to_function ?? "",
    from_function: props.ptppRequest?.from_function ?? "",
    sender_signature_name: props.ptppRequest?.sender_signature_name ?? "",
    sender_short_name: props.ptppRequest?.sender_short_name ?? "",
    sender_signature: "",
    location: props.ptppRequest?.location ?? "",
    source_category: props.ptppRequest?.source_category ?? "",
    description: props.ptppRequest?.description ?? "",
    illustration: null,
});

function selectIllustration(event) {
    form.illustration = event.target.files?.[0] ?? null;
}

function submit() {
    const options = { forceFormData: true, preserveScroll: true };

    if (props.ptppRequest) {
        form.put(props.action, options);
    } else {
        form.post(props.action, options);
    }
}
</script>

<template>
    <Head :title="ptppRequest ? 'Perbaiki PTPP' : 'Ajukan PTPP'" />

    <section class="ptpp-page">
        <Link href="/ptpp" class="back-link">
            <IconArrowLeft :size="17" /> Kembali ke daftar PTPP
        </Link>

        <header class="page-heading">
            <div>
                <p class="eyebrow">Dokumen Kepegawaian / Formulir</p>
                <h1>{{ ptppRequest ? "Perbaiki PTPP" : "Ajukan PTPP" }}</h1>
                <p>Isi data surat, pilih penerima, lalu kirim untuk persetujuan manager.</p>
            </div>
            <span class="date-note">Tanggal surat otomatis: {{ today }}</span>
        </header>

        <div v-if="ptppRequest?.review_notes" class="revision-note">
            <strong>Catatan manager</strong>
            <p>{{ ptppRequest.review_notes }}</p>
        </div>

        <form class="form-card" novalidate @submit.prevent="submit">
            <div v-if="Object.keys(form.errors).length" class="validation-summary" role="alert">
                <strong>Pengajuan belum terkirim. Periksa kembali isian berikut:</strong>
                <ul>
                    <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                </ul>
            </div>
            <section class="form-section">
                <h2>Identitas surat</h2>
                <div class="field-grid">
                    <label class="field">
                        <span>Nomor surat PTPP</span>
                        <input v-model="form.ptpp_number" maxlength="100" placeholder="PTPP-016/PPNLM0204/2026-55" required />
                        <small v-if="form.errors.ptpp_number">{{ form.errors.ptpp_number }}</small>
                    </label>
                    <label class="field">
                        <span>Area / Lokasi temuan</span>
                        <input v-model="form.location" maxlength="200" required />
                        <small v-if="form.errors.location">{{ form.errors.location }}</small>
                    </label>
                    <label class="field">
                        <span>Sumber ketidaksesuaian</span>
                        <select v-model="form.source_category" required>
                            <option value="" disabled>Pilih sumber</option>
                            <option v-for="(label, value) in sourceCategories" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <small v-if="form.errors.source_category">{{ form.errors.source_category }}</small>
                    </label>
                    <fieldset class="field-pair">
                        <legend>Kepada / Fungsi</legend>
                        <div class="field-grid">
                            <label class="field">
                                <span>Nama penerima</span>
                                <select v-model="form.recipient_id" required>
                                    <option value="" disabled>Pilih user tujuan</option>
                                    <option v-for="recipient in recipients" :key="recipient.id" :value="recipient.id">
                                        {{ recipient.name }}
                                    </option>
                                </select>
                                <small v-if="form.errors.recipient_id">{{ form.errors.recipient_id }}</small>
                            </label>
                            <label class="field">
                                <span>Fungsi penerima</span>
                                <input v-model="form.to_function" maxlength="200" placeholder="Contoh: MOS" required />
                                <small v-if="form.errors.to_function">{{ form.errors.to_function }}</small>
                            </label>
                        </div>
                    </fieldset>
                    <fieldset class="field-pair">
                        <legend>Dari / Fungsi</legend>
                        <div class="field-grid">
                            <label class="field">
                                <span>Nama pemohon</span>
                                <input v-model="form.sender_signature_name" maxlength="255" required />
                                <small v-if="form.errors.sender_signature_name">{{ form.errors.sender_signature_name }}</small>
                            </label>
                            <label class="field">
                                <span>Fungsi pemohon</span>
                                <input v-model="form.from_function" maxlength="200" placeholder="Contoh: Distribusi" required />
                                <small v-if="form.errors.from_function">{{ form.errors.from_function }}</small>
                            </label>
                        </div>
                    </fieldset>
                    <label class="field">
                        <span>Nama pendek di kotak kuning</span>
                        <input v-model="form.sender_short_name" maxlength="80" placeholder="Contoh: Eddy" required />
                        <small v-if="form.errors.sender_short_name">{{ form.errors.sender_short_name }}</small>
                    </label>
                </div>
            </section>

            <section class="form-section">
                <h2>Ketidaksesuaian atau potensi yang ditemukan</h2>
                <label class="field">
                    <span>Uraian temuan</span>
                    <textarea v-model="form.description" rows="6" maxlength="5000" required />
                    <small v-if="form.errors.description">{{ form.errors.description }}</small>
                </label>
            </section>

            <section class="form-section">
                <h2>Ilustrasi / gambar <span>(jika ada)</span></h2>
                <label class="field">
                    <span>Unggah gambar (JPG, PNG, atau WebP, maksimal 5 MB)</span>
                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="selectIllustration" />
                    <small v-if="form.errors.illustration">{{ form.errors.illustration }}</small>
                </label>
                <img
                    v-if="ptppRequest?.illustration_url && !form.illustration"
                    :src="ptppRequest.illustration_url"
                    class="existing-image"
                    alt="Ilustrasi PTPP saat ini"
                />
            </section>

            <section class="form-section">
                <h2>Tanda tangan pemohon</h2>
                <p class="section-hint">Bubuhkan tanda tangan digital sebelum mengirim surat untuk persetujuan manager.</p>
                <LeaveSignaturePad
                    v-model="form.sender_signature"
                    label="Tanda tangan pemohon"
                    :error="form.errors.sender_signature"
                    :canvas-width="720"
                    :canvas-height="540"
                    :max-display-width="420"
                />
            </section>

            <footer class="form-actions">
                <Link href="/ptpp" class="secondary-button">Batal</Link>
                <button type="submit" class="primary-button" :disabled="form.processing">
                    <IconDeviceFloppy :size="17" />
                    {{ form.processing ? "Mengirim..." : ptppRequest ? "Kirim perbaikan" : "Ajukan PTPP" }}
                </button>
            </footer>
        </form>
    </section>
</template>

<style scoped>
.ptpp-page { display: grid; gap: 20px; color: #182433; }
.back-link { display: inline-flex; align-items: center; gap: 6px; width: fit-content; color: #536273; text-decoration: none; }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.page-heading h1 { margin: 4px 0 6px; font-size: 25px; }
.page-heading p { margin: 0; color: #667382; }
.eyebrow { color: #667382; font-size: 12px; }
.date-note { padding: 9px 12px; color: #475569; background: #fff; border: 1px solid #e6e7e9; border-radius: 6px; font-size: 12px; }
.form-card { display: grid; gap: 22px; padding: 22px; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.form-section { display: grid; gap: 14px; }
.form-section + .form-section { padding-top: 20px; border-top: 1px solid #edf0f2; }
.form-section h2 { margin: 0; font-size: 16px; }
.form-section h2 span { color: #667382; font-size: 12px; font-weight: 400; }
.field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; }
.field-pair { min-width: 0; padding: 14px; margin: 0; border: 1px solid #dce3e9; border-radius: 6px; grid-column: 1 / -1; }
.field-pair legend { padding: 0 6px; color: #263445; font-size: 13px; font-weight: 700; }
.field { display: grid; gap: 6px; color: #354252; font-size: 13px; font-weight: 600; }
.field input, .field select, .field textarea { width: 100%; min-height: 40px; padding: 9px 10px; color: #182433; background: #fff; border: 1px solid #cbd4de; border-radius: 5px; font: inherit; font-weight: 400; }
.field textarea { resize: vertical; }
.field input:focus, .field select:focus, .field textarea:focus { outline: 2px solid #8fc5f5; outline-offset: 1px; }
.field small { color: #be123c; font-weight: 400; }
.section-hint { margin: -5px 0 0; color: #667382; font-size: 13px; }
.existing-image { width: min(100%, 360px); max-height: 240px; object-fit: contain; object-position: left; border: 1px solid #e6e7e9; border-radius: 5px; }
.revision-note { padding: 14px 18px; color: #754200; background: #fff4d6; border: 1px solid #f0dda8; border-radius: 6px; }
.revision-note p { margin: 6px 0 0; white-space: pre-wrap; }
.validation-summary { padding: 14px 18px; color: #8f1d2c; background: #fff0f1; border: 1px solid #f3c4ca; border-radius: 6px; }
.validation-summary ul { display: grid; gap: 4px; padding-left: 20px; margin: 8px 0 0; }
.form-actions { display: flex; justify-content: flex-end; gap: 8px; padding-top: 17px; border-top: 1px solid #edf0f2; }
.primary-button, .secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 8px 13px; border: 1px solid #cbd4de; border-radius: 5px; font: inherit; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; }
.primary-button { color: #fff; background: #066fd1; border-color: #066fd1; }
.secondary-button { color: #354252; background: #fff; }
.primary-button:disabled { opacity: .6; cursor: wait; }
@media (max-width: 650px) {
    .page-heading { align-items: flex-start; flex-direction: column; }
    .field-grid { grid-template-columns: 1fr; }
    .field-pair { padding: 12px; }
    .form-card { padding: 16px; }
    .form-actions { flex-direction: column-reverse; }
}
</style>
