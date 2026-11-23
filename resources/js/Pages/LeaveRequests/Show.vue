<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { IconArrowLeft, IconEdit, IconPrinter } from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import LeaveSignaturePad from "@/Components/LeaveSignaturePad.vue";
import LeaveLetterPreview from "@/Components/LeaveLetterPreview.vue";

defineOptions({ layout: AppLayout });

const props = defineProps({
    request: { type: Object, required: true },
    history: { type: Array, required: true },
    success: { type: String, default: "" },
    canEdit: { type: Boolean, default: false },
    canReview: { type: Boolean, default: false },
    isManager: { type: Boolean, default: false },
    readOnly: { type: Boolean, default: false },
});

const reviewForm = useForm({
    action: "",
    notes: "",
    manager_signature: "",
});

function submitReview() {
    reviewForm.post(`/leave-reviews/${props.request.id}`, {
        preserveScroll: true,
    });
}

function statusLabel(status) {
    return {
        diajukan: "Menunggu tinjauan",
        disetujui: "Disetujui",
        ditolak: "Ditolak",
        perlu_revisi: "Perlu perbaikan",
    }[status] ?? status;
}

function actionLabel(action) {
    return {
        diajukan: "Diajukan",
        diajukan_kembali: "Diperbaiki dan diajukan kembali",
        disetujui: "Disetujui",
        ditolak: "Ditolak",
        perlu_revisi: "Dikembalikan untuk perbaikan",
    }[action] ?? action;
}

</script>

<template>
    <Head :title="`Pengajuan Cuti #${request.id}`" />

    <section class="leave-page">
        <div class="page-heading">
            <div>
                <Link
                    :href="readOnly ? '/leave-administration' : isManager ? '/leave-reviews' : '/leave-requests'"
                    class="back-link"
                >
                    <IconArrowLeft :size="17" /> Kembali
                </Link>
                <p class="eyebrow">Dokumen Kepegawaian / Pengajuan Cuti #{{ request.id }}</p>
                <h1>Detail Pengajuan Cuti</h1>
                <span class="status-pill" :class="`status-${request.status}`">
                    {{ statusLabel(request.status) }}
                </span>
            </div>
            <div class="heading-actions">
                <Link
                    v-if="request.status === 'disetujui'"
                    :href="`/leave-requests/${request.id}/letter`"
                    class="secondary-button"
                >
                    <IconPrinter :size="17" /> Lihat surat
                </Link>
                <Link
                    v-if="canEdit"
                    :href="`/leave-requests/${request.id}/edit`"
                    class="primary-button"
                >
                    <IconEdit :size="17" /> Perbaiki pengajuan
                </Link>
            </div>
        </div>

        <div v-if="success" class="success-note" role="status">
            {{ success }}
        </div>

        <div class="content-grid">
            <section class="preview-section">
                <div class="card-title"><h2>Pratinjau surat</h2></div>
                <LeaveLetterPreview :request="request" />
            </section>
            <aside class="leave-card history-card">
                <div class="card-title"><h2>Riwayat proses</h2></div>
                <ol class="timeline">
                    <li v-for="(item, index) in history" :key="`${item.action}-${index}`">
                        <strong>{{ actionLabel(item.action) }}</strong>
                        <span>{{ item.actor_name }} · {{ item.created_at }}</span>
                        <p v-if="item.notes">{{ item.notes }}</p>
                    </li>
                </ol>
            </aside>
        </div>

        <section v-if="canReview" class="leave-card review-card">
            <div class="card-title">
                <h2>Tinjauan manajer</h2>
                <span>Pilih aksi dan sertakan catatan keputusan.</span>
            </div>
            <form class="review-form" @submit.prevent="submitReview">
                <label class="field">
                    <span>Aksi</span>
                    <select v-model="reviewForm.action" required>
                        <option value="" disabled>Pilih aksi</option>
                        <option value="disetujui">Disetujui</option>
                        <option value="ditolak">Ditolak</option>
                        <option value="perlu_revisi">Kembalikan untuk perbaikan</option>
                    </select>
                    <small v-if="reviewForm.errors.action">{{ reviewForm.errors.action }}</small>
                </label>
                <label class="field">
                    <span>Catatan peninjauan</span>
                    <textarea v-model="reviewForm.notes" rows="3" maxlength="2000" required />
                    <small v-if="reviewForm.errors.notes">{{ reviewForm.errors.notes }}</small>
                </label>
                <div v-if="reviewForm.action === 'disetujui'" class="form-section">
                    <p class="section-hint">Tanda tangan manajer merekam persetujuan internal.</p>
                    <LeaveSignaturePad
                        v-model="reviewForm.manager_signature"
                        label="Tanda tangan manajer"
                        :error="reviewForm.errors.manager_signature"
                    />
                </div>
                <div class="review-actions">
                    <Link href="/leave-reviews" class="secondary-button">
                        Batal
                    </Link>
                    <button type="submit" class="primary-button" :disabled="reviewForm.processing">
                        {{ reviewForm.processing ? "Mengirim..." : "Kirim keputusan" }}
                    </button>
                </div>
            </form>
        </section>
    </section>
