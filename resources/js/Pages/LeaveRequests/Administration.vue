<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import TablePagination from "@/Components/TablePagination.vue";
import LeaveRequestFilters from "@/Components/LeaveRequestFilters.vue";

defineOptions({ layout: AppLayout });

const props = defineProps({
    requests: { type: Object, required: true },
    filters: { type: Object, required: true },
    periodSummary: { type: Object, required: true },
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
    <Head title="Data Cuti" />

    <section class="leave-page">
        <header class="page-heading">
            <div>
                <p class="eyebrow">Dokumen Kepegawaian</p>
                <h1>Data Pengajuan Cuti</h1>
            </div>
        </header>

        <section class="summary-grid" aria-label="Ringkasan pengajuan cuti">
            <article class="summary-card">
                <span>Pengajuan masuk</span>
                <strong>{{ periodSummary.submitted }}</strong>
                <small>{{ periodSummary.period }}</small>
            </article>
            <article class="summary-card">
                <span>Surat ditindaklanjuti</span>
                <strong>{{ periodSummary.processed }}</strong>
                <small>{{ periodSummary.period }}</small>
            </article>
        </section>

        <section class="leave-card">
            <LeaveRequestFilters
                :filters="filters"
                url="/leave-administration"
                :per-page="requests.per_page"
            />
            <div v-if="requests.data.length" class="request-list">
                <article v-for="item in requests.data" :key="item.id" class="request-row">
                    <div class="request-main">
                        <Link :href="`/leave-requests/${item.id}`" class="request-link">
                            {{ item.employee_name }} — {{ item.department }} · {{ item.start_date }} sampai {{ item.end_date }}
                        </Link>
                        <span>Diajukan {{ item.created_at }}</span>
                    </div>
                    <span class="status-pill" :class="`status-${item.status}`">{{ statusLabel(item.status) }}</span>
                </article>
            </div>
            <div v-else class="empty-state">Tidak ada pengajuan yang cocok dengan pencarian atau filter.</div>
            <TablePagination v-if="requests.total > 0" :pagination="requests" label="pengajuan" />
        </section>
    </section>
</template>

<style scoped>
.leave-page { display: grid; gap: 22px; color: #182433; }
.page-heading h1 { margin: 4px 0 6px; font-size: 25px; }
.page-heading p { margin: 0; color: #667382; }
.eyebrow { font-size: 12px; }
.summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.summary-card { display: grid; gap: 8px; padding: 18px 20px; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.summary-card span { color: #667382; font-size: 13px; }
.summary-card strong { color: #182433; font-size: 28px; line-height: 1; }
.summary-card small { color: #667382; font-size: 11px; }
.leave-card { overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.request-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 20px; border-bottom: 1px solid #edf0f2; }
.request-main { display: grid; gap: 7px; }
.request-main span { color: #667382; font-size: 12px; }
.request-link { color: #066fd1; font-weight: 600; text-decoration: none; }
.request-link:hover { text-decoration: underline; }
.status-pill { width: fit-content; padding: 4px 9px; color: #475569; background: #f1f5f9; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-diajukan { color: #8a5700; background: #fff4d6; }
.status-disetujui { color: #087443; background: #e2f6ec; }
.status-ditolak { color: #a62232; background: #ffe8eb; }
.status-perlu_revisi { color: #9a4f00; background: #fff0dc; }
.empty-state { padding: 32px; color: #667382; text-align: center; }
@media (max-width: 600px) { .summary-grid { grid-template-columns: 1fr; } .request-row { align-items: flex-start; flex-direction: column; } }
</style>
