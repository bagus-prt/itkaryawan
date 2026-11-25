<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    IconTags,
    IconPlus,
    IconSearch,
    IconX,
    IconEdit,
    IconCheck,
    IconBan,
    IconChevronLeft,
    IconDeviceFloppy,
    IconCategory,
} from '@tabler/icons-vue'
import TablePagination from '@/Components/TablePagination.vue'
import { useCountUp } from '@/useCountUp'

defineOptions({ layout: AppLayout })
const countUp = useCountUp()

const props = defineProps({
    categories: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const showForm = ref(false)
const editingId = ref(null)
const flashMessage = ref('')
let searchTimeout

const form = useForm({
    name: '',
    description: '',
})

watch([search, status], () => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get('/inventory/categories', {
            search: search.value || undefined,
            status: status.value || undefined,
            per_page: props.categories.per_page,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 300)
})

onBeforeUnmount(() => clearTimeout(searchTimeout))

function openCreateForm() {
    editingId.value = null
    form.reset()
    form.clearErrors()
    showForm.value = true
}

function openEditForm(category) {
    editingId.value = category.id
    form.clearErrors()
    form.name = category.name
    form.description = category.description ?? ''
    showForm.value = true
}

function closeForm() {
    showForm.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
}

function submitForm() {
    const isEditing = editingId.value !== null

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeForm()
            flashMessage.value = isEditing
                ? 'Kategori berhasil diperbarui.'
                : 'Kategori berhasil ditambahkan.'
        },
    }

    if (isEditing) {
        form.put(`/inventory/categories/${editingId.value}`, options)
    } else {
        form.post('/inventory/categories', options)
    }
}

function toggleStatus(category) {
    const action = category.is_active ? 'menonaktifkan' : 'mengaktifkan'

    if (!window.confirm(`Yakin ingin ${action} kategori "${category.name}"?`)) {
        return
    }

    router.patch(`/inventory/categories/${category.id}/status`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            flashMessage.value = 'Status kategori berhasil diperbarui.'
        },
    })
}

function numberFormat(value) {
    return new Intl.NumberFormat('id-ID').format(value ?? 0)
}
</script>

