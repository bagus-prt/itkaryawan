<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    IconSearch,
    IconPlus,
    IconPackage,
    IconAlertTriangle,
    IconCircleCheck,
    IconCircleX,
    IconX,
    IconDeviceFloppy,
    IconFilter,
    IconEye,
    IconArchive,
    IconRefresh,
} from '@tabler/icons-vue'
import TablePagination from '@/Components/TablePagination.vue'
import { useCountUp } from '@/useCountUp'

defineOptions({ layout: AppLayout })
const countUp = useCountUp()

const props = defineProps({
    items: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    canUpdateItemType: Boolean,
})

const search = ref(props.filters.search ?? '')
const category = ref(props.filters.category ?? '')
const stockStatus = ref(props.filters.stock_status ?? '')
const status = ref(props.filters.status ?? 'active')
const showForm = ref(false)
const showStockForm = ref(false)
const showDetails = ref(false)
const stockItem = ref(null)
const selectedItem = ref(null)
const flashMessage = ref('')
const imageInput = ref(null)
const imagePreview = ref('')

let searchTimeout

watch([search, category, stockStatus, status], () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/inventory', {
            search: search.value || undefined,
            category: category.value || undefined,
            stock_status: stockStatus.value || undefined,
            status: status.value || undefined,
            per_page: props.items.per_page,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 300)
})

const form = useForm({
    name: '',
    category_id: '',
    unit: 'Pcs',
    initial_quantity: 0,
    is_borrowable: '',
    description: '',
    image: null,
})

const stockForm = useForm({
    quantity: 1,
    notes: '',
})
const typeForm = useForm({ is_borrowable: '' })
const itemTypes = [
    { value: false, label: 'Habis Pakai' },
    { value: true, label: 'Dapat Dipinjam' },
]
const typeLabel = (borrowable) => itemTypes.find((type) => type.value === borrowable)?.label ?? '—'

const stockLabel = (status) => ({
    available: 'Tersedia',
    low: 'Menipis',
    out: 'Habis',
}[status] ?? 'Tidak diketahui')

const stockClass = (status) => ({
    available: 'stock-badge stock-available',
    low: 'stock-badge stock-low',
    out: 'stock-badge stock-out',
}[status] ?? 'stock-badge')

const numberFormat = (value) =>
    new Intl.NumberFormat('id-ID').format(value ?? 0)

function clearImage() {
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value)
    }

    imagePreview.value = ''
    form.image = null

    if (imageInput.value) {
        imageInput.value.value = ''
    }
}

function selectImage(event) {
    const file = event.target.files?.[0]

    if (!file) {
        return
    }

    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value)
    }

    form.image = file
    imagePreview.value = URL.createObjectURL(file)
}

function openForm() {
    form.reset()
    form.clearErrors()
    clearImage()
    form.unit = props.units.includes('Pcs') ? 'Pcs' : (props.units[0] ?? '')
    showForm.value = true
}

function openStockForm(item) {
    stockItem.value = item
    stockForm.reset()
    stockForm.clearErrors()
    showStockForm.value = true
}

function closeStockForm() {
    showStockForm.value = false
    stockItem.value = null
    stockForm.reset()
    stockForm.clearErrors()
}

function submitStockForm() {
    if (!stockItem.value) {
        return
    }

    stockForm.post(`/inventory/${stockItem.value.id}/stock`, {
        preserveScroll: true,
        onSuccess: () => {
            closeStockForm()
            flashMessage.value = 'Stok barang berhasil ditambahkan.'
        },
    })
}

function closeForm() {
    showForm.value = false
    form.reset()
    form.clearErrors()
    clearImage()
}

function openDetails(item) {
    selectedItem.value = item
    typeForm.is_borrowable = item.is_borrowable
    typeForm.clearErrors()
    showDetails.value = true
}

function closeDetails() {
    if (typeForm.processing) return
    showDetails.value = false
    selectedItem.value = null
}

function submitItemType() {
    if (!selectedItem.value || typeForm.processing) return

    typeForm.patch(`/inventory/${selectedItem.value.id}/type`, {
        preserveScroll: true,
        onSuccess: () => {
            showDetails.value = false
            selectedItem.value = null
            flashMessage.value = 'Jenis barang berhasil diubah.'
        },
    })
}

