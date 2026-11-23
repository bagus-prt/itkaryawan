<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import {
    IconMessageCircle,
    IconPlus,
    IconPrinter,
    IconSearch,
    IconX,
} from '@tabler/icons-vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import TablePagination from '@/Components/TablePagination.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    complaints: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    today: { type: String, required: true },
})

const search = ref(props.filters.search ?? '')
const month = ref(props.filters.month ?? '')
const year = ref(props.filters.year ?? '')
const modalOpen = ref(false)
const flashMessage = ref('')
let searchTimeout

const form = useForm({
    station_code: '',
    station_address: '',
    description: '',
})

watch([search, month, year], () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 300)
})

onBeforeUnmount(() => clearTimeout(searchTimeout))

function applyFilters() {
    router.get('/complaints', {
        search: search.value || undefined,
        month: month.value || undefined,
        year: year.value || undefined,
        per_page: props.complaints.per_page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function resetFilters() {
    search.value = ''
    month.value = ''
    year.value = ''
}

function openModal() {
    form.reset()
    form.clearErrors()
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    form.reset()
    form.clearErrors()
}

function submitForm() {
    form.post('/complaints', {
        preserveScroll: true,
        onSuccess: () => {
            closeModal()
            flashMessage.value = 'Keluhan SPBU berhasil dicatat.'
        },
    })
}

function statusLabel(status) {
    return status === 'baru' ? 'Baru' : status
}
</script>

<template>
    <div class="complaints-page">
        <div class="page-heading">
            <div>
                <div class="breadcrumb">Operasional <span>/</span> Keluhan Pelanggan</div>
                <h1>Keluhan SPBU</h1>
                <p class="page-description">Kelola keluhan yang disampaikan SPBU kepada Pertamina, termasuk laporan yang dicatat admin saat sidak.</p>
            </div>

            <div class="heading-actions">
                <Link href="/complaints/qr" class="secondary-button">
                    <IconPrinter :size="18" />
                    Cetak QR Keluhan
                </Link>
                <button class="primary-button" type="button" @click="openModal">
                    <IconPlus :size="18" />
                    Catat Keluhan
                </button>
            </div>
        </div>

        <div v-if="flashMessage" class="flash-message" role="status">
            <IconMessageCircle :size="18" />
            <span>{{ flashMessage }}</span>
            <button type="button" aria-label="Tutup pesan" @click="flashMessage = ''">
                <IconX :size="17" />
            </button>
        </div>

        <section class="table-card">
            <div class="table-heading">
                <div>
                    <h2>Daftar keluhan</h2>
                    <p>Tanggal tercatat otomatis saat keluhan dikirim atau dicatat admin.</p>
                </div>
                <span class="record-count">{{ complaints.total }} data</span>
            </div>

            <div class="filter-bar">
                <label class="search-field">
                    <IconSearch :size="18" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari kode SPBU, lokasi, atau keterangan..."
                        aria-label="Cari keluhan"
                    />
                    <button v-if="search" type="button" aria-label="Hapus pencarian" @click="search = ''">
                        <IconX :size="16" />
                    </button>
                </label>

                <select v-model="month" class="filter-select" aria-label="Filter bulan">
                    <option value="">Semua bulan</option>
                    <option v-for="(label, index) in ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']" :key="label" :value="index + 1">
                        {{ label }}
                    </option>
                </select>

                <label class="year-filter">
                    <span>Tahun</span>
                    <input v-model="year" type="number" min="2000" max="2100" placeholder="Semua" aria-label="Filter tahun" />
                </label>

                <button v-if="search || month || year" class="clear-filter" type="button" @click="resetFilters">
                    Hapus filter
                </button>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nomor / kode SPBU</th>
                            <th>Lokasi / alamat SPBU</th>
                            <th>Keterangan keluhan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="complaint in complaints.data" :key="complaint.id">
                            <td class="date-cell">{{ complaint.date }}</td>
                            <td class="station-code">{{ complaint.station_code }}</td>
                            <td class="address-cell">{{ complaint.station_address }}</td>
                            <td class="description-cell">{{ complaint.description }}</td>
                            <td><span class="status-badge">{{ statusLabel(complaint.status) }}</span></td>
                        </tr>
                        <tr v-if="complaints.data.length === 0">
                            <td colspan="5">
                                <div class="empty-state">
                                    <IconMessageCircle :size="30" />
                                    <h3>{{ search || month || year ? 'Keluhan tidak ditemukan' : 'Belum ada keluhan' }}</h3>
                                    <p>{{ search || month || year ? 'Ubah pencarian atau filter untuk melihat data lainnya.' : 'Keluhan yang dikirim melalui QR dan dicatat admin akan muncul di sini.' }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination :pagination="complaints" label="keluhan" />
        </section>

        <div v-if="modalOpen" class="modal-backdrop" @click.self="closeModal">
            <section class="form-modal" role="dialog" aria-modal="true" aria-labelledby="form-title">
                <div class="modal-heading">
                    <div>
                        <span class="modal-eyebrow">INPUT ADMIN / SIDAK</span>
                        <h2 id="form-title">Catat keluhan SPBU</h2>
                        <p>Tanggal laporan akan otomatis tercatat saat formulir disimpan.</p>
                    </div>
                    <button class="modal-close" type="button" aria-label="Tutup formulir" @click="closeModal">
                        <IconX :size="20" />
                    </button>
                </div>

                <form @submit.prevent="submitForm">
                    <div class="form-body">
                        <div class="field-group">
                            <label for="complaint-station-code">Nomor / kode SPBU <span>*</span></label>
                            <input id="complaint-station-code" v-model="form.station_code" type="text" maxlength="50" placeholder="Contoh: 31.401.01" required />
                            <small v-if="form.errors.station_code" class="field-error">{{ form.errors.station_code }}</small>
                        </div>

                        <div class="field-group">
                            <label for="complaint-station-address">Lokasi / alamat SPBU <span>*</span></label>
                            <textarea id="complaint-station-address" v-model="form.station_address" rows="2" maxlength="2000" required></textarea>
                            <small v-if="form.errors.station_address" class="field-error">{{ form.errors.station_address }}</small>
                        </div>

                        <div class="field-group">
                            <label for="complaint-date">Tanggal <span class="optional-label">Otomatis</span></label>
                            <input id="complaint-date" type="date" :value="today" disabled />
                        </div>

                        <div class="field-group">
                            <label for="complaint-description">Keterangan keluhan <span>*</span></label>
                            <textarea id="complaint-description" v-model="form.description" rows="5" maxlength="10000" placeholder="Jelaskan keluhan atau temuan saat sidak..." required></textarea>
                            <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="secondary-button" type="button" :disabled="form.processing" @click="closeModal">Batal</button>
                        <button class="primary-button" type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan keluhan' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>

<style scoped>
.complaints-page { display: flex; flex-direction: column; gap: 20px; padding: 24px; color: #182433; }
.page-heading, .heading-actions, .table-heading, .filter-bar, .pagination, .pagination-actions { display: flex; align-items: center; }
.page-heading, .table-heading, .pagination { justify-content: space-between; gap: 16px; }
.breadcrumb { margin-bottom: 7px; color: #667382; font-size: 12px; }
.breadcrumb span { padding: 0 5px; }
h1 { margin: 0; font-size: 24px; }
.page-description, .table-heading p { margin: 6px 0 0; color: #667382; font-size: 13px; line-height: 1.5; }
.heading-actions { flex-wrap: wrap; justify-content: flex-end; gap: 9px; }
.primary-button, .secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 39px; padding: 0 13px; border: 1px solid #dce0e5; border-radius: 4px; font: inherit; font-size: 12px; font-weight: 500; text-decoration: none; cursor: pointer; }
.primary-button { color: #fff; background: #066fd1; border-color: #066fd1; }
.secondary-button { color: #344054; background: #fff; }
.primary-button:disabled, .secondary-button:disabled { opacity: .6; cursor: wait; }
.flash-message { display: flex; align-items: center; gap: 9px; padding: 12px 14px; color: #195c2a; background: #eaf7ed; border: 1px solid #ccebd2; border-radius: 5px; font-size: 13px; }
.flash-message button { display: grid; margin-left: auto; padding: 3px; color: inherit; background: transparent; border: 0; cursor: pointer; }
.table-card { min-width: 0; background: #fff; border: 1px solid #e6e7e9; border-radius: 5px; }
.table-heading { padding: 18px 20px; }
.table-heading h2 { margin: 0; font-size: 15px; }
.record-count { color: #667382; font-size: 12px; white-space: nowrap; }
.filter-bar { flex-wrap: wrap; gap: 10px; padding: 0 20px 16px; }
.search-field { display: flex; flex: 1 1 300px; align-items: center; gap: 8px; min-height: 38px; padding: 0 10px; color: #667382; border: 1px solid #dce0e5; border-radius: 4px; }
.search-field input { flex: 1; min-width: 0; height: 36px; border: 0; outline: 0; font: inherit; font-size: 12px; }
.search-field button { display: grid; padding: 3px; color: #667382; background: transparent; border: 0; cursor: pointer; }
.filter-select, .year-filter input { height: 38px; padding: 0 10px; color: #344054; background: #fff; border: 1px solid #dce0e5; border-radius: 4px; font: inherit; font-size: 12px; }
.year-filter { display: flex; align-items: center; gap: 7px; color: #667382; font-size: 12px; }
.year-filter input { width: 105px; }
.clear-filter { height: 36px; padding: 0 10px; color: #066fd1; background: transparent; border: 0; font: inherit; font-size: 12px; cursor: pointer; }
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th { padding: 11px 14px; color: #667382; background: #f8f9fb; border-top: 1px solid #e6e7e9; border-bottom: 1px solid #e6e7e9; font-size: 11px; font-weight: 600; white-space: nowrap; }
td { padding: 13px 14px; border-bottom: 1px solid #eef0f2; color: #344054; font-size: 12px; vertical-align: top; }
.date-cell { min-width: 130px; white-space: nowrap; }
.station-code { min-width: 140px; font-weight: 600; }
.address-cell { min-width: 200px; max-width: 280px; }
.description-cell { min-width: 250px; max-width: 420px; white-space: pre-wrap; overflow-wrap: anywhere; }
.status-badge { display: inline-flex; padding: 5px 9px; color: #855d00; background: #fff4d6; border-radius: 99px; font-size: 11px; white-space: nowrap; }
.empty-state { padding: 38px 16px; color: #8793a1; text-align: center; }
.empty-state h3 { margin: 8px 0 5px; color: #344054; font-size: 14px; }
.empty-state p { margin: 0; font-size: 12px; }
.pagination { padding: 14px 20px; color: #667382; font-size: 12px; }
.pagination-actions { gap: 7px; }
.pagination-actions button { display: grid; width: 32px; height: 32px; place-items: center; color: #344054; background: #fff; border: 1px solid #dce0e5; border-radius: 4px; cursor: pointer; }
.pagination-actions button:disabled { opacity: .45; cursor: not-allowed; }
.modal-backdrop { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 20px; background: rgb(16 24 40 / 50%); }
.form-modal { width: min(100%, 560px); max-height: min(90vh, 760px); overflow: auto; background: #fff; border-radius: 6px; box-shadow: 0 20px 60px rgb(16 24 40 / 20%); }
.modal-heading { display: flex; justify-content: space-between; gap: 16px; padding: 20px 22px; border-bottom: 1px solid #e6e7e9; }
.modal-eyebrow { color: #066fd1; font-size: 10px; font-weight: 600; letter-spacing: .8px; }
.modal-heading h2 { margin: 6px 0 0; font-size: 18px; }
.modal-heading p { margin: 6px 0 0; color: #667382; font-size: 12px; }
.modal-close { display: grid; width: 32px; height: 32px; place-items: center; color: #667382; background: #fff; border: 1px solid #e6e7e9; border-radius: 4px; cursor: pointer; }
.form-body { display: flex; flex-direction: column; gap: 16px; padding: 20px 22px; }
.field-group { display: flex; flex-direction: column; gap: 7px; }
.field-group label { color: #344054; font-size: 12px; font-weight: 500; }
.field-group label > span:not(.optional-label) { color: #d63939; }
.field-group input, .field-group textarea { width: 100%; min-height: 39px; padding: 9px 10px; color: #182433; border: 1px solid #dce0e5; border-radius: 4px; outline: 0; background: #fff; font: inherit; font-size: 12px; resize: vertical; }
.field-group input:focus, .field-group textarea:focus { border-color: #066fd1; box-shadow: 0 0 0 2px rgb(6 111 209 / 10%); }
.field-group input:disabled { color: #667382; background: #f6f8fb; }
.optional-label { margin-left: 5px; color: #8793a1; font-size: 10px; font-weight: 400; }
.field-error { color: #c02c2c; font-size: 11px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 9px; padding: 15px 22px; border-top: 1px solid #e6e7e9; }
@media (max-width: 700px) {
    .complaints-page { padding: 16px; }
    .page-heading { align-items: flex-start; flex-direction: column; }
    .heading-actions { justify-content: flex-start; }
    .table-heading { align-items: flex-start; }
    .filter-bar { align-items: stretch; flex-direction: column; }
    .search-field { flex-basis: auto; }
    .filter-select { width: 100%; }
    .year-filter { justify-content: space-between; }
    .year-filter input { width: 130px; }
}
</style>