<template>
    <div class="categories-page inventory-workspace">
        <div class="page-heading">
            <h1><span class="inventory-mark"><IconTags :size="21" aria-hidden="true" /></span>Kategori Barang</h1>

            <div class="heading-actions">
                <Link href="/inventory" class="secondary-button">
                    <IconChevronLeft :size="17" />
                    Data Barang
                </Link>
                <button class="primary-button" type="button" @click="openCreateForm">
                    <IconPlus :size="18" />
                    Tambah Kategori
                </button>
            </div>
        </div>

        <div v-if="flashMessage" class="flash-message" role="status">
            <IconCheck :size="18" />
            <span>{{ flashMessage }}</span>
            <button type="button" aria-label="Tutup pesan" @click="flashMessage = ''">
                <IconX :size="17" />
            </button>
        </div>

        <section class="summary-grid">
            <article class="summary-card">
                <div class="summary-icon icon-blue">
                    <IconTags :size="21" />
                </div>
                <div>
                    <div class="summary-label">Total kategori</div>
                    <strong><span aria-hidden="true">{{ numberFormat(countUp(summary.total ?? 0)) }}</span><span class="sr-only">{{ numberFormat(summary.total) }}</span></strong>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon icon-green">
                    <IconCheck :size="21" />
                </div>
                <div>
                    <div class="summary-label">Kategori aktif</div>
                    <strong><span aria-hidden="true">{{ numberFormat(countUp(summary.active ?? 0)) }}</span><span class="sr-only">{{ numberFormat(summary.active) }}</span></strong>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon icon-gray">
                    <IconBan :size="21" />
                </div>
                <div>
                    <div class="summary-label">Kategori nonaktif</div>
                    <strong><span aria-hidden="true">{{ numberFormat(countUp(summary.inactive ?? 0)) }}</span><span class="sr-only">{{ numberFormat(summary.inactive) }}</span></strong>
                </div>
            </article>
        </section>

        <section class="table-card">
            <div class="table-heading">
                <div>
                    <h2>Daftar kategori</h2>
                    <p>Nama, jumlah barang, dan status kategori.</p>
                </div>
                <span class="record-count">{{ numberFormat(categories.total) }} data</span>
            </div>

            <div class="filter-bar">
                <label class="search-field">
                    <IconSearch :size="18" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama atau keterangan..."
                        aria-label="Cari kategori"
                    />
                    <button
                        v-if="search"
                        type="button"
                        aria-label="Hapus pencarian"
                        @click="search = ''"
                    >
                        <IconX :size="16" />
                    </button>
                </label>

                <select v-model="status" class="status-filter" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th class="number-cell">Jumlah barang</th>
                            <th>Status</th>
                            <th class="action-cell">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="category in categories.data" :key="category.id">
                            <td>
                                <div class="category-identity">
                                    <div class="category-icon">
                                        <IconCategory :size="19" />
                                    </div>
                                    <div>
                                        <strong>{{ category.name }}</strong>
                                        <small>ID kategori: {{ category.id }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="description-cell">
                                {{ category.description || '—' }}
                            </td>
                            <td class="number-cell">
                                {{ numberFormat(category.items_count) }}
                            </td>
                            <td>
                                <span :class="category.is_active ? 'status-badge active' : 'status-badge inactive'">
                                    <span class="status-dot"></span>
                                    {{ category.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="action-cell">
                                <div class="actions">
                                    <button
                                        class="action-button edit-action"
                                        type="button"
                                        title="Edit kategori"
                                        aria-label="Edit kategori"
                                        @click="openEditForm(category)"
                                    >
                                        <IconEdit :size="17" />
                                    </button>
                                    <button
                                        class="action-button"
                                        :class="category.is_active ? 'deactivate-action' : 'activate-action'"
                                        type="button"
                                        :title="category.is_active ? 'Nonaktifkan kategori' : 'Aktifkan kategori'"
                                        :aria-label="category.is_active ? 'Nonaktifkan kategori' : 'Aktifkan kategori'"
                                        @click="toggleStatus(category)"
                                    >
                                        <IconBan v-if="category.is_active" :size="17" />
                                        <IconCheck v-else :size="17" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="categories.data.length === 0">
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-icon"><IconTags :size="27" /></div>
                                    <h3>
                                        {{ search || status ? 'Kategori tidak ditemukan' : 'Belum ada kategori' }}
                                    </h3>
                                    <p>
                                        {{ search || status
                                            ? 'Coba ubah kata kunci atau filter status.'
                                            : 'Tambahkan kategori agar barang dapat dikelompokkan dengan benar.' }}
                                    </p>
                                    <button
                                        v-if="!search && !status"
                                        class="secondary-button"
                                        type="button"
                                        @click="openCreateForm"
                                    >
                                        <IconPlus :size="17" />
                                        Tambah kategori pertama
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination :pagination="categories" label="kategori" />
        </section>

        <div v-if="showForm" class="modal-backdrop" @click.self="closeForm">
            <section class="form-modal" role="dialog" aria-modal="true" aria-labelledby="form-title">
                <div class="modal-heading">
                    <div>

                        <h2 id="form-title">
                            {{ editingId ? 'Edit kategori' : 'Tambah kategori' }}
                        </h2>
                        <p>Gunakan nama yang jelas agar barang mudah dikelompokkan.</p>
                    </div>
                    <button
                        class="modal-close"
                        type="button"
                        aria-label="Tutup formulir"
                        @click="closeForm"
                    >
                        <IconX :size="20" />
                    </button>
                </div>

                <form @submit.prevent="submitForm">
                    <div class="form-body">
                        <div class="field-group">
                            <label for="category-name">Nama kategori <span>*</span></label>
                            <input
                                id="category-name"
                                v-model="form.name"
                                type="text"
                                maxlength="100"
                                placeholder="Contoh: Elektronik"
                                required
                            />
                            <small v-if="form.errors.name" class="field-error">
                                {{ form.errors.name }}
                            </small>
                        </div>

                        <div class="field-group">
                            <label for="category-description">
                                Keterangan <span class="optional-label">Opsional</span>
                            </label>
                            <textarea
                                id="category-description"
                                v-model="form.description"
                                rows="4"
                                maxlength="1000"
                                placeholder="Jelaskan jenis barang dalam kategori ini..."
                            ></textarea>
                            <small v-if="form.errors.description" class="field-error">
                                {{ form.errors.description }}
                            </small>
                        </div>

                        <div class="form-note">
                            <IconTags :size="19" />
                            <p>
                                Kategori yang dinonaktifkan tidak bisa dipilih untuk barang baru.
                                Barang lama tetap tersimpan dan tidak dihapus.
                            </p>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button
                            class="secondary-button"
                            type="button"
                            :disabled="form.processing"
                            @click="closeForm"
                        >
                            Batal
                        </button>
                        <button class="primary-button" type="submit" :disabled="form.processing">
                            <IconDeviceFloppy :size="18" />
                            {{ form.processing ? 'Menyimpan...' : 'Simpan kategori' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>

<style scoped>
.categories-page { color: #182433; font-family: Inter, sans-serif; font-size: 14px; }
.heading-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.page-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 24px; }
.breadcrumb { display: flex; gap: 9px; margin-bottom: 8px; color: #667382; font-size: 12px; }
.breadcrumb span { color: #a0a8b2; }
.page-heading h1 { margin: 0; font-size: 24px; font-weight: 600; letter-spacing: -.6px; }
.page-description { margin: 7px 0 0; color: #667382; line-height: 1.6; }
.primary-button, .secondary-button { display: inline-flex; justify-content: center; align-items: center; gap: 8px; min-height: 38px; padding: 0 14px; border: 1px solid transparent; border-radius: 4px; font: inherit; font-weight: 500; cursor: pointer; white-space: nowrap; }
.primary-button { color: white; background: #066fd1; border-color: #066fd1; }
.primary-button:hover { background: #055eb2; }
.primary-button:disabled { opacity: .55; cursor: not-allowed; }
.secondary-button { color: #182433; background: white; border-color: #dce0e5; }
.secondary-button:hover { background: #f6f8fb; }
.summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
.summary-card { display: flex; align-items: flex-start; gap: 13px; padding: 19px; background: #fff; border: 1px solid #e6e7e9; border-radius: 4px; }
.summary-icon { display: grid; flex: 0 0 42px; width: 42px; height: 42px; place-items: center; border-radius: 4px; }
.icon-blue { color: #066fd1; background: #e8f2fc; }
.icon-green { color: #2fb344; background: #eaf7ed; }
.icon-gray { color: #667382; background: #f1f4f8; }
.summary-label { color: #667382; font-size: 12px; }
.summary-card strong { display: block; margin-top: 5px; font-size: 25px; font-weight: 600; line-height: 1.2; }
.summary-card p { margin: 5px 0 0; color: #8793a1; font-size: 11px; line-height: 1.5; }
.table-card { overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 4px; }
.table-heading { display: flex; align-items: center; justify-content: space-between; gap: 15px; padding: 20px 22px; }
.table-heading h2 { margin: 0; font-size: 16px; font-weight: 600; }
.table-heading p { margin: 6px 0 0; color: #667382; font-size: 12px; }
.record-count { color: #667382; font-size: 12px; }
.filter-bar { display: flex; gap: 10px; padding: 0 22px 18px; }
.search-field { display: flex; flex: 1; align-items: center; gap: 9px; height: 38px; padding: 0 11px; color: #667382; border: 1px solid #dce0e5; border-radius: 4px; }
.search-field input { width: 100%; min-width: 0; border: 0; outline: 0; color: #182433; background: transparent; font: inherit; }
.search-field input::placeholder { color: #9aa4af; }
.search-field button, .flash-message button { display: inline-grid; place-items: center; padding: 2px; border: 0; color: inherit; background: transparent; cursor: pointer; }
.status-filter { min-width: 160px; height: 38px; padding: 0 11px; border: 1px solid #dce0e5; border-radius: 4px; color: #182433; background: white; font: inherit; }
.table-scroll { width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
thead { background: #f6f8fb; }
th { padding: 12px 17px; color: #667382; font-size: 11px; font-weight: 600; border-top: 1px solid #e6e7e9; border-bottom: 1px solid #e6e7e9; white-space: nowrap; }
td { padding: 14px 17px; color: #465363; border-bottom: 1px solid #edf0f2; }
tbody tr:last-child td { border-bottom: 0; }
tbody tr:hover { background: #fbfcfe; }
.category-identity { display: flex; align-items: center; gap: 11px; min-width: 175px; }
.category-icon { display: grid; flex: 0 0 36px; width: 36px; height: 36px; place-items: center; color: #066fd1; background: #eef5fc; border: 1px solid #e2edf9; border-radius: 4px; }
.category-identity strong { display: block; color: #182433; font-weight: 500; }
.category-identity small { display: block; margin-top: 4px; color: #8793a1; font-size: 11px; }
.description-cell { min-width: 180px; max-width: 330px; color: #667382; font-size: 12px; line-height: 1.6; }
.number-cell { text-align: right; font-variant-numeric: tabular-nums; }
.status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; white-space: nowrap; }
.status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.status-badge.active { color: #218838; background: #eaf7ed; }
.status-badge.inactive { color: #667382; background: #f1f4f8; }
.action-cell { text-align: right; }
.actions { display: flex; justify-content: flex-end; gap: 6px; }
.action-button { display: grid; width: 32px; height: 32px; place-items: center; border: 1px solid #dce0e5; border-radius: 4px; background: white; cursor: pointer; }
.edit-action { color: #066fd1; }
.edit-action:hover { background: #eef5fc; border-color: #c7def5; }
.deactivate-action { color: #d63939; }
.deactivate-action:hover { background: #fdecec; border-color: #f5cccc; }
.activate-action { color: #218838; }
.activate-action:hover { background: #eaf7ed; border-color: #ccebd2; }
.empty-state { display: flex; align-items: center; flex-direction: column; padding: 48px 20px; text-align: center; }
.empty-icon { display: grid; width: 54px; height: 54px; place-items: center; margin-bottom: 15px; color: #667382; background: #f1f4f8; border-radius: 50%; }
.empty-state h3 { margin: 0; color: #182433; font-size: 15px; font-weight: 600; }
.empty-state p { max-width: 360px; margin: 8px 0 18px; color: #667382; line-height: 1.6; }
.pagination-bar { display: flex; align-items: center; justify-content: space-between; gap: 15px; padding: 16px 22px; color: #667382; font-size: 12px; border-top: 1px solid #e6e7e9; }
.pagination-bar strong { color: #182433; font-weight: 500; }
.pagination-controls { display: flex; gap: 5px; }
.page-button { display: grid; min-width: 32px; height: 32px; place-items: center; padding: 0 8px; color: #465363; border: 1px solid #dce0e5; border-radius: 4px; background: white; font: inherit; font-size: 12px; cursor: pointer; }
.page-button:hover:not(:disabled) { color: #066fd1; border-color: #066fd1; }
.page-button:disabled { opacity: .4; cursor: not-allowed; }
.page-button.page-active { color: white; background: #066fd1; border-color: #066fd1; }
.flash-message { display: flex; align-items: center; gap: 9px; margin-bottom: 18px; padding: 12px 14px; color: #218838; background: #eaf7ed; border: 1px solid #ccebd2; border-radius: 4px; }
.flash-message button { margin-left: auto; }
.modal-backdrop { position: fixed; z-index: 1000; inset: 0; display: flex; justify-content: center; align-items: center; overflow-y: auto; padding: 24px; background: rgb(24 36 51 / 42%); }
.form-modal { width: 100%; max-width: 560px; max-height: calc(100vh - 48px); overflow-y: auto; background: white; border: 1px solid #e6e7e9; border-radius: 5px; box-shadow: 0 12px 40px rgb(24 36 51 / 18%); }
.modal-heading { display: flex; justify-content: space-between; gap: 15px; padding: 23px 25px 20px; border-bottom: 1px solid #e6e7e9; }
.modal-eyebrow { color: #066fd1; font-size: 10px; font-weight: 600; letter-spacing: 1px; }
.modal-heading h2 { margin: 7px 0 0; font-size: 20px; font-weight: 600; }
.modal-heading p { margin: 6px 0 0; color: #667382; font-size: 12px; line-height: 1.5; }
.modal-close { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; color: #667382; background: white; border: 1px solid #e6e7e9; border-radius: 4px; cursor: pointer; }
.form-body { display: flex; flex-direction: column; gap: 18px; padding: 22px 25px; }
.field-group { display: flex; flex-direction: column; gap: 7px; }
.field-group label { color: #344054; font-size: 12px; font-weight: 500; }
.field-group label > span:first-child { color: #d63939; }
.field-group input, .field-group textarea { width: 100%; min-height: 39px; padding: 9px 11px; color: #182433; border: 1px solid #dce0e5; border-radius: 4px; outline: none; background: white; font: inherit; font-size: 13px; }
.field-group textarea { min-height: 90px; resize: vertical; }
.field-group input:focus, .field-group textarea:focus { border-color: #066fd1; box-shadow: 0 0 0 2px rgb(6 111 209 / 10%); }
.field-error { color: #d63939; font-size: 11px; line-height: 1.5; }
.optional-label { margin-left: 5px; color: #8793a1; font-weight: 400; }
.form-note { display: flex; align-items: flex-start; gap: 10px; padding: 12px; color: #315a83; background: #eef5fc; border: 1px solid #d9e9fa; border-radius: 4px; }
.form-note svg { flex: 0 0 auto; margin-top: 1px; }
.form-note p { margin: 0; font-size: 11px; line-height: 1.6; }
.modal-footer { display: flex; justify-content: flex-end; gap: 9px; padding: 16px 25px; border-top: 1px solid #e6e7e9; background: #fbfcfe; }
@media (max-width: 800px) {
    .summary-grid { grid-template-columns: 1fr; gap: 10px; }
    .summary-card { padding: 14px; }
    .page-heading { flex-direction: column; }
    .filter-bar { padding: 0 14px 15px; }
    .table-heading { padding: 17px 14px; }
    th, td { padding: 12px 13px; }
    .pagination-bar { flex-direction: column; align-items: flex-start; padding: 14px; }
    .modal-backdrop { align-items: flex-start; padding: 12px; }
    .form-modal { max-height: calc(100vh - 24px); }
    .modal-heading, .form-body { padding-left: 17px; padding-right: 17px; }
    .modal-footer { padding: 14px 17px; }
}
@media (max-width: 520px) {
    .filter-bar { flex-direction: column; }
    .status-filter { width: 100%; }
}
</style>

<style scoped src="../../../css/inventory.css"></style>
