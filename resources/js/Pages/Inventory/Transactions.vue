<script setup>
import { computed, nextTick, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { IconArrowDown, IconArrowUp, IconChevronDown, IconCornerDownLeft, IconMinus, IconPackage, IconPlus } from '@tabler/icons-vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import TablePagination from '@/Components/TablePagination.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    transactions: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    activeLoans: { type: Array, default: () => [] },
    isAdmin: Boolean,
    typeFilter: { type: String, default: '' },
})

const page = usePage()
const actions = [
    { type: 'stock_in', label: 'Tambah Stok', icon: IconArrowDown },
    { type: 'stock_out', label: 'Barang Keluar', icon: IconArrowUp },
    { type: 'loan', label: 'Peminjaman', icon: IconArrowUp },
    { type: 'return', label: 'Pengembalian', icon: IconCornerDownLeft },
]
const availableActions = computed(() => actions.filter(({ type }) => props.isAdmin || ['loan', 'stock_out'].includes(type)))
const dialog = ref(null)
const message = ref('')
const number = new Intl.NumberFormat('id-ID')
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
const form = useForm({ type: '', inventory_item_id: '', quantity: 1, items: [], loan_ids: [] })
const search = ref('')
const category = ref('')
const quantities = ref({})
const movementItems = computed(() => props.items.filter((item) => item.is_borrowable === (form.type === 'loan')))
const categories = computed(() => [...new Set(movementItems.value.map((item) => item.category).filter(Boolean))].sort())
const visibleItems = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('id-ID')
    return movementItems.value.filter((item) => (!category.value || item.category === category.value)
        && [item.name, item.code].some((text) => text.toLocaleLowerCase('id-ID').includes(keyword)))
})
const selectedItems = computed(() => movementItems.value
    .filter((item) => quantities.value[item.id] > 0)
    .map((item) => ({ ...item, quantity: quantities.value[item.id] })))
const stockConflict = computed(() => selectedItems.value.some((item) => item.quantity > item.stock_on_hand))
const loanGroups = computed(() => {
    const groups = new Map()
    for (const line of props.activeLoans) {
        if (!groups.has(line.transaction_id)) groups.set(line.transaction_id, { ...line, lines: [] })
        groups.get(line.transaction_id).lines.push(line)
    }
    return [...groups.values()].sort((left, right) =>
        new Date(right.transaction_at) - new Date(left.transaction_at) || right.transaction_id - left.transaction_id)
})
const selectedItem = computed(() => props.items.find((item) => item.id === form.inventory_item_id))
const canSubmit = computed(() => !form.processing && ({
    loan: selectedItems.value.length > 0 && !stockConflict.value,
    stock_out: selectedItems.value.length > 0 && !stockConflict.value,
    return: form.loan_ids.length > 0,
    stock_in: selectedItem.value && Number.isInteger(form.quantity) && form.quantity > 0,
})[form.type])
const transactionError = computed(() => Object.values(form.errors)[0])

function changeQuantity(item, change) {
    quantities.value[item.id] = Math.max(0, Math.min(item.stock_on_hand, (quantities.value[item.id] ?? 0) + change))
    form.clearErrors()
}

async function openAction(type) {
    form.reset()
    form.clearErrors()
    form.type = type
    quantities.value = {}
    search.value = ''
    category.value = ''
    message.value = ''
    dialog.value.showModal()
    await nextTick()
    dialog.value.querySelector('input[type="search"], select, input[type="checkbox"]')?.focus()
}

function submitForm() {
    if (!canSubmit.value) return
    form.items = selectedItems.value.map(({ id, quantity }) => ({ inventory_item_id: id, quantity }))
    const query = new URLSearchParams({ history_type: props.typeFilter, per_page: props.transactions.per_page })
    form.post('/inventory/transactions?' + query, {
        preserveScroll: true,
        onSuccess: () => {
            dialog.value?.close()
            message.value = 'Transaksi berhasil dicatat.'
        },
    })
}

function filterTransactions(event) {
    router.get('/inventory/transactions', {
        type: event.target.value || undefined,
        per_page: props.transactions.per_page,
    }, { preserveScroll: true })
}

function transactionLabel(type) {
    return type === 'stock_in' ? 'Barang masuk' : actions.find((action) => action.type === type)?.label ?? type
}
</script>

