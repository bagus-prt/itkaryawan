<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { IconArrowLeft, IconArrowRight, IconCheck, IconChevronUp, IconMinus, IconPackage, IconPlus } from '@tabler/icons-vue'
import LeaveSignaturePad from '@/Components/LeaveSignaturePad.vue'

const props = defineProps({
    items: { type: Array, default: () => [] },
    consumables: { type: Array, default: () => [] },
    activeLoans: { type: Array, default: () => [] },
    borrowerPhoneLookup: { type: String, default: '' },
    successType: { type: String, default: null },
})

const borrowForm = useForm({
    items: [],
    borrower_name: '',
    division: '',
    borrower_phone: '',
    signature: '',
})

const lookupForm = useForm({
    borrower_phone: props.borrowerPhoneLookup,
})

const returnForm = useForm({
    loan_item_id: props.activeLoans[0]?.id ?? '',
    borrower_phone: props.borrowerPhoneLookup,
    quantity: 1,
})

const activeTab = ref(props.borrowerPhoneLookup || props.successType === 'return' ? 'return' : null)
const transactionType = ref('loan')
const isStockOut = computed(() => transactionType.value === 'stock_out')
const catalogItems = computed(() => isStockOut.value ? props.consumables : props.items)
const heading = ref(null)
const successDialog = ref(null)
const confirmation = ref('')
const successLabels = { loan: 'Peminjaman berhasil', return: 'Pengembalian berhasil', stock_out: 'Pengambilan berhasil' }
let closeTimer
const search = ref('')
const category = ref('')
const quantities = ref({})
const signatureVersion = ref(0)
const showSelection = ref(false)
const categories = computed(() => [...new Set(catalogItems.value.map((item) => item.category))].sort())

const visibleItems = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('id-ID')

    return catalogItems.value.filter((item) =>
        (!category.value || item.category === category.value)
        && (item.name.toLocaleLowerCase('id-ID').includes(keyword) || item.code.toLocaleLowerCase('id-ID').includes(keyword))
    )
})

const selectedItems = computed(() => catalogItems.value
    .filter((item) => quantityFor(item) > 0)
    .map((item) => ({ ...item, quantity: quantityFor(item) })))

const hasStockConflict = computed(() => selectedItems.value.some((item) => item.quantity > item.stock_on_hand))

const screenTitle = computed(() => ({
    loan: isStockOut.value ? 'Pilih barang habis pakai' : 'Pilih barang',
    details: isStockOut.value ? 'Informasi pengambil' : 'Informasi peminjam',
    return: 'Kembalikan barang',
})[activeTab.value] ?? 'Transaksi inventaris')

async function changeScreen(screen) {
    showSelection.value = false
    activeTab.value = screen
    await nextTick()
    heading.value?.focus({ preventScroll: true })
    window.scrollTo(0, 0)
}

function openCatalog(type) {
    if (transactionType.value !== type) {
        quantities.value = {}
        search.value = ''
        category.value = ''
        borrowForm.reset('signature')
        signatureVersion.value++
        borrowForm.clearErrors()
        transactionType.value = type
    }
    changeScreen('loan')
}

function clearSuccessTimer() {
    window.clearTimeout(closeTimer)
}

async function showConfirmation(type) {
    if (!successLabels[type]) return
    confirmation.value = successLabels[type]
    await nextTick()
    if (!successDialog.value) return
    clearSuccessTimer()
    if (!successDialog.value.open) successDialog.value.showModal()
    closeTimer = window.setTimeout(() => successDialog.value?.close(), 3000)
}

onMounted(() => showConfirmation(props.successType))
onBeforeUnmount(clearSuccessTimer)

const selectionError = computed(() =>
    borrowForm.errors.items || borrowForm.errors.inventory_item_id || borrowForm.errors.quantity
    || Object.entries(borrowForm.errors).find(([key]) => key.startsWith('items.'))?.[1]
)

function quantityFor(item) {
    return quantities.value[item.id] ?? 0
}

function changeQuantity(item, change) {
    quantities.value[item.id] = Math.max(0, Math.min(item.stock_on_hand, quantityFor(item) + change))
    clearSelectionErrors()
}

