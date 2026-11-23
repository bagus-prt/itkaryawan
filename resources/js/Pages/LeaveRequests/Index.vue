<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { IconDownload, IconFilePlus, IconHistory } from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import TablePagination from "@/Components/TablePagination.vue";

defineOptions({ layout: AppLayout });

defineProps({
    requests: { type: Object, required: true },
});

function statusLabel(status) {
    return {
        diajukan: "Menunggu tinjauan",
        disetujui: "Disetujui",
        ditolak: "Ditolak",
        perlu_revisi: "Perlu perbaikan",
    }[status] ?? status;
}
</script>

<template>
    <Head title="Riwayat Cuti" />

    <section class="leave-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Dokumen Kepegawaian</p>
                <h1>Riwayat Pengajuan Cuti</h1>
                <p>Lihat status dan catatan untuk setiap pengajuan cuti Anda.</p>
            </div>
            <Link href="/leave-requests/create" class="primary-button">
                <IconFilePlus :size="18" />
                Ajukan cuti
            </Link>
        </div>

        <section class="leave-card">
            <div class="card-title">
                <IconHistory :size="20" />
                <h2>Pengajuan saya</h2>
                <span>{{ requests.total }} pengajuan</span>
            </div>
            <div v-if="requests.data.length" class="request-list">
                <article v-for="item in requests.data" :key="item.id" class="request-row">
                    <div class="request-main">
                        <Link :href="`/leave-requests/${item.id}`" class="request-link">
                            Cuti {{ item.start_date }} – {{ item.end_date }}
                        </Link>
                        <span>Diajukan {{ item.created_at }}</span>
                    </div>
                    <div class="request-side">
                        <span class="status-pill" :class="`status-${item.status}`">
                            {{ statusLabel(item.status) }}
                        </span>
                        <p v-if="item.review_notes">{{ item.review_notes }}</p>
                        <Link
                            v-if="item.status === 'disetujui'"
                            :href="`/leave-requests/${item.id}/letter`"
                            class="download-link"
                        >
                            <IconDownload :size="16" />
                            Unduh / cetak surat
                        </Link>
                    </div>
                </article>
            </div>
            <div v-else class="empty-state">
                <p>Belum ada pengajuan cuti.</p>
                <Link href="/leave-requests/create">Buat pengajuan pertama</Link>
            </div>
            <TablePagination v-if="requests.total > 0" :pagination="requests" label="pengajuan" />
        </section>
    </section>
</template>

<style scoped>
.leave-page { display: grid; gap: 22px; color: #182433; }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.page-heading h1 { margin: 4px 0 6px; font-size: 25px; }
.page-heading p { margin: 0; color: #667382; }
.eyebrow { color: #667382; font-size: 12px; }
.primary-button { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; color: #fff; background: #066fd1; border-radius: 5px; font-weight: 600; text-decoration: none; }
.leave-card { overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.card-title { display: flex; align-items: center; gap: 10px; padding: 18px 20px; border-bottom: 1px solid #e6e7e9; }
.card-title h2 { margin: 0; font-size: 16px; }
.card-title span { margin-left: auto; color: #667382; font-size: 12px; }
.request-row { display: flex; justify-content: space-between; gap: 16px; padding: 18px 20px; border-bottom: 1px solid #edf0f2; }
.request-main, .request-side { display: grid; gap: 6px; }
.request-main span, .request-side p { margin: 0; color: #667382; font-size: 12px; }
.download-link { display: inline-flex; align-items: center; gap: 5px; color: #066fd1; font-size: 12px; font-weight: 600; text-decoration: none; }
.download-link:hover { text-decoration: underline; }
.request-link { color: #066fd1; font-weight: 600; text-decoration: none; }
.request-link:hover { text-decoration: underline; }
.status-pill { width: fit-content; padding: 4px 9px; color: #475569; background: #f1f5f9; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-diajukan { color: #8a5700; background: #fff4d6; }
.status-disetujui { color: #087443; background: #e2f6ec; }
.status-ditolak { color: #a62232; background: #ffe8eb; }
.status-perlu_revisi { color: #9a4f00; background: #fff0dc; }
.empty-state { padding: 36px 20px; text-align: center; }
.empty-state p { margin: 0 0 8px; color: #667382; }
.empty-state a { color: #066fd1; }
@media (max-width: 600px) { .page-heading, .request-row { align-items: flex-start; flex-direction: column; } }
</style>
