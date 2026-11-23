<script setup>
import { onBeforeUnmount, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { IconSearch, IconX } from "@tabler/icons-vue";

const props = defineProps({
    filters: { type: Object, required: true },
    url: { type: String, required: true },
    perPage: { type: Number, required: true },
});

const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");
const month = ref(props.filters.month ?? "");
const year = ref(props.filters.year ?? "");
let searchTimeout;

watch([search, status, month, year], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

onBeforeUnmount(() => clearTimeout(searchTimeout));

function applyFilters() {
    router.get(props.url, {
        search: search.value || undefined,
        status: status.value || undefined,
        month: month.value || undefined,
        year: year.value || undefined,
        per_page: props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters() {
    search.value = "";
    status.value = "";
    month.value = "";
    year.value = "";
}
</script>

<template>
    <div class="filter-bar">
        <label class="search-field">
            <IconSearch :size="18" />
            <input
                v-model="search"
                type="search"
                placeholder="Cari nama, bagian, atau tujuan..."
                aria-label="Cari pengajuan cuti"
            />
            <button v-if="search" type="button" aria-label="Hapus pencarian" @click="search = ''">
                <IconX :size="16" />
            </button>
        </label>

        <select v-model="status" class="filter-select" aria-label="Filter status">
            <option value="">Semua status</option>
            <option value="diajukan">Menunggu tinjauan</option>
            <option value="disetujui">Disetujui</option>
            <option value="ditolak">Ditolak</option>
            <option value="perlu_revisi">Dikembalikan</option>
        </select>

        <select v-model="month" class="filter-select" aria-label="Filter bulan pengajuan">
            <option value="">Semua bulan</option>
            <option v-for="(label, index) in ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']" :key="label" :value="index + 1">
                {{ label }}
            </option>
        </select>

        <label class="year-filter">
            <span>Tahun</span>
            <input v-model="year" type="number" min="2000" max="2100" placeholder="Semua" aria-label="Filter tahun pengajuan" />
        </label>

        <button
            v-if="search || status || month || year"
            class="clear-filter"
            type="button"
            @click="clearFilters"
        >
            Hapus filter
        </button>
    </div>
</template>

<style scoped>
.filter-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #edf0f2; }
.search-field { display: flex; flex: 1 1 250px; align-items: center; gap: 8px; min-width: 220px; padding: 0 10px; color: #667382; border: 1px solid #cbd4de; border-radius: 5px; }
.search-field input { width: 100%; height: 36px; border: 0; outline: 0; font: inherit; }
.search-field button { display: grid; place-items: center; padding: 3px; color: #667382; background: transparent; border: 0; cursor: pointer; }
.filter-select, .year-filter input { height: 38px; padding: 0 10px; color: #354252; background: #fff; border: 1px solid #cbd4de; border-radius: 5px; font: inherit; }
.year-filter { display: flex; align-items: center; gap: 7px; color: #667382; font-size: 12px; }
.year-filter input { width: 90px; }
.clear-filter { padding: 8px 10px; color: #066fd1; background: transparent; border: 0; cursor: pointer; font: inherit; font-size: 12px; }
@media (max-width: 650px) { .filter-bar { align-items: stretch; flex-direction: column; } .search-field { flex-basis: auto; } }
</style>