</template>

<style scoped>
.leave-page { display: grid; gap: 20px; color: #182433; }
.page-heading { display: flex; justify-content: space-between; gap: 18px; }
.page-heading h1 { margin: 5px 0 9px; font-size: 25px; }
.eyebrow { margin: 15px 0 0; color: #667382; font-size: 12px; }
.back-link { display: inline-flex; align-items: center; gap: 6px; color: #536273; text-decoration: none; }
.heading-actions { display: flex; align-items: center; gap: 8px; }
.leave-card { min-width: 0; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.content-grid { display: grid; grid-template-columns: minmax(0, 1fr); align-items: start; gap: 18px; }
.preview-section { min-width: 0; overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.card-title { display: flex; align-items: center; gap: 10px; padding: 17px 20px; border-bottom: 1px solid #edf0f2; }
.card-title h2 { margin: 0; font-size: 16px; }
.card-title span { margin-left: auto; color: #667382; font-size: 12px; }
.success-note { padding: 14px 18px; color: #087443; background: #e2f6ec; border: 1px solid #b9e8d0; border-radius: 6px; }
.success-note { color: #087443; background: #e2f6ec; border: 1px solid #b9e8d0; border-radius: 6px; }
.history-card { overflow: hidden; }
.timeline { display: grid; gap: 0; padding: 20px 20px 20px 37px; margin: 0; }
.timeline li { padding: 0 0 19px 4px; color: #066fd1; }
.timeline li:last-child { padding-bottom: 0; }
.timeline strong, .timeline span { display: block; color: #263445; }
.timeline span { margin-top: 5px; color: #667382; font-size: 11px; }
.timeline p { margin: 7px 0 0; color: #667382; font-size: 12px; white-space: pre-wrap; }
.status-pill { display: inline-block; padding: 4px 9px; color: #475569; background: #f1f5f9; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-diajukan { color: #8a5700; background: #fff4d6; }
.status-disetujui { color: #087443; background: #e2f6ec; }
.status-ditolak { color: #a62232; background: #ffe8eb; }
.status-perlu_revisi { color: #9a4f00; background: #fff0dc; }
.primary-button, .secondary-button, .danger-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 8px 12px; border-radius: 5px; font: inherit; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; }
.primary-button { color: #fff; background: #066fd1; border: 1px solid #066fd1; }
.secondary-button { color: #354252; background: #fff; border: 1px solid #cbd4de; }
.primary-button:disabled, .secondary-button:disabled { opacity: .6; cursor: wait; }
.review-card { padding-bottom: 18px; }
.review-form { padding: 18px 20px 0; }
.field { display: grid; gap: 6px; margin-bottom: 15px; color: #354252; font-size: 13px; font-weight: 600; }
.field textarea, .field select { width: 100%; padding: 10px; border: 1px solid #cbd4de; border-radius: 5px; font: inherit; }
.field small { color: #be123c; font-weight: 400; }
.form-section { margin-top: 18px; }
.section-hint { color: #667382; font-size: 12px; }
.review-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 18px; }
@media (max-width: 650px) { .page-heading { flex-direction: column; } .heading-actions { align-items: flex-start; flex-wrap: wrap; } .review-actions { flex-direction: column-reverse; } }
</style>