<template>
    <div class="transactions-page inventory-workspace">
        <header class="page-heading">
            <h1><span class="inventory-mark"><IconPackage :size="21" aria-hidden="true" /></span>Transaksi Inventaris</h1>
            <Link v-if="isAdmin" href="/inventory" class="text-link">Data Barang</Link>
        </header>

        <div class="action-grid" aria-label="Catat transaksi">
            <button v-for="action in availableActions" :key="action.type" class="action-card" type="button" @click="openAction(action.type)">
                <span class="action-icon"><component :is="action.icon" :size="19" aria-hidden="true" /></span>
                {{ action.label }}
            </button>
        </div>
        <p v-if="message" class="notice" role="status">{{ message }}</p>

        <section class="table-card" aria-labelledby="history-heading">
            <header class="list-heading">
                <h2 id="history-heading">{{ isAdmin ? 'Riwayat transaksi' : 'Transaksi saya' }}</h2>
                <label>
                    <span class="sr-only">Filter jenis transaksi</span>
                    <select :value="typeFilter" @change="filterTransactions">
                        <option value="">Semua transaksi</option>
                        <option v-if="isAdmin" value="stock_in">Barang masuk</option>
                        <option value="stock_out">Barang keluar</option>
                        <option value="loan">Peminjaman</option>
                        <option value="return">Pengembalian</option>
                    </select>
                </label>
            </header>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Tanggal / Kode</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Barang / Jumlah</th>
                            <th v-if="isAdmin" scope="col">Peminjam / Penerima</th>
                            <th v-if="isAdmin" scope="col">Dicatat oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="transaction in transactions.data" :key="transaction.id">
                            <td>
                                <span>{{ transaction.transaction_at ? dateFormat.format(new Date(transaction.transaction_at)) : '—' }}</span>
                                <small>{{ transaction.transaction_code }}</small>
                            </td>
                            <td><span class="badge" :data-type="transaction.type">{{ transactionLabel(transaction.type) }}</span></td>
                            <td>
                                <ul>
                                    <li v-for="line in transaction.items" :key="line.id">
                                        {{ line.inventory_item?.name ?? 'Barang tidak tersedia' }}
                                        <small class="line-quantity">{{ number.format(line.quantity) }} {{ line.inventory_item?.unit }}</small>
                                    </li>
                                </ul>
                            </td>
                            <td v-if="isAdmin">
                                {{ transaction.borrower_name ?? '—' }}
                                <small v-if="transaction.division">{{ transaction.division }}</small>
                                <small v-if="transaction.signature_url">
                                    <a class="text-link" :href="transaction.signature_url" target="_blank" rel="noopener">Lihat tanda tangan</a>
                                </small>
                            </td>
                            <td v-if="isAdmin">{{ transaction.creator?.name ?? '—' }}</td>
                        </tr>
                        <tr v-if="!transactions.data.length">
                            <td :colspan="isAdmin ? 5 : 3" class="empty">Belum ada transaksi untuk ditampilkan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <TablePagination :pagination="transactions" label="transaksi" />
        </section>

        <dialog ref="dialog" class="action-dialog" :class="{ wide: form.type !== 'stock_in' }" aria-labelledby="action-title" @cancel="form.processing && $event.preventDefault()">
            <form class="action-form" @submit.prevent="submitForm">
                <h2 id="action-title">{{ form.type === 'stock_in' ? 'Tambah Stok' : transactionLabel(form.type) }}</h2>
                <p v-if="form.type === 'loan'" class="hint">Peminjam {{ page.props.auth?.user?.name }}</p>
                <template v-if="form.type === 'loan' || form.type === 'stock_out'">
                    <div class="catalog-filters">
                        <label>Cari barang <input v-model="search" type="search" placeholder="Nama atau kode" @keydown.enter.prevent /></label>
                        <label>Kategori
                            <select v-model="category">
                                <option value="">Semua kategori</option>
                                <option v-for="name in categories" :key="name">{{ name }}</option>
                            </select>
                        </label>
                    </div>
                    <div v-if="visibleItems.length" class="card-list">
                        <article v-for="item in visibleItems" :key="item.id" class="product-card">
                            <div class="product-image">
                                <img v-if="item.image_url" :src="item.image_url" alt="" loading="lazy" />
                                <IconPackage v-else :size="28" aria-hidden="true" />
                            </div>
                            <div class="product-info">
                                <strong>{{ item.name }}</strong>
                                <p class="hint">{{ item.category }}</p>
                                <p v-if="item.stock_on_hand === 0" class="hint">Habis</p>
                            </div>
                            <div class="quantity-control">
                                <button type="button" :aria-label="'Kurangi ' + item.name" :disabled="!quantities[item.id] || form.processing" @click="changeQuantity(item, -1)"><IconMinus :size="16" /></button>
                                <output :aria-label="'Jumlah ' + item.name">{{ quantities[item.id] ?? 0 }}</output>
                                <button type="button" :aria-label="'Tambah ' + item.name" :disabled="(quantities[item.id] ?? 0) >= item.stock_on_hand || form.processing" @click="changeQuantity(item, 1)"><IconPlus :size="16" /></button>
                            </div>
                        </article>
                    </div>
                    <p v-else class="hint">Tidak ada barang yang cocok.</p>
                    <ul v-if="selectedItems.length" class="selected-items" aria-label="Barang yang dipilih">
                        <li v-for="item in selectedItems" :key="item.id"><span>{{ item.name }}</span><strong>{{ item.quantity }} {{ item.unit }}</strong></li>
                    </ul>
                    <p v-if="stockConflict" class="error">Stok berubah. Sesuaikan jumlah barang yang dipilih.</p>
                </template>
                <div v-else-if="form.type === 'return'" class="card-list">
                    <p v-if="!loanGroups.length" class="hint">Tidak ada peminjaman aktif.</p>
                    <div v-for="loan in loanGroups" :key="loan.transaction_id" class="return-card">
                        <label class="return-select">
                            <input v-model="form.loan_ids" type="checkbox" :value="loan.transaction_id" :disabled="form.processing" />
                            <span>{{ dateFormat.format(new Date(loan.transaction_at)) }}</span>
                            <span class="sr-only">{{ loan.transaction_code }} {{ loan.borrower_name }}</span>
                        </label>
                        <details>
                            <summary>
                                <span><strong>{{ loan.borrower_name ?? loan.transaction_code }}</strong><small>{{ loan.transaction_code }} · {{ loan.lines.length }} jenis barang</small></span>
                                <IconChevronDown :size="17" aria-hidden="true" />
                            </summary>
                            <ul class="selected-items">
                                <li v-for="line in loan.lines" :key="line.id"><span>{{ line.item?.name }}</span><strong>{{ line.outstanding_quantity }} {{ line.item?.unit }}</strong></li>
                            </ul>
                        </details>
                    </div>
                </div>
                <template v-else>
                    <label>Barang
                        <select v-model="form.inventory_item_id" required>
                            <option value="" disabled>Pilih barang</option>
                            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }} · {{ item.code }}</option>
                        </select>
                    </label>
                    <label>Jumlah <input v-model.number="form.quantity" type="number" min="1" required /></label>
                </template>
                <p v-if="transactionError" class="error" role="alert">{{ transactionError }}</p>
                <div class="form-actions">
                    <button type="button" class="cancel" :disabled="form.processing" @click="dialog.close()">Batal</button>
                    <button type="submit" class="primary" :disabled="!canSubmit">
                        {{ form.processing ? 'Menyimpan...' : ({ loan: 'Pinjam', return: 'Kembalikan Barang', stock_out: 'Catat Barang Keluar' })[form.type] ?? 'Simpan' }}
                    </button>
                </div>
            </form>
        </dialog>
    </div>