function removeItem(item) {
    quantities.value[item.id] = 0
    clearSelectionErrors()
}

function clearSelectionErrors() {
    const fields = Object.keys(borrowForm.errors).filter((key) =>
        key === 'items' || key === 'inventory_item_id' || key === 'quantity' || key.startsWith('items.')
    )
    if (fields.length) borrowForm.clearErrors(...fields)
}

const selectedLoan = computed(() =>
    props.activeLoans.find((loan) => loan.id === Number(returnForm.loan_item_id)),
)

function submitBorrow() {
    if (!selectedItems.value.length || hasStockConflict.value) return

    borrowForm.items = selectedItems.value.map((item) => ({
        inventory_item_id: item.id,
        quantity: item.quantity,
    }))

    borrowForm.transform((data) => ({ ...data, type: transactionType.value })).post('/i/transaksi', {
        preserveScroll: true,
        onSuccess: (page) => {
            borrowForm.reset()
            quantities.value = {}
            signatureVersion.value++
            activeTab.value = null
            showSelection.value = false
            window.scrollTo(0, 0)
            showConfirmation(page.props.successType)
        },
    })
}

function searchLoans() {
    lookupForm.post('/i/transaksi/cari-pinjaman', {
        preserveScroll: true,
        onSuccess: () => {
            returnForm.borrower_phone = lookupForm.borrower_phone
            returnForm.loan_item_id = props.activeLoans[0]?.id ?? ''
            returnForm.quantity = 1
            returnForm.clearErrors()
        },
    })
}

function submitReturn() {
    returnForm.post('/i/transaksi/kembali', {
        preserveScroll: true,
        onSuccess: (page) => {
            returnForm.reset('quantity')
            showConfirmation(page.props.successType)
        },
    })
}

function numberFormat(value) {
    return new Intl.NumberFormat('id-ID').format(value ?? 0)
}
</script>

