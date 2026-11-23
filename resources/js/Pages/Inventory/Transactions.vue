<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { IconChevronLeft, IconDeviceFloppy } from '@tabler/icons-vue'
import TablePagination from '@/Components/TablePagination.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    transactions: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    activeLoans: { type: Array, default: () => [] },
})

const flashMessage = ref('')
const form = useForm({
    type: 'stock_in',
    inventory_item_id: '',
    loan_item_id: '',
    quantity: 1,
    borrower_name: '',
    division: '',
    borrower_phone: '',
    notes: '',
})

const selectedLoan = computed(() =>
    props.activeLoans.find((loan) => loan.id === Number(form.loan_item_id)),
)

function submitForm() {
    form.post('/inventory/transactions', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            flashMessage.value = 'Transaksi inventaris berhasil dicatat.'
        },
    })
}

function transactionLabel(type) {
    return {
        stock_in: 'Barang masuk',
        loan: 'Peminjaman',
        return: 'Pengembalian',
    }[type] ?? type
}

function transactionClass(type) {
    return {
        stock_in: 'transaction-in',
        loan: 'transaction-loan',
        return: 'transaction-return',
    }[type] ?? ''
}

function numberFormat(value) {
    return new Intl.NumberFormat('id-ID').format(value ?? 0)
}
</script>

