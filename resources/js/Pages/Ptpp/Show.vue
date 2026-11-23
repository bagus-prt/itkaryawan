<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import {
    IconArrowLeft,
    IconCheck,
    IconEdit,
    IconPrinter,
    IconSend,
} from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import PtppLetterPreview from "@/Components/PtppLetterPreview.vue";
import LeaveSignaturePad from "@/Components/LeaveSignaturePad.vue";

defineOptions({ layout: AppLayout });

const props = defineProps({
    ptppRequest: { type: Object, required: true },
    history: { type: Array, required: true },
    success: { type: String, default: "" },
    backUrl: { type: String, default: "/ptpp" },
    canEdit: { type: Boolean, default: false },
    canReview: { type: Boolean, default: false },
    canAccept: { type: Boolean, default: false },
    canUpdateWork: { type: Boolean, default: false },
    isManager: { type: Boolean, default: false },
    readOnly: { type: Boolean, default: false },
});

const reviewForm = useForm({
    action: "",
    notes: "",
    reviewer_signature: "",
});

const acceptForm = useForm({});

const progressForm = useForm({
    notes: "",
    photo: null,
    finish: false,
});
const photoInput = ref(null);

function submitReview() {
    reviewForm.post(`/ptpp/${props.ptppRequest.id}/review`, { preserveScroll: true });
}

function acceptLetter() {
    acceptForm.patch(`/ptpp/${props.ptppRequest.id}/accept`, { preserveScroll: true });
}

function selectPhoto(event) {
    progressForm.photo = event.target.files?.[0] ?? null;
}

function submitProgress(finish) {
    progressForm.finish = finish;
    progressForm.post(`/ptpp/${props.ptppRequest.id}/updates`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            progressForm.notes = "";
            progressForm.photo = null;
            progressForm.finish = false;
            if (photoInput.value) {
                photoInput.value.value = "";
            }
        },
    });
}

function approvalLabel(status) {
    return {
        menunggu_persetujuan: "Menunggu persetujuan",
        disetujui: "Disetujui",
        perlu_revisi: "Perlu revisi",
    }[status] ?? status;
}

function workLabel(status) {
    return {
        menunggu_diterima: "Menunggu diterima",
        dalam_pengerjaan: "Dalam pengerjaan",
        selesai: "Selesai",
    }[status] ?? status;
}

function actionLabel(action) {
    return {
        diajukan: "PTPP diajukan",
        diajukan_kembali: "Perbaikan diajukan kembali",
        disetujui: "Disetujui manager",
        perlu_revisi: "Dikembalikan untuk perbaikan",
        diterima_penerima: "Surat diterima",
        pekerjaan_selesai: "Pekerjaan ditandai selesai",
    }[action] ?? action;
}
</script>