<template>
    <main class="public-page inventory-workspace" :class="{ 'has-selection-bar': activeTab === 'loan' }">
        <header class="public-header">
            <div class="brand-mark"><IconPackage :size="22" /></div>
            <div>
                <strong>Inventaris IT Karyawan</strong>
                <span>Transaksi barang melalui QR</span>
            </div>
        </header>

        <section class="item-card">
            <button
                v-if="activeTab"
                class="back-button"
                type="button"
                :disabled="borrowForm.processing || returnForm.processing || lookupForm.processing"
                @click="changeScreen(activeTab === 'details' ? 'loan' : null)"
            >
                <IconArrowLeft :size="17" />
                {{ activeTab === 'details' ? 'Kembali ke daftar barang' : 'Kembali ke pilihan transaksi' }}
            </button>
            <h1 ref="heading" tabindex="-1">{{ screenTitle }}</h1>
            <p v-if="!activeTab" class="page-intro">Pilih transaksi yang ingin dicatat.</p>

            <nav v-if="!activeTab" class="transaction-options" aria-label="Jenis transaksi">
                <button type="button" @click="openCatalog('loan')">
                    <span>
                        <strong>Peminjaman</strong>
                        <span>Pilih barang yang ingin dipinjam.</span>
                    </span>
                    <IconArrowRight :size="20" aria-hidden="true" />
                </button>
                <button type="button" @click="changeScreen('return')">
                    <span>
                        <strong>Pengembalian</strong>
                        <span>Cari pinjaman aktif dengan nomor HP.</span>
                    </span>
                    <IconArrowRight :size="20" aria-hidden="true" />
                </button>
                <button type="button" @click="openCatalog('stock_out')">
                    <span>
                        <strong>Ambil Barang Habis Pakai</strong>
                        <span>Catat barang yang diambil untuk digunakan.</span>
                    </span>
                    <IconArrowRight :size="20" aria-hidden="true" />
                </button>
            </nav>

            <div v-show="activeTab" class="action-grid">
                <div v-show="activeTab === 'loan'">
                    <section aria-label="Daftar barang">
                        <div class="catalog-filters">
                            <label>
                                Cari barang
                                <input v-model="search" type="search" placeholder="Nama atau kode barang" />
                            </label>
                            <label>
                                Kategori
                                <select v-model="category">
                                    <option value="">Semua kategori</option>
                                    <option v-for="name in categories" :key="name">{{ name }}</option>
                                </select>
                            </label>
                        </div>

                        <div v-if="visibleItems.length" class="catalog">
                            <article v-for="item in visibleItems" :key="item.id" class="product-card">
                                <div class="product-image">
                                    <img v-if="item.image_url" :src="item.image_url" alt="" loading="lazy" />
                                    <IconPackage v-else :size="33" aria-hidden="true" />
                                </div>
                                <div class="product-info">
                                    <h3>{{ item.name }}</h3>
                                    <p>{{ item.category }}</p>
                                    <div class="product-actions">
                                        <span v-if="item.stock_on_hand === 0" class="unavailable">Habis</span>
                                        <div class="quantity-control">
                                            <button
                                                type="button"
                                                :aria-label="'Kurangi ' + item.name"
                                                :disabled="item.stock_on_hand === 0 || quantityFor(item) === 0 || borrowForm.processing"
                                                @click="changeQuantity(item, -1)"
                                            ><IconMinus :size="17" /></button>
                                            <output :aria-label="'Jumlah dipilih untuk ' + item.name">{{ numberFormat(quantityFor(item)) }}</output>
                                            <button
                                                type="button"
                                                :aria-label="'Tambah ' + item.name"
                                                :disabled="quantityFor(item) >= item.stock_on_hand || borrowForm.processing"
                                                @click="changeQuantity(item, 1)"
                                            ><IconPlus :size="17" /></button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <p v-else class="form-notice">
                            {{ catalogItems.length ? 'Tidak ada barang yang cocok dengan filter.' : isStockOut ? 'Belum ada barang habis pakai yang terdaftar.' : 'Belum ada barang yang dapat dipinjam.' }}
                        </p>
                    </section>

                    <section class="selection-bar" aria-label="Pilihan transaksi">
                        <div class="selection-bar-inner">
                            <div v-show="showSelection" id="selection-details" class="selection-details" tabindex="0" role="region" aria-label="Rincian barang yang dipilih">
                                <p v-if="!selectedItems.length" class="form-notice">Belum ada barang yang dipilih.</p>
                                <ul v-else class="selection-list">
                                    <li v-for="item in selectedItems" :key="item.id">
                                        <div>
                                            <strong>{{ item.name }}</strong>
                                            <p>{{ numberFormat(item.quantity) }} {{ item.unit }}</p>
                                            <small v-if="item.quantity > item.stock_on_hand" class="field-error">Jumlah melebihi stok terbaru.</small>
                                        </div>
                                        <button type="button" :aria-label="'Hapus ' + item.name" @click="removeItem(item)">Hapus</button>
                                    </li>
                                </ul>
                            </div>
                            <p v-if="selectionError || hasStockConflict" class="field-error selection-error" role="alert">
                                {{ selectionError || 'Stok berubah. Sesuaikan jumlah yang dipilih.' }}
                            </p>
                            <div class="selection-bar-actions">
                                <button class="selection-toggle" type="button" :aria-expanded="showSelection" aria-controls="selection-details" @click="showSelection = !showSelection">
                                    <span class="selection-summary">
                                        <strong>{{ isStockOut ? 'Daftar pengambilan' : 'Daftar peminjaman' }}</strong>
                                        <span aria-live="polite">{{ numberFormat(selectedItems.length) }} jenis barang dipilih</span>
                                    </span>
                                    <IconChevronUp :size="18" aria-hidden="true" />
                                </button>
                                <button class="submit-borrow" type="button" :disabled="!selectedItems.length || hasStockConflict" @click="changeScreen('details')">
                                    {{ isStockOut ? 'Lanjutkan Pengambilan' : 'Pinjam Barang' }} <IconArrowRight :size="17" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <form v-show="activeTab === 'details'" class="surface" :aria-label="screenTitle" @submit.prevent="submitBorrow">
                    <p class="selected-count">{{ numberFormat(selectedItems.length) }} jenis barang dipilih</p>
                    <div class="borrower-fields">
                        <label>
                            {{ isStockOut ? 'Nama pengambil' : 'Nama peminjam' }}
                            <input v-model="borrowForm.borrower_name" type="text" maxlength="150" autocomplete="name" required />
                            <small v-if="borrowForm.errors.borrower_name" class="field-error">{{ borrowForm.errors.borrower_name }}</small>
                        </label>
                        <label>
                            Bagian / instansi
                            <input v-model="borrowForm.division" type="text" maxlength="100" required />
                            <small v-if="borrowForm.errors.division" class="field-error">{{ borrowForm.errors.division }}</small>
                        </label>
                        <label>
                            Nomor HP
                            <input v-model="borrowForm.borrower_phone" type="tel" maxlength="30" autocomplete="tel" required />
                            <small v-if="borrowForm.errors.borrower_phone" class="field-error">{{ borrowForm.errors.borrower_phone }}</small>
                        </label>
                    </div>
                    <LeaveSignaturePad
                        :key="signatureVersion"
                        v-model="borrowForm.signature"
                        :label="isStockOut ? 'Tanda tangan pengambil' : 'Tanda tangan peminjam'"
                        :error="borrowForm.errors.signature"
                    />
                    <p v-if="selectionError || hasStockConflict" class="field-error" role="alert">
                        {{ selectionError || 'Stok berubah. Kembali ke daftar barang untuk menyesuaikan jumlah.' }}
                    </p>
                    <button class="submit-borrow" type="submit" :disabled="borrowForm.processing || !selectedItems.length || hasStockConflict">
                        {{ borrowForm.processing ? 'Mencatat...' : isStockOut ? 'Catat Pengambilan' : 'Catat Peminjaman' }}
                    </button>
                    <p class="privacy-note">Data ini digunakan untuk mencatat transaksi inventaris.</p>
                </form>

                <section v-show="activeTab === 'return'" class="action-card">
                    <div class="section-heading">
                        <div>
                            <p>Cari pinjaman aktif dengan nomor HP yang digunakan saat meminjam.</p>
                        </div>
                    </div>

                    <form class="transaction-form lookup-form" @submit.prevent="searchLoans">
                        <label>
                            Nomor HP peminjam <span>*</span>
                            <input v-model="lookupForm.borrower_phone" type="tel" maxlength="30" autocomplete="tel" required />
                            <small v-if="lookupForm.errors.borrower_phone">{{ lookupForm.errors.borrower_phone }}</small>
                        </label>
                        <button type="submit" :disabled="lookupForm.processing">
                            {{ lookupForm.processing ? 'Mencari...' : 'Cari pinjaman aktif' }}
                        </button>
                    </form>

                    <form v-if="activeLoans.length > 0" class="transaction-form return-form" @submit.prevent="submitReturn">
                        <label>
                            Peminjaman aktif <span>*</span>
                            <select v-model="returnForm.loan_item_id" required>
                                <option value="" disabled>Pilih barang yang dikembalikan</option>
                                <option v-for="loan in activeLoans" :key="loan.id" :value="loan.id">
                                    {{ loan.item.name }} · sisa {{ numberFormat(loan.outstanding_quantity) }} {{ loan.item.unit }}
                                </option>
                            </select>
                            <small v-if="returnForm.errors.loan_item_id">{{ returnForm.errors.loan_item_id }}</small>
                        </label>
                        <label>
                            Jumlah yang dikembalikan <span>*</span>
                            <input
                                v-model.number="returnForm.quantity"
                                type="number"
                                min="1"
                                :max="selectedLoan?.outstanding_quantity"
                                required
                            />
                            <small v-if="returnForm.errors.quantity">{{ returnForm.errors.quantity }}</small>
                            <small v-if="returnForm.errors.borrower_phone">{{ returnForm.errors.borrower_phone }}</small>
                        </label>
                        <button type="submit" :disabled="returnForm.processing || !selectedLoan">
                            {{ returnForm.processing ? 'Mencatat...' : 'Catat pengembalian' }}
                        </button>
                    </form>

                    <p v-else-if="borrowerPhoneLookup" class="form-notice search-result">
                        Tidak ada peminjaman aktif yang cocok dengan nomor HP tersebut.
                    </p>
                    <p v-else class="form-notice search-result">
                        Masukkan nomor HP untuk menampilkan barang yang masih dipinjam.
                    </p>
                </section>
            </div>

        </section>

        <dialog ref="successDialog" class="success-dialog" aria-labelledby="success-title" @close="clearSuccessTimer">
            <span class="success-icon"><IconCheck :size="24" aria-hidden="true" /></span>
            <h2 id="success-title">{{ confirmation }}</h2>
            <form method="dialog">
                <button class="submit-borrow" type="submit" autofocus>OK</button>
            </form>
        </dialog>
    </main>