function archiveItem(item) {
    if (!window.confirm(`Arsipkan "${item.name}" dari daftar barang aktif? Riwayat transaksi akan tetap tersimpan.`)) {
        return
    }

    router.delete(`/inventory/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            flashMessage.value = 'Barang berhasil dihapus dari daftar aktif.'
        },
    })
}

function restoreItem(item) {
    router.patch(`/inventory/${item.id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            flashMessage.value = 'Barang berhasil dipulihkan.'
        },
    })
}

function submitForm(addAnother = false) {
    form.post('/inventory', {
        preserveScroll: true,

        onSuccess: () => {
            flashMessage.value = addAnother
                ? 'Barang berhasil ditambahkan. Silakan isi barang berikutnya.'
                : 'Barang berhasil ditambahkan.'

            if (addAnother) {
                // Pertahankan kategori dan satuan untuk input berikutnya.
                form.reset(
                    'name',
                    'initial_quantity',
                    'is_borrowable',
                    'description',
                    'image',
                )

                clearImage()
                form.clearErrors()
            } else {
                closeForm()
            }

            setTimeout(() => {
                flashMessage.value = ''
            }, 3500)
        },
    })
}

onBeforeUnmount(() => {
    clearTimeout(searchTimeout)
    clearImage()
})
</script>

