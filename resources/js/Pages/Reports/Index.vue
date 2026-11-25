<script setup>
import { reactive, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { IconPrinter, IconDownload } from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import TablePagination from "@/Components/TablePagination.vue";

defineOptions({ layout: AppLayout });

const props = defineProps({
    reports: Object,
    report: String,
    title: String,
    period: String,
    columns: Object,
    fields: Object,
    filters: [Array, Object],
    sort: String,
    direction: String,
    rows: Object,
    printUrl: String,
    csvUrl: String,
});

const page = usePage();
const form = reactive({});

watch(() => props.filters, (filters) => {
    for (const key of Object.keys(form)) {
        delete form[key];
    }
    Object.assign(form, filters);
}, { immediate: true });

function applyFilters(filters, sort = props.sort, direction = props.direction) {
    router.get("/reports", {
        report: props.report,
        ...filters,
        sort,
        direction,
        per_page: props.rows.per_page,
    }, { preserveScroll: true });
}

function changeSort(column) {
    applyFilters(props.filters, column, props.sort === column && props.direction === "asc" ? "desc" : "asc");
}
</script>

<template>
    <Head title="Laporan" />
    <section class="reports">
        <header class="heading">
            <div>
                <h1>Laporan</h1>
                <p>{{ title }} · {{ period }}</p>
            </div>
            <div class="actions">
                <a class="button" :href="csvUrl"><IconDownload :size="17" /> Ekspor CSV</a>
                <a class="button primary" :href="printUrl" target="_blank" rel="noopener"><IconPrinter :size="17" /> Pratinjau Cetak</a>
            </div>
        </header>

        <nav class="tabs" aria-label="Jenis laporan">
            <Link
                v-for="(label, key) in reports"
                :key="key"
                href="/reports"
                :data="{ report: key }"
                :aria-current="key === report ? 'page' : undefined"
            >
                {{ label }}
            </Link>
        </nav>

        <form class="filters panel" @submit.prevent="applyFilters(form)">
            <div class="filter-fields">
                <label class="search">
                    <span>Pencarian</span>
                    <input v-model="form.search" type="search" maxlength="100" placeholder="Cari di laporan ini" />
                </label>
                <label v-for="(field, key) in fields" :key="key">
                    <span>{{ field.label }}</span>
                    <select v-if="field.type === 'select'" :value="form[key] ?? (key === 'date_basis' ? 'submitted' : '')" @change="form[key] = $event.target.value">
                        <option v-if="key !== 'date_basis'" value="">Semua</option>
                        <option v-for="(label, value) in field.options" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                    <input v-else v-model="form[key]" :type="field.type" :min="key === 'end' ? form.start : undefined" />
                    <small v-if="page.props.errors?.[key]" class="field-error">{{ page.props.errors[key] }}</small>
                </label>
            </div>
            <small v-if="page.props.errors?.search" class="field-error">{{ page.props.errors.search }}</small>
            <div class="actions">
                <button class="button primary" type="submit">Terapkan filter</button>
                <button class="button" type="button" @click="applyFilters({})">Reset</button>
            </div>
        </form>

        <section class="panel results" aria-label="Hasil laporan">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th
                                v-for="(label, key) in columns"
                                :key="key"
                                scope="col"
                                :aria-sort="sort === key ? (direction === 'asc' ? 'ascending' : 'descending') : undefined"
                            >
                                <button type="button" @click="changeSort(key)">
                                    {{ label }} <span v-if="sort === key" aria-hidden="true">{{ direction === 'asc' ? '↑' : '↓' }}</span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.data.length">
                            <td :colspan="Object.keys(columns).length" class="empty">Belum ada data yang sesuai dengan filter.</td>
                        </tr>
                        <tr v-for="(row, index) in rows.data" :key="index">
                            <td v-for="(_, key) in columns" :key="key">{{ row[key] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <TablePagination :pagination="rows" label="baris" />
        </section>
    </section>
</template>

<style scoped>
.reports { display: grid; gap: 18px; color: var(--app-text); font-size: 14px; }
.heading, .actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.heading { justify-content: space-between; gap: 14px; }
.heading h1 { font-size: 24px; font-weight: 600; }
.heading p { color: var(--app-muted); font-size: 13px; }
.panel { min-width: 0; background: var(--app-surface); border: 1px solid var(--app-border); border-radius: 8px; }
.button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border: 1px solid var(--app-border);
    border-radius: 4px;
    background: var(--app-surface);
    font-weight: 500;
    cursor: pointer;
}
.button:hover { border-color: var(--app-primary); }
.button.primary { background: var(--app-primary); border-color: var(--app-primary); color: #fff; }
.button.primary:hover { background: #045db0; border-color: #045db0; }
.tabs { display: flex; gap: 8px; overflow-x: auto; border-bottom: 1px solid var(--app-border); }
.tabs a { flex: none; padding: 9px 10px; border-bottom: 2px solid transparent; }
.tabs [aria-current="page"] { color: var(--app-primary); border-color: var(--app-primary); font-weight: 600; }
.filters { padding: 16px; }
.filter-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 155px), 1fr)); gap: 12px; }
.filter-fields label { display: grid; align-content: start; gap: 5px; font-size: 13px; }
.filter-fields input, .filter-fields select {
    width: 100%;
    min-width: 0;
    padding: 7px 9px;
    border: 1px solid var(--app-border);
    border-radius: 4px;
    background: var(--app-surface);
}
.filters .actions { margin-top: 14px; }
.field-error { color: #b22727; }
.results { overflow: hidden; }
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th, td { padding: 10px 13px; border-bottom: 1px solid var(--app-border); vertical-align: top; }
th { color: var(--app-muted); background: #f6f8fb; font-size: 12px; white-space: nowrap; }
th button { font-weight: 600; text-align: left; cursor: pointer; }
th[aria-sort] button { color: var(--app-primary); }
td { font-size: 13px; overflow-wrap: anywhere; }
.empty { padding: 24px; text-align: center; color: var(--app-muted); }
</style>