</template>

<style scoped>
.public-page {
    min-height: 100vh;
    padding: 28px 16px 60px;
    background: #f2f4f8;
    color: #182433;
    font-family: Inter, ui-sans-serif, system-ui, sans-serif;
}
.public-header, .item-card { width: min(100%, 620px); margin-inline: auto; }
.public-header { display: flex; align-items: center; gap: 11px; margin-bottom: 24px; }
.brand-mark { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 14px; background: #e9f0f9; color: #045db0; }
.public-header strong, .public-header span { display: block; }
.public-header strong { font-size: 14px; }
.public-header span { color: #667382; font-size: 11px; }
h1 { font-size: clamp(23px, 5vw, 30px); font-weight: 650; letter-spacing: -.025em; }
.page-intro { margin-top: 5px; color: #586678; font-size: 14px; }
.back-button { color: #045db0; font-size: 13px; font-weight: 600; cursor: pointer; }
.back-button { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; }
.transaction-options, .catalog { display: grid; gap: 14px; }
.transaction-options { margin-top: 20px; }
.transaction-options button, .surface, .action-card, .product-card {
    border: 1px solid #e7ebf1;
    background: #fff;
    box-shadow: 0 5px 18px rgb(32 51 79 / 6%);
}
.transaction-options button {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-radius: 20px;
    padding: 20px;
    text-align: left;
    cursor: pointer;
}
.transaction-options button:hover { border-color: #a7c7eb; }
.transaction-options strong, .transaction-options span span { display: block; }
.transaction-options strong { font-size: 16px; font-weight: 650; }
.transaction-options span span { margin-top: 4px; color: #586678; font-size: 13px; }
.transaction-options svg { flex: none; color: #066fd1; }
.action-grid { margin-top: 18px; }
.surface, .action-card { min-width: 0; padding: clamp(16px, 4vw, 24px); border-radius: 22px; }
.public-page.has-selection-bar { padding-bottom: calc(142px + env(safe-area-inset-bottom, 0px)); }
.selection-bar {
    position: fixed;
    z-index: 10;
    inset: auto 0 0;
    padding: 10px 16px calc(10px + env(safe-area-inset-bottom, 0px));
    border-top: 1px solid #e1e8f0;
    background: #fff;
    box-shadow: 0 -5px 20px rgb(24 36 51 / 9%);
}
.selection-bar-inner { position: relative; width: min(100%, 620px); margin-inline: auto; }
.selection-bar-actions { display: flex; align-items: center; gap: 12px; }
.selection-toggle { display: flex; flex: 1; align-items: center; justify-content: space-between; gap: 5px; min-height: 44px; padding: 4px 6px; border-radius: 8px; text-align: left; cursor: pointer; }
.selection-summary { display: grid; }
.selection-summary strong { font-size: 13px; }
.selection-summary > span { color: #586678; font-size: 12px; }
.selection-toggle svg { flex: none; color: #045db0; }
.selection-toggle[aria-expanded='true'] svg { transform: rotate(180deg); }
.selection-bar .submit-borrow { width: auto; margin: 0; }
.selection-details {
    position: absolute;
    inset: auto 0 calc(100% + 16px);
    max-height: min(42dvh, 300px);
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 10px 14px;
    border: 1px solid #e1e8f0;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 5px 24px rgb(24 36 51 / 14%);
}
.selection-error { margin-bottom: 5px; }
.catalog-filters { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 10px; margin-bottom: 14px; }
.borrower-fields, .transaction-form { display: grid; gap: 13px; }
.borrower-fields { margin-bottom: 19px; }
.catalog-filters label, .borrower-fields label, .transaction-form label { display: grid; align-content: start; gap: 6px; color: #344054; font-size: 13px; font-weight: 550; }
.catalog-filters input, .catalog-filters select, .borrower-fields input, .transaction-form input, .transaction-form select {
    width: 100%;
    min-width: 0;
    min-height: 44px;
    padding: 9px 12px;
    border: 1px solid #dce3eb;
    border-radius: 11px;
    background: #fff;
    color: #182433;
}
:where(input, select, button):focus-visible { outline: 2px solid #066fd1; outline-offset: 2px; }
.product-card {
    display: grid;
    grid-template-columns: clamp(90px, 25vw, 150px) minmax(0, 1fr);
    gap: clamp(12px, 4vw, 22px);
    min-height: 126px;
    padding: 11px;
    border-radius: 24px;
}
.product-image { display: grid; place-items: center; min-height: 104px; overflow: hidden; border-radius: 16px; background: #f5f7fa; color: #667382; }
.product-image img { display: block; width: 100%; aspect-ratio: 1; height: auto; object-fit: contain; }
.product-info { display: flex; flex-direction: column; align-items: flex-start; min-width: 0; padding: 4px 6px 2px 0; }
.product-info h3 { font-size: clamp(15px, 3.5vw, 19px); font-weight: 650; overflow-wrap: anywhere; }
.product-info p { color: #667382; font-size: 13px; }
.product-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; width: 100%; margin-top: auto; padding-top: 8px; }
.unavailable { color: #667382; font-size: 11px; }
.quantity-control { display: inline-flex; align-items: center; flex-shrink: 0; gap: 2px; padding: 2px; border-radius: 999px; background: #edf4fc; color: #045db0; }
.quantity-control button { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; }
.quantity-control button:hover:not(:disabled) { background: #dbeaf9; }
.quantity-control button:disabled { opacity: .38; cursor: not-allowed; }
.quantity-control output { min-width: 20px; text-align: center; font-size: 13px; font-weight: 650; }
.selection-list li { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding-block: 9px; border-bottom: 1px solid #e7ebf1; font-size: 13px; }
.selection-list li p, .selected-count { color: #586678; }
.selection-list button { padding: 4px 8px; color: #045db0; cursor: pointer; }
.selected-count { margin-bottom: 15px; font-size: 13px; }
.field-error, .transaction-form small { color: #b22727; font-size: 12px; }
.submit-borrow, .transaction-form button {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    width: 100%;
    min-height: 44px;
    margin-top: 12px;
    padding: 8px 14px;
    border-radius: 11px;
    background: #066fd1;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}
.submit-borrow:disabled, .transaction-form button:disabled { opacity: .55; cursor: not-allowed; }
.section-heading { margin-bottom: 14px; color: #586678; font-size: 13px; }
.return-form { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e7ebf1; }
.form-notice { padding: 10px 12px; border-radius: 10px; background: #f6f8fb; color: #586678; font-size: 13px; }
.search-result, .privacy-note { margin-top: 14px; }
.privacy-note { color: #667382; font-size: 11px; }
.success-dialog {
    position: fixed;
    inset: 0;
    width: min(100% - 32px, 360px);
    max-height: calc(100dvh - 32px);
    margin: auto;
    padding: 24px;
    border: 0;
    border-radius: 23px;
    background: #fff;
    text-align: center;
    box-shadow: 0 16px 56px rgb(24 36 51 / 20%);
}
.success-dialog::backdrop { background: rgb(24 36 51 / 34%); }
.success-dialog h2 { margin-top: 10px; font-size: 19px; font-weight: 650; }
.success-icon { display: inline-grid; width: 46px; height: 46px; place-items: center; border-radius: 50%; background: #e9f0f9; color: #045db0; }
@media (max-width: 360px) {
    .catalog-filters { grid-template-columns: minmax(0, 1fr); }
    .product-actions { flex-wrap: wrap; }
    .selection-bar-actions { display: grid; gap: 4px; }
    .selection-bar .submit-borrow { width: 100%; }
}
</style>

<style scoped src="../../../css/inventory.css"></style>