<template>
    <div class="transactions-page">
        <div class="page-heading">
            <div>
                <div class="breadcrumb">Inventaris <span>/</span> Transaksi</div>
                <h1>Transaksi Inventaris</h1>
                <p class="page-description">Catat barang masuk, peminjaman, dan pengembalian. Setiap transaksi memperbarui stok serta tersimpan di riwayat.</p>
            </div>
            <Link href="/inventory" class="secondary-button">
                <IconChevronLeft :size="17" />
                Master barang
            </Link>
        </div>

        <div v-if="flashMessage" class="notice notice-success" role="status">{{ flashMessage }}</div>

        <section class="transaction-form-card">
            <h2>Catat transaksi baru</h2>
            <form class="transaction-form" @submit.prevent="submitForm">
                <div class="field-group">
                    <label for="transaction-type">Jenis transaksi <span>*</span></label>
                    <select id="transaction-type" v-model="form.type">
                        <option value="stock_in">Barang masuk / tambah stok</option>
                        <option value="loan">Peminjaman</option>
                        <option value="return">Pengembalian</option>
                    </select>
                    <small v-if="form.errors.type" class="field-error">{{ form.errors.type }}</small>
                </div>

                <div v-if="form.type !== 'return'" class="field-group">
                    <label for="transaction-item">Barang <span>*</span></label>
                    <select id="transaction-item" v-model="form.inventory_item_id">
                        <option value="" disabled>Pilih barang</option>
                        <option
                            v-for="item in items"
                            :key="item.id"
                            :value="item.id"
                            :disabled="form.type === 'loan' && !item.is_borrowable"
                        >
                            {{ item.code }} · {{ item.name }} (stok {{ numberFormat(item.stock_on_hand) }})
                        </option>
                    </select>
                    <small v-if="form.errors.inventory_item_id" class="field-error">{{ form.errors.inventory_item_id }}</small>
                </div>

                <div v-else class="field-group">
                    <label for="transaction-loan">Peminjaman aktif <span>*</span></label>
                    <select id="transaction-loan" v-model="form.loan_item_id">
                        <option value="" disabled>Pilih peminjaman</option>
                        <option v-for="loan in activeLoans" :key="loan.id" :value="loan.id">
                            {{ loan.transaction_code }} · {{ loan.item.name }} · {{ loan.borrower_name }} · sisa {{ loan.outstanding_quantity }}
                        </option>
                    </select>
                    <small v-if="form.errors.loan_item_id" class="field-error">{{ form.errors.loan_item_id }}</small>
                    <small v-if="activeLoans.length === 0" class="field-hint">Tidak ada peminjaman aktif untuk dikembalikan.</small>
                </div>

                <div class="field-group">
                    <label for="transaction-quantity">Jumlah <span>*</span></label>
                    <input
                        id="transaction-quantity"
                        v-model.number="form.quantity"
                        type="number"
                        min="1"
                        :max="form.type === 'return' ? selectedLoan?.outstanding_quantity : undefined"
                        required
                    />
                    <small v-if="form.errors.quantity" class="field-error">{{ form.errors.quantity }}</small>
                </div>

                <template v-if="form.type === 'loan'">
                    <div class="field-group">
                        <label for="borrower-name">Nama peminjam <span>*</span></label>
                        <input id="borrower-name" v-model="form.borrower_name" type="text" maxlength="150" required />
                        <small v-if="form.errors.borrower_name" class="field-error">{{ form.errors.borrower_name }}</small>
                    </div>
                    <div class="field-group">
                        <label for="borrower-division">Bagian / instansi <span>*</span></label>
                        <input id="borrower-division" v-model="form.division" type="text" maxlength="100" required />
                        <small v-if="form.errors.division" class="field-error">{{ form.errors.division }}</small>
                    </div>
                    <div class="field-group">
                        <label for="borrower-phone">Nomor HP <span>*</span></label>
                        <input id="borrower-phone" v-model="form.borrower_phone" type="tel" maxlength="30" required />
                        <small v-if="form.errors.borrower_phone" class="field-error">{{ form.errors.borrower_phone }}</small>
                    </div>
                </template>

                <div class="field-group notes-field">
                    <label for="transaction-notes">Catatan <span class="optional-label">Opsional</span></label>
                    <textarea id="transaction-notes" v-model="form.notes" rows="2" maxlength="2000"></textarea>
                    <small v-if="form.errors.notes" class="field-error">{{ form.errors.notes }}</small>
                </div>

                <div class="form-actions">
                    <p v-if="form.type === 'loan'" class="field-hint">Peminjam tanpa akun tetap tercatat dengan nama, bagian/instansi, dan nomor HP.</p>
                    <button class="primary-button" type="submit" :disabled="form.processing || (form.type === 'return' && activeLoans.length === 0)">
                        <IconDeviceFloppy :size="18" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan transaksi' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="transaction-list-card">
            <div class="list-heading">
                <div>
                    <h2>Riwayat transaksi</h2>
                    <p>Catatan barang masuk, peminjaman, dan pengembalian.</p>
                </div>
                <span>{{ numberFormat(transactions.total) }} data</span>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Kode / tanggal</th>
                            <th>Jenis</th>
                            <th>Barang</th>
                            <th>Jumlah</th>
                            <th>Peminjam / catatan</th>
                            <th>Dicatat oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="transaction in transactions.data" :key="transaction.id">
                            <td>
                                <strong>{{ transaction.transaction_code }}</strong>
                                <small>{{ transaction.transaction_at }}</small>
                            </td>
                            <td><span class="transaction-badge" :class="transactionClass(transaction.type)">{{ transactionLabel(transaction.type) }}</span></td>
                            <td>
                                <div v-for="line in transaction.items" :key="line.id">
                                    {{ line.inventory_item?.name ?? 'Barang dihapus' }}
                                    <small>{{ line.inventory_item?.code ?? '' }}</small>
                                </div>
                            </td>
                            <td>
                                <div v-for="line in transaction.items" :key="line.id">
                                    {{ numberFormat(line.quantity) }} {{ line.inventory_item?.unit ?? '' }}
                                </div>
                            </td>
                            <td>
                                <strong v-if="transaction.borrower_name">{{ transaction.borrower_name }}</strong>
                                <span v-if="transaction.division">{{ transaction.division }}</span>
                                <small v-if="transaction.borrower_phone">{{ transaction.borrower_phone }}</small>
                                <span v-if="transaction.notes">{{ transaction.notes }}</span>
                                <span v-if="!transaction.borrower_name && !transaction.notes">—</span>
                            </td>
                            <td>{{ transaction.creator?.name ?? 'QR / sistem' }}</td>
                        </tr>
                        <tr v-if="transactions.data.length === 0">
                            <td colspan="6" class="empty-state">Belum ada transaksi inventaris.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination :pagination="transactions" label="transaksi" />
        </section>
    </div>
</template>