<template>
    <Head :title="`PTPP ${ptppRequest.ptpp_number}`" />

    <section class="ptpp-page">
        <header class="page-heading">
            <div>
                <Link :href="backUrl" class="back-link">
                    <IconArrowLeft :size="17" /> Kembali ke daftar PTPP
                </Link>
                <p class="eyebrow">Dokumen Kepegawaian / PTPP</p>
                <h1>{{ ptppRequest.ptpp_number }}</h1>
                <div class="status-row">
                    <span class="status-pill" :class="`approval-${ptppRequest.approval_status}`">
                        Persetujuan: {{ approvalLabel(ptppRequest.approval_status) }}
                    </span>
                    <span class="status-pill" :class="`work-${ptppRequest.work_status}`">
                        Pekerjaan: {{ workLabel(ptppRequest.work_status) }}
                    </span>
                </div>
            </div>
            <div class="heading-actions">
                <Link
                    v-if="ptppRequest.approval_status === 'disetujui'"
                    :href="`/ptpp/${ptppRequest.id}/letter`"
                    class="secondary-button"
                >
                    <IconPrinter :size="17" /> Cetak / unduh surat
                </Link>
                <Link v-if="canEdit" :href="`/ptpp/${ptppRequest.id}/edit`" class="primary-button">
                    <IconEdit :size="17" /> Perbaiki pengajuan
                </Link>
            </div>
        </header>

        <div v-if="success" class="success-note" role="status">{{ success }}</div>

        <div class="content-grid">
            <section class="preview-section">
                <div class="card-title">
                    <h2>Pratinjau surat PTPP</h2>
                    <span>Surat resmi · format A4</span>
                </div>
                <PtppLetterPreview :ptpp-request="ptppRequest" />
            </section>

            <aside class="side-column">
                <section class="info-card">
                    <div class="card-title"><h2>Informasi proses</h2></div>
                    <dl class="request-details">
                        <div><dt>Pengaju</dt><dd>{{ ptppRequest.submitter_name }}</dd></div>
                        <div><dt>Waktu pengajuan</dt><dd>{{ ptppRequest.created_at }}</dd></div>
                        <div><dt>Penerima</dt><dd>{{ ptppRequest.recipient_name }}</dd></div>
                        <div><dt>Manager</dt><dd>{{ ptppRequest.manager_name }}</dd></div>
                        <div v-if="ptppRequest.work_received_at"><dt>Diterima</dt><dd>{{ ptppRequest.work_received_at }}</dd></div>
                        <div v-if="ptppRequest.completed_at"><dt>Selesai</dt><dd>{{ ptppRequest.completed_at }}</dd></div>
                    </dl>
                </section>

                <section v-if="ptppRequest.review_notes" class="revision-note">
                    <strong>Catatan manager</strong>
                    <p>{{ ptppRequest.review_notes }}</p>
                </section>

                <section v-if="canReview" class="action-card">
                    <div class="card-title"><h2>Persetujuan manager</h2></div>
                    <form class="action-form" @submit.prevent="submitReview">
                        <label class="field">
                            <span>Keputusan</span>
                            <select v-model="reviewForm.action" required>
                                <option value="" disabled>Pilih keputusan</option>
                                <option value="disetujui">Setujui PTPP</option>
                                <option value="perlu_revisi">Kembalikan untuk revisi</option>
                            </select>
                            <small v-if="reviewForm.errors.action">{{ reviewForm.errors.action }}</small>
                        </label>
                        <label class="field">
                            <span>Catatan keputusan</span>
                            <textarea v-model="reviewForm.notes" rows="3" maxlength="2000" required />
                            <small v-if="reviewForm.errors.notes">{{ reviewForm.errors.notes }}</small>
                        </label>
                        <LeaveSignaturePad
                            v-if="reviewForm.action === 'disetujui'"
                            v-model="reviewForm.reviewer_signature"
                            label="Tanda tangan manager"
                            :error="reviewForm.errors.reviewer_signature"
                            :canvas-width="720"
                            :canvas-height="540"
                            :max-display-width="420"
                        />
                        <button class="primary-button full-button" type="submit" :disabled="reviewForm.processing">
                            <IconCheck :size="17" />
                            {{ reviewForm.processing ? "Mengirim..." : "Kirim keputusan" }}
                        </button>
                    </form>
                </section>

                <section v-if="canAccept" class="action-card accept-card">
                    <div>
                        <h2>Surat sudah disetujui</h2>
                        <p>Terima surat untuk memulai pekerjaan. Pengaju dan manager akan melihat status pengerjaan.</p>
                    </div>
                    <button class="primary-button full-button" type="button" :disabled="acceptForm.processing" @click="acceptLetter">
                        <IconCheck :size="17" />
                        {{ acceptForm.processing ? "Memproses..." : "Terima surat" }}
                    </button>
                </section>

                <section v-if="canUpdateWork" class="action-card">
                    <div class="card-title"><h2>Update pekerjaan</h2></div>
                    <form class="action-form" @submit.prevent="submitProgress(false)">
                        <label class="field">
                            <span>Keterangan progres</span>
                            <textarea v-model="progressForm.notes" rows="4" maxlength="5000" required />
                            <small v-if="progressForm.errors.notes">{{ progressForm.errors.notes }}</small>
                        </label>
                        <label class="field">
                            <span>Foto (opsional, maksimal 5 MB)</span>
                            <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" @change="selectPhoto" />
                            <small v-if="progressForm.errors.photo">{{ progressForm.errors.photo }}</small>
                        </label>
                        <button class="secondary-button full-button" type="submit" :disabled="progressForm.processing">
                            <IconSend :size="16" /> Kirim update
                        </button>
                        <button
                            class="primary-button full-button"
                            type="button"
                            :disabled="progressForm.processing"
                            @click="submitProgress(true)"
                        >
                            <IconCheck :size="17" /> Kirim update dan tandai selesai
                        </button>
                    </form>
                </section>

                <section class="info-card history-card">
                    <div class="card-title"><h2>Riwayat proses</h2></div>
                    <ol v-if="history.length" class="timeline">
                        <li v-for="(item, index) in history" :key="`${item.action}-${index}`">
                            <strong>{{ actionLabel(item.action) }}</strong>
                            <span>{{ item.actor_name }} · {{ item.created_at }}</span>
                            <p v-if="item.notes">{{ item.notes }}</p>
                        </li>
                    </ol>
                    <p v-else class="empty-history">Belum ada riwayat proses.</p>
                </section>
            </aside>
        </div>

        <section class="progress-section">
            <div class="card-title">
                <h2>Riwayat progres pekerjaan</h2>
                <span>{{ ptppRequest.progress_updates.length }} update</span>
            </div>
            <ol v-if="ptppRequest.progress_updates.length" class="progress-list">
                <li v-for="update in ptppRequest.progress_updates" :key="update.id" class="progress-item">
                    <div class="progress-meta">
                        <strong>{{ update.user_name }}</strong>
                        <time>{{ update.recorded_at }}</time>
                    </div>
                    <p>{{ update.notes }}</p>
                    <a
                        v-if="update.photo_url"
                        :href="update.photo_url"
                        class="progress-photo-link"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="`Buka foto progres dari ${update.user_name}`"
                    >
                        <img
                            :src="update.photo_url"
                            :alt="`Foto progres dari ${update.user_name}`"
                            class="progress-photo"
                            loading="lazy"
                        />
                    </a>
                </li>
            </ol>
            <p v-else class="empty-history">Belum ada update progres pekerjaan.</p>
        </section>
    </section>