<template>
    <div class="inventory-page inventory-workspace">
        <div class="page-heading">
            <h1><span class="inventory-mark"><IconPackage :size="21" aria-hidden="true" /></span>Data Barang</h1>

            <div class="heading-actions">
                <button class="primary-button" type="button" @click="openForm">
                    <IconPlus :size="18" stroke-width="1.8" />
                    <span>Tambah Barang</span>
                </button>
            </div>
        </div>

        <div v-if="flashMessage" class="flash-message" role="status">
            <IconCircleCheck :size="19" />
            {{ flashMessage }}
            <button type="button" aria-label="Tutup pesan" @click="flashMessage = ''">
                <IconX :size="17" />
            </button>
        </div>

        <section class="summary-grid">
            <article class="summary-card">
                <div class="summary-icon icon-blue"><IconPackage :size="21" /></div>
                <div class="summary-content">
                    <span class="summary-label">Total jenis barang</span>
                    <strong><span aria-hidden="true">{{ numberFormat(countUp(summary.total_items ?? 0)) }}</span><span class="sr-only">{{ numberFormat(summary.total_items) }}</span></strong>

                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon icon-green"><IconCircleCheck :size="21" /></div>
                <div class="summary-content">
                    <span class="summary-label">Stok tersedia</span>
                    <strong><span aria-hidden="true">{{ numberFormat(countUp(summary.available ?? 0)) }}</span><span class="sr-only">{{ numberFormat(summary.available) }}</span></strong>

                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon icon-orange"><IconAlertTriangle :size="21" /></div>
                <div class="summary-content">
                    <span class="summary-label">Stok menipis</span>
                    <strong><span aria-hidden="true">{{ numberFormat(countUp(summary.low ?? 0)) }}</span><span class="sr-only">{{ numberFormat(summary.low) }}</span></strong>

                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon icon-red"><IconCircleX :size="21" /></div>
                <div class="summary-content">
                    <span class="summary-label">Stok habis</span>
                    <strong><span aria-hidden="true">{{ numberFormat(countUp(summary.out ?? 0)) }}</span><span class="sr-only">{{ numberFormat(summary.out) }}</span></strong>

                </div>
            </article>
        </section>

        <section class="table-card">
            <div class="table-card-heading">
                <div>
                    <h2>Daftar barang</h2>
                    <p>Data persediaan dan status stok saat ini.</p>
                </div>
                <span class="record-count">
                    {{ numberFormat(items.total) }} data
                </span>
            </div>

            <div class="filter-bar">
                <label class="search-field">
                    <IconSearch :size="18" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari kode atau nama barang..."
                        aria-label="Cari kode atau nama barang"
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

                <label class="select-field">
                    <IconFilter :size="17" />
                    <select v-model="category" aria-label="Filter kategori">
                        <option value="">Semua kategori</option>
                        <option
                            v-for="itemCategory in categories"
                            :key="itemCategory.id"
                            :value="String(itemCategory.id)"
                        >
                            {{ itemCategory.name }}
                        </option>
                    </select>
                </label>

                <select v-model="stockStatus" class="status-filter" aria-label="Filter kondisi stok">
                    <option value="">Semua kondisi</option>
                    <option value="available">Tersedia</option>
                    <option value="low">Menipis</option>
                    <option value="out">Habis</option>
                </select>

                <select v-model="status" class="status-filter" aria-label="Filter status barang">
                    <option value="active">Barang aktif</option>
                    <option value="inactive">Barang diarsipkan</option>
                    <option value="all">Semua barang</option>
                </select>
            </div>

            <div class="table-scroll">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th scope="col">Barang</th>
                            <th scope="col">Kategori</th>
                            <th scope="col">Stok</th>
                            <th scope="col">Status Stok</th>
                            <th scope="col">Jenis barang</th>
                            <th scope="col" class="actions-cell">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <tr v-for="item in items.data" :key="item.id">
                    <td>
                        <div class="item-identity">
                            <div class="item-avatar">
                                <img
                                    v-if="item.image_url"
                                    :src="item.image_url"
                                    :alt="`Foto ${item.name}`"
                                />
                                <IconPackage v-else :size="19" />
                            </div>
                            <div>
                                <div class="item-name">{{ item.name }}</div>
                                <div class="item-meta">
                                    <span class="item-code">{{ item.code }}</span>
                                    <small v-if="!item.is_active" class="archived-label">Diarsipkan</small>
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>{{ item.category?.name ?? '—' }}</td>

                    <td>
                        <div class="stock-quantity">
                            <strong>{{ numberFormat(item.stock_on_hand) }}</strong>
                            <small>{{ item.unit }}</small>
                        </div>
                    </td>
                    <td>
                        <span :class="stockClass(item.stock_status)">
                            <span class="status-dot"></span>
                            {{ stockLabel(item.stock_status) }}
                        </span>
                    </td>

                    <td>
                        <span class="item-type" :class="{ borrowable: item.is_borrowable }">
                            {{ typeLabel(item.is_borrowable) }}
                        </span>
                    </td>

                    <td class="actions-cell">
                        <div class="row-actions">
                            <button
                                v-if="item.is_active"
                                class="row-action-button add-action"
                                type="button"
                                :title="`Tambah stok ${item.name}`"
                                :aria-label="`Tambah stok ${item.name}`"
                                @click="openStockForm(item)"
                            >
                                <IconPlus :size="15" />
                            </button>
                            <button
                                class="row-action-button view-action"
                                type="button"
                                :title="`Lihat ${item.name}`"
                                :aria-label="`Lihat ${item.name}`"
                                @click="openDetails(item)"
                            >
                                <IconEye :size="16" />
                            </button>
                            <button
                                v-if="item.is_active"
                                class="row-action-button delete-action"
                                type="button"
                                :title="`Arsipkan ${item.name}`"
                                :aria-label="`Arsipkan ${item.name}`"
                                @click="archiveItem(item)"
                            >
                                <IconArchive :size="16" />
                            </button>
                            <button
                                v-else
                                class="row-action-button restore-action"
                                type="button"
                                :title="`Pulihkan ${item.name}`"
                                :aria-label="`Pulihkan ${item.name}`"
                                @click="restoreItem(item)"
                            >
                                <IconRefresh :size="16" />
                            </button>
                        </div>
                    </td>
                </tr>

                        <tr v-if="items.data.length === 0">
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon"><IconPackage :size="27" /></div>
                                    <h3>{{ search || category || stockStatus || status !== 'active' ? 'Barang tidak ditemukan' : 'Belum ada data barang' }}</h3>
                                    <p>
                                        {{ search || category || stockStatus || status !== 'active'
                                            ? 'Coba ubah kata kunci atau filter pencarian.'
                                            : 'Tambahkan barang pertama untuk mulai mengelola persediaan.' }}
                                    </p>
                                    <button
                                        v-if="!search && !category && !stockStatus && status === 'active'"
                                        class="secondary-button"
                                        type="button"
                                        @click="openForm"
                                    >
                                        <IconPlus :size="17" />
                                        Tambah barang pertama
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination :pagination="items" label="barang" />
        </section>

        <div v-if="showForm" class="modal-backdrop add-item-backdrop" @click.self="closeForm">
            <section class="form-modal add-item-modal" role="dialog" aria-modal="true" aria-labelledby="form-title">
                <div class="modal-heading">
                    <div>

                        <h2 id="form-title">Tambah barang</h2>
                    </div>
                    <button class="modal-close" type="button" aria-label="Tutup formulir" @click="closeForm">
                        <IconX :size="20" />
                    </button>
                </div>

                <form @submit.prevent="submitForm">
                    <div class="form-body">
                        <div class="field-group field-wide">
                            <label for="item-name">Nama barang <span>*</span></label>
                            <input
                                id="item-name"
                                v-model="form.name"
                                type="text"
                                placeholder="Contoh: Laptop Lenovo ThinkPad"
                                maxlength="150"
                                required
                            />
                            <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
                        </div>

                        <div class="form-two-columns">
                            <div class="field-group">
                                <label for="item-category">Kategori <span>*</span></label>
                                <select id="item-category" v-model="form.category_id" required>
                                    <option value="" disabled>Pilih kategori</option>
                                    <option
                                        v-for="itemCategory in categories"
                                        :key="itemCategory.id"
                                        :value="itemCategory.id"
                                    >
                                        {{ itemCategory.name }}
                                    </option>
                                </select>
                                <small v-if="form.errors.category_id" class="field-error">{{ form.errors.category_id }}</small>
                                <small v-if="categories.length === 0" class="field-hint">
                                    Belum ada kategori aktif. Tambahkan kategori terlebih dahulu.
                                </small>
                            </div>

                            <div class="field-group">
                                <label for="item-unit">Satuan <span>*</span></label>
                                <select id="item-unit" v-model="form.unit" required>
                                    <option v-for="unit in units" :key="unit" :value="unit">{{ unit }}</option>
                                </select>
                                <small v-if="form.errors.unit" class="field-error">{{ form.errors.unit }}</small>
                            </div>
                        </div>

                        <div class="field-group">
                            <label for="item-quantity">Jumlah stok awal <span>*</span></label>
                            <input
                                id="item-quantity"
                                v-model.number="form.initial_quantity"
                                type="number"
                                min="0"
                                step="1"
                                required
                            />
                            <small class="field-hint">Stok di atas 0 dicatat sebagai barang masuk.</small>
                            <small v-if="form.errors.initial_quantity" class="field-error">{{ form.errors.initial_quantity }}</small>
                        </div>

                        <div class="field-group">
                            <label for="item-image">Foto barang <span>*</span></label>
                            <input
                                id="item-image"
                                ref="imageInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                required
                                @change="selectImage"
                            />
                            <small class="field-hint">Format JPG, PNG, atau WEBP; ukuran maksimal 2 MB.</small>
                            <small v-if="form.errors.image" class="field-error">{{ form.errors.image }}</small>
                            <img
                                v-if="imagePreview"
                                class="image-preview"
                                :src="imagePreview"
                                alt="Pratinjau foto barang"
                            />
                        </div>

                        <div class="field-group field-wide">
                            <label for="item-type">Jenis barang <span>*</span></label>
                            <select id="item-type" v-model="form.is_borrowable" required>
                                <option value="" disabled>Pilih jenis barang</option>
                                <option v-for="type in itemTypes" :key="type.label" :value="type.value">{{ type.label }}</option>
                            </select>
                            <small class="field-hint">Jenis barang menentukan formulir transaksi QR tempat barang muncul.</small>
                            <small v-if="form.errors.is_borrowable" class="field-error">{{ form.errors.is_borrowable }}</small>
                        </div>

                        <div class="field-group field-wide">
                            <label for="item-description">Keterangan <span class="optional-label">Opsional</span></label>
                            <textarea
                                id="item-description"
                                v-model="form.description"
                                rows="2"
                                maxlength="2000"
                                placeholder="Tambahkan spesifikasi atau keterangan barang..."
                            ></textarea>
                            <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button
                            class="secondary-button"
                            type="button"
                            :disabled="form.processing"
                            @click="closeForm"
                        >
                            Selesai
                        </button>

                        <button
                            class="secondary-button"
                            type="button"
                            :disabled="form.processing || categories.length === 0"
                            @click="submitForm(true)"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan & Tambah Lagi' }}
                        </button>

                        <button
                            class="primary-button"
                            type="submit"
                            :disabled="form.processing || categories.length === 0"
                        >
                            <IconDeviceFloppy :size="18" />
                            {{ form.processing ? 'Menyimpan...' : 'Simpan barang' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div v-if="showDetails && selectedItem" class="modal-backdrop" @click.self="closeDetails">
            <section class="form-modal detail-modal" role="dialog" aria-modal="true" aria-labelledby="detail-title">
                <div class="modal-heading">
                    <div>

                        <h2 id="detail-title">{{ selectedItem.name }}</h2>
                        <p>{{ selectedItem.code }} · {{ selectedItem.category?.name ?? 'Tanpa kategori' }}</p>
                    </div>
                    <button class="modal-close" type="button" aria-label="Tutup detail barang" @click="closeDetails">
                        <IconX :size="20" />
                    </button>
                </div>
                <div class="item-details">
                    <img
                        v-if="selectedItem.image_url"
                        :src="selectedItem.image_url"
                        :alt="`Foto ${selectedItem.name}`"
                    />
                    <div class="detail-facts">
                        <p><strong>Stok:</strong> {{ numberFormat(selectedItem.stock_on_hand) }} {{ selectedItem.unit }}</p>
                        <p><strong>Status stok:</strong> {{ stockLabel(selectedItem.stock_status) }}</p>
                        <p><strong>Status barang:</strong> {{ selectedItem.is_active ? 'Aktif' : 'Diarsipkan' }}</p>
                        <p><strong>Jenis barang:</strong> {{ typeLabel(selectedItem.is_borrowable) }}</p>
                        <p><strong>Keterangan:</strong> {{ selectedItem.description || 'Tidak ada keterangan.' }}</p>
                    </div>
                </div>
                <form v-if="canUpdateItemType" class="type-editor" @submit.prevent="submitItemType">
                    <div class="field-group">
                        <label for="edit-item-type">Ubah jenis barang</label>
                        <select id="edit-item-type" v-model="typeForm.is_borrowable" required>
                            <option v-for="type in itemTypes" :key="type.label" :value="type.value">{{ type.label }}</option>
                        </select>
                        <small v-if="typeForm.errors.is_borrowable" class="field-error">{{ typeForm.errors.is_borrowable }}</small>
                    </div>
                    <button class="primary-button" type="submit" :disabled="typeForm.processing || typeForm.is_borrowable === selectedItem.is_borrowable">Simpan jenis</button>
                </form>
                <div v-else class="modal-footer">
                    <button class="secondary-button" type="button" @click="closeDetails">Tutup</button>
                </div>
            </section>
        </div>

        <div v-if="showStockForm && stockItem" class="modal-backdrop" @click.self="closeStockForm">
            <section class="form-modal" role="dialog" aria-modal="true" aria-labelledby="stock-form-title">
                <div class="modal-heading">
                    <div>

                        <h2 id="stock-form-title">Tambah stok</h2>
                        <p>{{ stockItem.name }} · {{ typeLabel(stockItem.is_borrowable) }} · Stok {{ numberFormat(stockItem.stock_on_hand) }} {{ stockItem.unit }}</p>
                    </div>
                    <button class="modal-close" type="button" aria-label="Tutup formulir" @click="closeStockForm">
                        <IconX :size="20" />
                    </button>
                </div>

                <form @submit.prevent="submitStockForm">
                    <div class="form-body">
                        <div class="field-group">
                            <label for="stock-quantity">Jumlah barang masuk <span>*</span></label>
                            <input
                                id="stock-quantity"
                                v-model.number="stockForm.quantity"
                                type="number"
                                min="1"
                                step="1"
                                required
                            />
                            <small v-if="stockForm.errors.quantity" class="field-error">{{ stockForm.errors.quantity }}</small>
                        </div>

                        <div class="field-group">
                            <label for="stock-notes">Catatan <span class="optional-label">Opsional</span></label>
                            <textarea id="stock-notes" v-model="stockForm.notes" rows="3" maxlength="2000"></textarea>
                            <small v-if="stockForm.errors.notes" class="field-error">{{ stockForm.errors.notes }}</small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="secondary-button" type="button" :disabled="stockForm.processing" @click="closeStockForm">Batal</button>
                        <button class="primary-button" type="submit" :disabled="stockForm.processing">
                            <IconDeviceFloppy :size="18" />
                            {{ stockForm.processing ? 'Menyimpan...' : 'Catat stok masuk' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>

<style scoped>
.heading-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.row-actions { display: flex; justify-content: flex-end; align-items: center; gap: 6px; }
.row-action-button {
    display: grid;
    width: 32px;
    height: 32px;
    place-items: center;
    padding: 0;
    color: #fff;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
}
.inventory-workspace .row-action-button.add-action { color: #fff; background: #066fd1; border-color: #066fd1; }
.inventory-workspace .row-action-button.view-action { color: #fff; background: #425269; border-color: #425269; }
.inventory-workspace .row-action-button.delete-action { color: #fff; background: #b72b39; border-color: #b72b39; }
.inventory-workspace .row-action-button.restore-action { color: #fff; background: #287046; border-color: #287046; }
.row-action-button:hover { filter: brightness(.9); }
.row-action-button:focus-visible { outline: 2px solid #182433; outline-offset: 2px; }

@media (max-width: 700px) {
    .heading-actions {
        align-self: flex-start;
    }

    .item-details { grid-template-columns: minmax(0, 1fr); }
}

.inventory-page {
    color: #182433;
    font-family: Inter, sans-serif;
    font-size: 14px;
}
.page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}
.breadcrumb {
    display: flex;
    gap: 9px;
    align-items: center;
    margin-bottom: 8px;
    color: #667382;
    font-size: 12px;
}
.breadcrumb span { color: #a0a8b2; }
.page-heading h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.6px;
}
.page-description {
    margin: 7px 0 0;
    color: #667382;
    line-height: 1.6;
}
.primary-button, .secondary-button {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid transparent;
    border-radius: 4px;
    font: inherit;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s, border-color .15s;
}
.primary-button { color: white; background: #066fd1; border-color: #066fd1; }
.primary-button:hover { background: #055eb2; border-color: #055eb2; }
.primary-button:disabled { opacity: .55; cursor: not-allowed; }
.secondary-button { color: #182433; background: white; border-color: #dce0e5; }
.secondary-button:hover { background: #f6f8fb; }
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.summary-card {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    min-width: 0;
    padding: 19px;
    background: #fff;
    border: 1px solid #e6e7e9;
    border-radius: 4px;
}
.image-preview {
    width: 100%;
    max-width: 220px;
    max-height: 160px;
    object-fit: contain;
    border: 1px solid #e6e7e9;
    border-radius: 4px;
}
.item-details { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; padding: 20px 22px; }
.item-details > img { width: 100%; max-height: 240px; object-fit: contain; border: 1px solid #e6e7e9; border-radius: 4px; }
.detail-facts p { margin: 0 0 12px; color: #667382; line-height: 1.6; }
.detail-facts strong { color: #182433; }
.summary-icon {
    display: grid;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 4px;
}
.icon-blue { color: #066fd1; background: #e8f2fc; }
.icon-green { color: #2fb344; background: #eaf7ed; }
.icon-orange { color: #f76707; background: #fff0e5; }
.icon-red { color: #d63939; background: #fdecec; }
.summary-content { display: flex; flex-direction: column; min-width: 0; }
.summary-label { color: #667382; font-size: 12px; }
.summary-content strong { margin: 5px 0 3px; font-size: 25px; font-weight: 600; line-height: 1.2; }
.summary-footnote { color: #8793a1; font-size: 11px; }
.table-card { overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 4px; }
.table-card-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px 22px;
}
.table-card-heading h2 { margin: 0; font-size: 16px; font-weight: 600; }
.table-card-heading p { margin: 6px 0 0; color: #667382; font-size: 12px; }
.record-count { color: #667382; font-size: 12px; }
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    padding: 0 22px 18px;
}
.search-field, .select-field {
    display: flex;
    align-items: center;
    gap: 9px;
    height: 38px;
    padding: 0 11px;
    color: #667382;
    border: 1px solid #dce0e5;
    border-radius: 4px;
    background: #fff;
}
.search-field { flex: 1 1 270px; }
.search-field input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    color: #182433;
    background: transparent;
    font: inherit;
}
.search-field input::placeholder { color: #9aa4af; }
.search-field button, .flash-message button {
    display: inline-grid;
    place-items: center;
    padding: 2px;
    border: 0;
    color: inherit;
    background: transparent;
    cursor: pointer;
}
.select-field { flex: 0 1 190px; }
.select-field select, .status-filter {
    min-width: 0;
    border: 0;
    outline: 0;
    color: #182433;
    background: transparent;
    font: inherit;
}
.select-field select { width: 100%; }
.status-filter {
    height: 38px;
    padding: 0 11px;
    border: 1px solid #dce0e5;
    border-radius: 4px;
    background: white;
}
.table-scroll { width: 100%; overflow-x: auto; }
.inventory-table { width: 100%; min-width: 620px; border-collapse: collapse; text-align: left; }
.inventory-table th:first-child { width: 34%; }
.inventory-table .actions-cell { width: 1%; text-align: right; }
.inventory-table thead { background: #f6f8fb; }
.inventory-table th {
    padding: 12px 17px;
    color: #667382;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .15px;
    border-top: 1px solid #e6e7e9;
    border-bottom: 1px solid #e6e7e9;
}
.inventory-table td { padding: 11px 17px; border-bottom: 1px solid #edf0f2; color: #465363; vertical-align: middle; }
.inventory-table tbody tr:last-child td { border-bottom: 0; }
.inventory-table tbody tr:hover { background: #fbfcfe; }
.item-identity { display: flex; align-items: center; gap: 11px; }
.item-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; margin-top: 3px; }
.item-avatar {
    display: grid;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    place-items: center;
    color: #066fd1;
    background: #eef5fc;
    border: 1px solid #e2edf9;
    border-radius: 10px;
    overflow: hidden;
}
.item-avatar img { width: 100%; height: 100%; object-fit: cover; }
.item-name { color: #182433; font-weight: 600; overflow-wrap: anywhere; }
.item-code { color: #667382; font-size: 11px; }
.archived-label { padding: 1px 6px; border-radius: 999px; background: #edf1f6; color: #485568; font-size: 10px; }
.stock-quantity { display: flex; align-items: baseline; gap: 4px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.stock-quantity strong { color: #182433; font-weight: 650; }
.stock-quantity small { color: #667382; font-size: 11px; }
.stock-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
}
.status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.stock-available { color: #218838; background: #eaf7ed; }
.stock-low { color: #bd5707; background: #fff0e5; }
.stock-out { color: #c02c2c; background: #fdecec; }
.item-type { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #edf1f6; color: #485568; font-size: 11px; white-space: nowrap; }
.item-type.borrowable { background: #e9f0f9; color: #045db0; }
.empty-state { display: flex; align-items: center; flex-direction: column; padding: 48px 20px; text-align: center; white-space: normal; }
.empty-icon { display: grid; width: 54px; height: 54px; place-items: center; margin-bottom: 15px; color: #667382; background: #f1f4f8; border-radius: 50%; }
.empty-state h3 { margin: 0; color: #182433; font-size: 15px; font-weight: 600; }
.empty-state p { max-width: 350px; margin: 8px 0 18px; color: #667382; line-height: 1.6; }
.pagination-bar { display: flex; align-items: center; justify-content: space-between; gap: 15px; padding: 16px 22px; border-top: 1px solid #e6e7e9; }
.pagination-info { color: #667382; font-size: 12px; }
.pagination-info strong { color: #182433; font-weight: 500; }
.pagination-controls { display: flex; gap: 5px; }
.page-button {
    display: grid;
    min-width: 32px;
    height: 32px;
    place-items: center;
    padding: 0 8px;
    color: #465363;
    border: 1px solid #dce0e5;
    border-radius: 4px;
    background: white;
    font: inherit;
    font-size: 12px;
    cursor: pointer;
}
.page-button:hover:not(:disabled) { color: #066fd1; border-color: #066fd1; }
.page-button:disabled { opacity: .4; cursor: not-allowed; }
.page-button.page-active { color: white; background: #066fd1; border-color: #066fd1; }
.flash-message { display: flex; align-items: center; gap: 9px; margin-bottom: 18px; padding: 12px 14px; color: #218838; background: #eaf7ed; border: 1px solid #ccebd2; border-radius: 4px; }
.flash-message button { margin-left: auto; }
.modal-backdrop {
    position: fixed;
    z-index: 1000;
    inset: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow-y: auto;
    padding: 24px;
    background: rgb(24 36 51 / 42%);
}
.form-modal {
    width: 100%;
    max-width: 600px;
    max-height: calc(100vh - 48px);
    overflow-y: auto;
    background: white;
    border: 1px solid #e6e7e9;
    border-radius: 5px;
    box-shadow: 0 12px 40px rgb(24 36 51 / 18%);
}
.add-item-backdrop {
    align-items: flex-start;
    scrollbar-width: none;
}
.add-item-backdrop::-webkit-scrollbar { display: none; }
.form-modal.add-item-modal {
    flex: none;
    max-width: 660px;
    max-height: none;
    margin-block: auto;
    overflow: visible;
}
.add-item-modal .modal-heading { align-items: center; padding-block: 14px; }
.add-item-modal .modal-heading h2 { margin: 0; }
.add-item-modal .form-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 14px; padding-block: 14px; }
.add-item-modal .field-wide { grid-column: 1 / -1; }
.add-item-modal .form-two-columns { display: contents; }
.add-item-modal .field-group { gap: 4px; }
.add-item-modal .image-preview { max-width: 104px; max-height: 72px; }
.type-editor { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid #e6e7e9; }
.type-editor .field-group { flex: 1 1 180px; }
.add-item-modal .field-group textarea { min-height: 58px; }
.add-item-modal .modal-footer { padding-block: 10px; border-radius: 0 0 19px 19px; }
.modal-heading { display: flex; justify-content: space-between; gap: 15px; padding: 23px 25px 20px; border-bottom: 1px solid #e6e7e9; }
.modal-eyebrow { color: #066fd1; font-size: 10px; font-weight: 600; letter-spacing: 1px; }
.modal-heading h2 { margin: 7px 0 0; font-size: 20px; font-weight: 600; }
.modal-heading p { margin: 6px 0 0; color: #667382; font-size: 12px; line-height: 1.5; }
.modal-close { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; color: #667382; background: white; border: 1px solid #e6e7e9; border-radius: 4px; cursor: pointer; }
.form-body { display: flex; flex-direction: column; gap: 18px; padding: 22px 25px; }
.field-group { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.field-group > label { color: #344054; font-size: 12px; font-weight: 500; }
.field-group > label > span:first-child { color: #d63939; }
.field-group input, .field-group select, .field-group textarea {
    width: 100%;
    min-height: 39px;
    padding: 9px 11px;
    color: #182433;
    border: 1px solid #dce0e5;
    border-radius: 4px;
    outline: none;
    background: #fff;
    font: inherit;
    font-size: 13px;
}
.field-group textarea { min-height: 80px; resize: vertical; }
.field-group input:focus, .field-group select:focus, .field-group textarea:focus { border-color: #066fd1; box-shadow: 0 0 0 2px rgb(6 111 209 / 10%); }
.field-group input::placeholder, .field-group textarea::placeholder { color: #9aa4af; }
.form-two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.field-error { color: #d63939; font-size: 11px; line-height: 1.5; }
.field-hint { color: #8793a1; font-size: 11px; line-height: 1.5; }
.optional-label { margin-left: 5px; color: #8793a1; font-weight: 400; }
.modal-footer { display: flex; justify-content: flex-end; gap: 9px; padding: 16px 25px; border-top: 1px solid #e6e7e9; background: #fbfcfe; }
@media (max-width: 1100px) {
    .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 470px) {
    .add-item-modal .form-body { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 700px) {
    .page-heading { flex-direction: column; align-items: stretch; }
    .page-heading .primary-button { align-self: flex-start; }
    .summary-grid { gap: 10px; }
    .summary-card { padding: 13px; gap: 10px; }
    .summary-icon { flex-basis: 34px; width: 34px; height: 34px; }
    .summary-content strong { font-size: 21px; }
    .filter-bar { padding: 0 14px 15px; }
    .search-field, .select-field, .status-filter { flex: 1 1 100%; width: 100%; }
    .table-card-heading { padding: 17px 14px; }
    .inventory-table th, .inventory-table td { padding: 12px 13px; }
    .pagination-bar { flex-direction: column; align-items: flex-start; padding: 14px; }
    .form-two-columns { grid-template-columns: 1fr; }
    .modal-backdrop { align-items: flex-start; padding: 12px; }
    .form-modal { max-height: calc(100vh - 24px); }
    .modal-heading, .form-body { padding-left: 17px; padding-right: 17px; }
    .modal-footer { padding: 14px 17px; }
}
</style>

<style scoped src="../../../css/inventory.css"></style>
