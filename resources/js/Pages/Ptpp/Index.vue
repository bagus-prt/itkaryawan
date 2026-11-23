<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { IconClipboardCheck, IconFilePlus } from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import TablePagination from "@/Components/TablePagination.vue";

defineOptions({ layout: AppLayout });

defineProps({
    requests: { type: Object, required: true },
    pendingCount: { type: Number, default: 0 },
    canCreate: { type: Boolean, default: false },
    isManager: { type: Boolean, default: false },
});

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
</script>

<template>
    <Head title="PTPP" />

    <section class="ptpp-page">
        <header class="page-heading">
            <div>
                <p class="eyebrow">Dokumen Kepegawaian</p>
                <h1>PTPP</h1>
                <p>Pantau pengajuan dan progres PTPP.</p>
            </div>
            <div class="heading-actions">
                <span v-if="isManager" class="pending-count">
                    {{ pendingCount }} menunggu persetujuan
                </span>
                <Link v-if="canCreate" href="/ptpp/create" class="primary-button">
                    <IconFilePlus :size="18" />
                    Ajukan PTPP
                </Link>
            </div>
        </header>

        <section class="request-card">
            <div class="card-heading">
                <IconClipboardCheck :size="20" />
                <h2>Daftar PTPP</h2>
                <span>{{ requests.total }} surat</span>
            </div>

            <div v-if="requests.data.length" class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nomor surat</th>
                            <th>Pengaju dan penerima</th>
                            <th>Waktu pengajuan</th>
                            <th>Status persetujuan</th>
                            <th>Status pekerjaan</th>
                            <th><span class="sr-only">Detail</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in requests.data" :key="item.id">
                            <td data-label="Nomor surat">
                                <Link :href="`/ptpp/${item.id}`" class="request-link">
                                    {{ item.ptpp_number }}
                                </Link>
                            </td>
                            <td data-label="Pengaju dan penerima">
                                <strong>{{ item.submitter_name }}</strong>
                                <span class="secondary-text">Tujuan: {{ item.recipient_name }}</span>
                            </td>
                            <td data-label="Waktu pengajuan">{{ item.created_at }}</td>
                            <td data-label="Status persetujuan">
                                <span class="status-pill" :class="`approval-${item.approval_status}`">
                                    {{ approvalLabel(item.approval_status) }}
                                </span>
                            </td>
                            <td data-label="Status pekerjaan">
                                <span class="status-pill" :class="`work-${item.work_status}`">
                                    {{ workLabel(item.work_status) }}
                                </span>
                            </td>
                            <td class="action-cell">
                                <Link :href="`/ptpp/${item.id}`" class="detail-link">Lihat surat</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="empty-state">
                <p>Belum ada PTPP yang dapat ditampilkan.</p>
                <Link v-if="canCreate" href="/ptpp/create">Buat pengajuan PTPP</Link>
            </div>

            <TablePagination v-if="requests.total > 0" :pagination="requests" label="surat PTPP" />
        </section>
    </section>
</template>

<style scoped>
.ptpp-page { display: grid; gap: 22px; color: #182433; }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.page-heading h1 { margin: 4px 0 6px; font-size: 25px; }
.page-heading p { margin: 0; color: #667382; }
.eyebrow { color: #667382; font-size: 12px; }
.heading-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.primary-button { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; color: #fff; background: #066fd1; border-radius: 5px; font-weight: 600; text-decoration: none; }
.pending-count { padding: 8px 11px; color: #8a5700; background: #fff4d6; border-radius: 999px; font-size: 12px; font-weight: 600; }
.request-card { overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.card-heading { display: flex; align-items: center; gap: 10px; padding: 18px 20px; border-bottom: 1px solid #e6e7e9; }
.card-heading h2 { margin: 0; font-size: 16px; }
.card-heading > span { margin-left: auto; color: #667382; font-size: 12px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th { padding: 12px 15px; color: #667382; background: #f8fafc; font-size: 11px; font-weight: 600; white-space: nowrap; }
td { padding: 14px 15px; border-top: 1px solid #edf0f2; font-size: 12px; vertical-align: middle; }
td strong, .secondary-text { display: block; }
.secondary-text { margin-top: 5px; color: #667382; }
.request-link, .detail-link { color: #066fd1; font-weight: 600; text-decoration: none; }
.request-link:hover, .detail-link:hover { text-decoration: underline; }
.status-pill { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.approval-menunggu_persetujuan { color: #8a5700; background: #fff4d6; }
.approval-disetujui { color: #087443; background: #e2f6ec; }
.approval-perlu_revisi { color: #9a4f00; background: #fff0dc; }
.work-menunggu_diterima { color: #475569; background: #f1f5f9; }
.work-dalam_pengerjaan { color: #0756a5; background: #e8f2fc; }
.work-selesai { color: #087443; background: #e2f6ec; }
.action-cell { text-align: right; white-space: nowrap; }
.empty-state { padding: 36px 20px; text-align: center; }
.empty-state p { margin: 0 0 8px; color: #667382; }
.empty-state a { color: #066fd1; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 760px) {
    .page-heading { align-items: flex-start; flex-direction: column; }
    .heading-actions { justify-content: space-between; width: 100%; }
    thead { display: none; }
    table, tbody, tr, td { display: block; width: 100%; }
    tr { padding: 8px 14px; border-top: 1px solid #edf0f2; }
    td { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border: 0; text-align: right; }
    td::before { content: attr(data-label); color: #667382; font-size: 11px; text-align: left; }
    .action-cell { justify-content: flex-end; }
}
</style>
