<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-vue'

const props = defineProps({
    pagination: { type: Object, required: true },
    label: { type: String, default: 'data' },
})

const pageNumbers = computed(() =>
    (props.pagination.links ?? []).filter((link) => /^\d+$/.test(link.label)),
)

function visitPage(url) {
    if (url) {
        router.visit(url, {
            preserveScroll: true,
            preserveState: true,
        })
    }
}

function changePerPage(event) {
    const query = Object.fromEntries(new URLSearchParams(window.location.search))

    delete query.page
    query.per_page = event.target.value

    router.get(window.location.pathname, query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}
</script>

<template>
    <footer class="table-pagination">
        <div class="pagination-summary">
            Menampilkan <strong>{{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }}</strong>
            dari <strong>{{ pagination.total }}</strong> {{ label }}
        </div>

        <div class="pagination-options">
            <label>
                Baris per halaman
                <select
                    :value="pagination.per_page"
                    aria-label="Jumlah data per halaman"
                    @change="changePerPage"
                >
                    <option :value="10">10</option>
                    <option :value="20">20</option>
                    <option :value="50">50</option>
                </select>
            </label>

            <nav class="pagination-controls" aria-label="Navigasi halaman tabel">
                <button
                    class="page-button"
                    type="button"
                    aria-label="Halaman sebelumnya"
                    :disabled="!pagination.prev_page_url"
                    @click="visitPage(pagination.prev_page_url)"
                >
                    <IconChevronLeft :size="17" />
                </button>
                <button
                    v-for="link in pageNumbers"
                    :key="link.label"
                    class="page-button"
                    :class="{ 'page-active': link.active }"
                    type="button"
                    :aria-current="link.active ? 'page' : undefined"
                    :disabled="!link.url"
                    @click="visitPage(link.url)"
                >
                    {{ link.label }}
                </button>
                <button
                    class="page-button"
                    type="button"
                    aria-label="Halaman berikutnya"
                    :disabled="!pagination.next_page_url"
                    @click="visitPage(pagination.next_page_url)"
                >
                    <IconChevronRight :size="17" />
                </button>
            </nav>
        </div>
    </footer>
</template>

<style scoped>
.table-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 20px;
    color: #667382;
    border-top: 1px solid #e6e7e9;
    font-size: 12px;
}

.pagination-summary strong {
    color: #182433;
    font-weight: 500;
}

.pagination-options,
.pagination-options label,
.pagination-controls {
    display: flex;
    align-items: center;
}

.pagination-options {
    gap: 16px;
}

.pagination-options label {
    gap: 8px;
    white-space: nowrap;
}

.pagination-options select {
    height: 32px;
    padding: 0 8px;
    color: #344054;
    border: 1px solid #dce0e5;
    border-radius: 4px;
    background: #fff;
    font: inherit;
}

.pagination-controls {
    gap: 5px;
}

.page-button {
    display: grid;
    min-width: 32px;
    height: 32px;
    place-items: center;
    padding: 0 8px;
    color: #465363;
    border: 1px solid #dce0e5;
    border-radius: 4px;
    background: #fff;
    font: inherit;
    font-size: 12px;
    cursor: pointer;
}

.page-button:hover:not(:disabled) {
    color: #066fd1;
    border-color: #066fd1;
}

.page-button:disabled {
    opacity: .4;
    cursor: not-allowed;
}

.page-button.page-active {
    color: #fff;
    border-color: #066fd1;
    background: #066fd1;
}

@media (max-width: 640px) {
    .table-pagination,
    .pagination-options {
        align-items: flex-start;
        flex-direction: column;
    }

    .table-pagination {
        padding: 14px;
    }
}
</style>