<style scoped>
.transactions-page { color: #182433; font-family: Inter, sans-serif; font-size: 14px; }
.page-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 22px; }
.breadcrumb { display: flex; gap: 9px; margin-bottom: 8px; color: #667382; font-size: 12px; }
.breadcrumb span { color: #a0a8b2; }
.page-heading h1 { margin: 0; font-size: 24px; font-weight: 600; }
.page-description { max-width: 720px; margin: 7px 0 0; color: #667382; line-height: 1.6; }
.primary-button, .secondary-button { display: inline-flex; justify-content: center; align-items: center; gap: 8px; min-height: 38px; padding: 0 14px; border: 1px solid transparent; border-radius: 4px; font: inherit; font-weight: 500; text-decoration: none; white-space: nowrap; cursor: pointer; }
.primary-button { color: #fff; background: #066fd1; border-color: #066fd1; }
.primary-button:disabled { opacity: .55; cursor: not-allowed; }
.secondary-button { color: #182433; background: #fff; border-color: #dce0e5; }
.notice { margin-bottom: 16px; padding: 12px 14px; border-radius: 4px; }
.notice-success { color: #218838; background: #eaf7ed; border: 1px solid #ccebd2; }
.transaction-form-card, .transaction-list-card { margin-bottom: 20px; overflow: hidden; background: #fff; border: 1px solid #e6e7e9; border-radius: 4px; }
.transaction-form-card h2, .list-heading h2 { margin: 0; font-size: 16px; font-weight: 600; }
.transaction-form-card > h2 { padding: 19px 22px 0; }
.transaction-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; padding: 18px 22px 22px; }
.field-group { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.field-group label { color: #344054; font-size: 12px; font-weight: 500; }
.field-group label > span:first-child { color: #d63939; }
.field-group input, .field-group select, .field-group textarea { width: 100%; min-height: 39px; padding: 9px 11px; color: #182433; border: 1px solid #dce0e5; border-radius: 4px; background: #fff; font: inherit; font-size: 13px; }
.field-group textarea { resize: vertical; }
.notes-field, .form-actions { grid-column: 1 / -1; }
.field-hint { color: #8793a1; font-size: 11px; line-height: 1.5; }
.field-error { color: #d63939; font-size: 11px; }
.optional-label { color: #8793a1; font-weight: 400; }
.form-actions { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
.form-actions p { margin: 0; }
.list-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 19px 22px; }
.list-heading p { margin: 6px 0 0; color: #667382; font-size: 12px; }
.list-heading > span { color: #667382; font-size: 12px; }
.table-scroll { width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
thead { background: #f6f8fb; }
th { padding: 12px 15px; color: #667382; border-top: 1px solid #e6e7e9; border-bottom: 1px solid #e6e7e9; font-size: 11px; white-space: nowrap; }
td { padding: 13px 15px; color: #465363; border-bottom: 1px solid #edf0f2; vertical-align: top; }
td strong, td small { display: block; }
td small { margin-top: 4px; color: #8793a1; font-size: 11px; }
td span { display: block; margin-top: 4px; }
.transaction-badge { display: inline-flex; margin: 0; padding: 5px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; }
.transaction-in { color: #218838; background: #eaf7ed; }
.transaction-loan { color: #bd5707; background: #fff0e5; }
.transaction-return { color: #066fd1; background: #e8f2fc; }
.empty-state { padding: 34px; color: #667382; text-align: center; }
.pagination-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 22px; color: #667382; border-top: 1px solid #e6e7e9; font-size: 12px; }
.pagination-controls { display: flex; gap: 6px; }
.page-button { display: grid; min-width: 32px; height: 32px; place-items: center; color: #465363; background: #fff; border: 1px solid #dce0e5; border-radius: 4px; cursor: pointer; }
.page-button:disabled { opacity: .45; cursor: not-allowed; }
@media (max-width: 800px) {
    .transaction-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
    .page-heading { flex-direction: column; }
    .transaction-form { grid-template-columns: 1fr; padding: 16px; }
    .notes-field, .form-actions { grid-column: auto; }
    .form-actions { align-items: flex-start; flex-direction: column; }
    .transaction-form-card > h2, .list-heading { padding-right: 16px; padding-left: 16px; }
}
</style>