</template>

<style scoped>
.ptpp-page { display: grid; gap: 20px; color: #182433; }
.page-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
.page-heading h1 { margin: 4px 0 10px; font-size: 25px; }
.eyebrow { margin: 13px 0 0; color: #667382; font-size: 12px; }
.back-link { display: inline-flex; align-items: center; gap: 6px; color: #536273; text-decoration: none; }
.heading-actions, .status-row { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.status-row { margin-top: 9px; }
.status-pill { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.approval-menunggu_persetujuan { color: #8a5700; background: #fff4d6; }
.approval-disetujui { color: #087443; background: #e2f6ec; }
.approval-perlu_revisi { color: #9a4f00; background: #fff0dc; }
.work-menunggu_diterima { color: #475569; background: #f1f5f9; }
.work-dalam_pengerjaan { color: #0756a5; background: #e8f2fc; }
.work-selesai { color: #087443; background: #e2f6ec; }
.content-grid { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(300px, .85fr); align-items: start; gap: 18px; }
.preview-section, .info-card, .action-card { min-width: 0; overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.progress-section { min-width: 0; overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.preview-section { padding-bottom: 12px; }
.card-title { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 15px 17px; border-bottom: 1px solid #edf0f2; }
.card-title h2, .accept-card h2 { margin: 0; font-size: 15px; }
.card-title span { color: #667382; font-size: 11px; }
.side-column { display: grid; gap: 14px; }
.request-details { display: grid; gap: 0; padding: 0 16px; margin: 0; }
.request-details > div { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #edf0f2; font-size: 12px; }
.request-details > div:last-child { border-bottom: 0; }
.request-details dt { color: #667382; }
.request-details dd { margin: 0; text-align: right; font-weight: 600; }
.revision-note, .success-note { padding: 13px 15px; border-radius: 6px; }
.revision-note { color: #754200; background: #fff4d6; border: 1px solid #f0dda8; }
.revision-note p { margin: 6px 0 0; white-space: pre-wrap; }
.success-note { color: #087443; background: #e2f6ec; border: 1px solid #b9e8d0; }
.action-form { display: grid; gap: 13px; padding: 15px; }
.field { display: grid; gap: 6px; color: #354252; font-size: 12px; font-weight: 600; }
.field select, .field textarea, .field input { width: 100%; padding: 9px; color: #182433; background: #fff; border: 1px solid #cbd4de; border-radius: 5px; font: inherit; font-weight: 400; }
.field textarea { resize: vertical; }
.field small { color: #be123c; font-weight: 400; }
.primary-button, .secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 8px 12px; border: 1px solid #cbd4de; border-radius: 5px; font: inherit; font-size: 12px; font-weight: 600; text-decoration: none; cursor: pointer; }
.primary-button { color: #fff; background: #066fd1; border-color: #066fd1; }
.secondary-button { color: #354252; background: #fff; }
.primary-button:disabled, .secondary-button:disabled { opacity: .6; cursor: wait; }
.full-button { width: 100%; }
.accept-card { display: grid; gap: 13px; padding: 16px; }
.accept-card p { margin: 7px 0 0; color: #667382; font-size: 12px; line-height: 1.5; }
.timeline { display: grid; gap: 0; padding: 15px 15px 15px 31px; margin: 0; }
.timeline li { padding: 0 0 17px 4px; color: #066fd1; }
.timeline li:last-child { padding-bottom: 0; }
.timeline strong, .timeline span { display: block; color: #263445; font-size: 12px; }
.timeline span { margin-top: 4px; color: #667382; font-size: 11px; }
.timeline p, .empty-history { margin: 6px 0 0; color: #667382; font-size: 12px; white-space: pre-wrap; }
.empty-history { padding: 0 16px 16px; }
.progress-list { display: grid; padding: 0; margin: 0; list-style: none; }
.progress-item { display: grid; gap: 9px; padding: 16px; border-bottom: 1px solid #edf0f2; }
.progress-item:last-child { border-bottom: 0; }
.progress-meta { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.progress-meta strong { color: #263445; font-size: 13px; }
.progress-meta time { color: #667382; font-size: 11px; }
.progress-item > p { margin: 0; color: #354252; font-size: 13px; line-height: 1.5; white-space: pre-wrap; }
.progress-photo-link { display: block; width: fit-content; max-width: 100%; }
.progress-photo { display: block; width: auto; max-width: 100%; max-height: 480px; object-fit: contain; border: 1px solid #e6e7e9; border-radius: 5px; }
@media (max-width: 1000px) {
    .content-grid { grid-template-columns: minmax(0, 1fr); }
    .side-column { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
    .history-card { grid-column: 1 / -1; }
}
@media (max-width: 650px) {
    .page-heading { flex-direction: column; }
    .heading-actions { width: 100%; }
    .side-column { grid-template-columns: minmax(0, 1fr); }
    .history-card { grid-column: auto; }
    .progress-meta { align-items: flex-start; flex-direction: column; gap: 4px; }
}
</style>