</template>

<style scoped>
.transactions-page { display: grid; gap: 18px; color: #182433; font-size: 14px; }
.page-heading, .list-heading, .form-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.page-heading h1 { font-size: 24px; font-weight: 650; }
.text-link { color: #045db0; font-weight: 550; }
.text-link:hover { text-decoration: underline; }
.action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 170px), 1fr)); gap: 12px; }
.action-card, .table-card, .action-dialog { border: 1px solid #e6e7e9; background: #fff; box-shadow: 0 4px 16px rgb(24 36 51 / 5%); }
.action-card { display: flex; align-items: center; gap: 10px; padding: 13px; border-radius: 16px; text-align: left; font-weight: 600; cursor: pointer; }
.action-card:hover { border-color: #9bbde0; }
.action-icon { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 12px; background: #e9f0f9; color: #045db0; }
.notice { color: #20703c; font-size: 13px; }
.table-card { min-width: 0; overflow: hidden; border-radius: 16px; }
.list-heading { padding: 15px 16px; }
.list-heading h2, .action-form h2 { font-size: 16px; font-weight: 650; }
select, input { min-height: 40px; padding: 7px 10px; border: 1px solid #dce3eb; border-radius: 10px; background: #fff; color: #182433; }
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th, td { padding: 11px 14px; border-bottom: 1px solid #e6e7e9; vertical-align: top; }
th { background: #f6f8fb; color: #586678; font-size: 12px; font-weight: 650; white-space: nowrap; }
td { font-size: 13px; }
td small { display: block; color: #586678; font-size: 11px; }
td li + li { margin-top: 5px; }
.badge { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #e9f0f9; color: #045db0; white-space: nowrap; }
.badge[data-type='return'] { background: #eaf5ee; color: #23673a; }
.badge[data-type='stock_in'] { background: #f0f3f7; color: #485568; }
.badge[data-type='stock_out'] { background: #fff1e6; color: #935017; }
.empty { padding: 22px; text-align: center; color: #586678; }
.action-dialog { position: fixed; inset: 0; width: min(100% - 30px, 450px); max-height: calc(100dvh - 30px); margin: auto; padding: 21px; border-radius: 20px; }
.action-dialog::backdrop { background: rgb(24 36 51 / 33%); }
.action-form { display: grid; gap: 14px; }
.action-form label { display: grid; gap: 5px; font-weight: 550; }
.action-form :is(input:not([type='checkbox']), select) { width: 100%; min-width: 0; }
.action-dialog.wide { width: min(100% - 30px, 620px); }
.catalog-filters { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 10px; }
.card-list { display: grid; gap: 10px; }
.product-card, .return-card { border: 1px solid #e6eaf1; border-radius: 18px; background: #fff; box-shadow: 0 4px 12px rgb(24 36 51 / 5%); }
.product-card { display: grid; grid-template-columns: 76px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 9px; }
.product-image { display: grid; place-items: center; width: 76px; height: 76px; overflow: hidden; border-radius: 13px; background: #f2f5f9; color: #667382; }
.product-image img { width: 100%; height: 100%; object-fit: contain; }
.product-info { min-width: 0; overflow-wrap: anywhere; }
.quantity-control { display: flex; align-items: center; gap: 3px; padding: 2px; border-radius: 999px; background: #edf4fc; color: #045db0; }
.quantity-control button { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; }
.quantity-control button:disabled { opacity: .4; cursor: not-allowed; }
.quantity-control output { min-width: 18px; text-align: center; font-weight: 600; }
.selected-items { border-top: 1px solid #e6eaf1; }
.selected-items li { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; padding: 8px 0; font-size: 13px; }
.selected-items li strong { flex: none; }
.return-card { padding: 13px 14px; }
.action-form .return-select { display: flex; align-items: center; gap: 8px; width: fit-content; color: #586678; font-size: 12px; cursor: pointer; }
.return-select input { appearance: none; display: grid; place-items: center; flex: none; width: 21px; min-height: 21px; height: 21px; padding: 0; border: 1px solid #9aacbf; border-radius: 50%; cursor: pointer; }
.return-select input:checked { border-color: #066fd1; background: #066fd1; }
.return-select input:checked::after { content: ""; width: 9px; height: 5px; transform: translateY(-1px) rotate(-45deg); border-left: 2px solid #fff; border-bottom: 2px solid #fff; }
.return-card summary { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 0 2px; list-style: none; cursor: pointer; }
.return-card summary::-webkit-details-marker { display: none; }
.return-card summary small { display: block; color: #586678; }
.return-card details[open] summary svg { transform: rotate(180deg); }
.return-card .selected-items { margin-top: 8px; }
@media (max-width: 430px) {
    .catalog-filters { grid-template-columns: minmax(0, 1fr); }
    .product-card { grid-template-columns: 64px minmax(0, 1fr); }
    .product-image { width: 64px; height: 64px; grid-row: span 2; }
    .quantity-control { grid-column: 2; justify-self: end; }
}
.hint, .error { font-size: 12px; }
.hint { color: #586678; }
.error { color: #b22727; }
.form-actions { justify-content: flex-end; }
.form-actions button { min-height: 40px; padding: 7px 14px; border-radius: 10px; font-weight: 600; cursor: pointer; }
.cancel { border: 1px solid #dce3eb; }
.primary { background: #066fd1; color: #fff; }
.form-actions button:disabled { opacity: .54; cursor: not-allowed; }
</style>

<style scoped src="../../../css/inventory.css"></style>
