<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { IconPackage } from '@tabler/icons-vue'

const props = defineProps({
    items: { type: Array, default: () => [] },
    activeLoans: { type: Array, default: () => [] },
    borrowerPhoneLookup: { type: String, default: '' },
    receipt: { type: Object, default: null },
})

const borrowForm = useForm({
    inventory_item_id: props.items[0]?.id ?? '',
    borrower_name: '',
    division: '',
    borrower_phone: '',
    quantity: 1,
})

const lookupForm = useForm({
    borrower_phone: props.borrowerPhoneLookup,
})

const returnForm = useForm({
    loan_item_id: props.activeLoans[0]?.id ?? '',
    borrower_phone: props.borrowerPhoneLookup,
    quantity: 1,
})

const selectedBorrowItem = computed(() =>
    props.items.find((item) => item.id === Number(borrowForm.inventory_item_id)),
)

const selectedLoan = computed(() =>
    props.activeLoans.find((loan) => loan.id === Number(returnForm.loan_item_id)),
)

function submitBorrow() {
    borrowForm.post('/i/transaksi', {
        preserveScroll: true,
        onSuccess: () => borrowForm.reset(),
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
        onSuccess: () => returnForm.reset('quantity'),
    })
}

function numberFormat(value) {
    return new Intl.NumberFormat('id-ID').format(value ?? 0)
}
</script>

<template>
    <main class="public-page">
        <header class="public-header">
            <div class="brand-mark"><IconPackage :size="22" /></div>
            <div>
                <strong>Inventaris IT Karyawan</strong>
                <span>Transaksi barang melalui QR</span>
            </div>
        </header>

        <section class="item-card">
            <div class="item-eyebrow">TRANSAKSI INVENTARIS</div>
            <h1>Peminjaman &amp; Pengembalian Barang</h1>
            <p class="page-intro">Pilih barang yang akan dipinjam atau cari peminjaman aktif menggunakan nomor HP.</p>

            <div v-if="receipt" class="receipt" role="status">
                <strong>{{ receipt.type === 'loan' ? 'Peminjaman tercatat' : 'Pengembalian tercatat' }}</strong>
                <span>{{ receipt.item_name }} · {{ numberFormat(receipt.quantity) }} {{ receipt.unit }}</span>
                <span>Kode transaksi: <b>{{ receipt.transaction_code }}</b></span>
                <p v-if="receipt.type === 'loan'">Pengembalian dapat dicatat dengan mencari nomor HP peminjam pada formulir di bawah.</p>
            </div>

            <div class="action-grid">
                <section class="action-card">
                    <div class="section-heading">
                        <span class="step-number">1</span>
                        <div>
                            <h2>Pinjam barang</h2>
                            <p>Isi identitas peminjam dan pilih barang. Akun aplikasi tidak diperlukan.</p>
                        </div>
                    </div>

                    <p v-if="items.length === 0" class="form-notice">
                        Saat ini tidak ada barang yang tersedia untuk dipinjam.
                    </p>

                    <form v-else class="transaction-form" @submit.prevent="submitBorrow">
                        <label>
                            Nama peminjam <span>*</span>
                            <input v-model="borrowForm.borrower_name" type="text" maxlength="150" autocomplete="name" required />
                            <small v-if="borrowForm.errors.borrower_name">{{ borrowForm.errors.borrower_name }}</small>
                        </label>
                        <label>
                            Bagian / instansi <span>*</span>
                            <input v-model="borrowForm.division" type="text" maxlength="100" required />
                            <small v-if="borrowForm.errors.division">{{ borrowForm.errors.division }}</small>
                        </label>
                        <label>
                            Nomor HP <span>*</span>
                            <input v-model="borrowForm.borrower_phone" type="tel" maxlength="30" autocomplete="tel" required />
                            <small v-if="borrowForm.errors.borrower_phone">{{ borrowForm.errors.borrower_phone }}</small>
                        </label>
                        <label>
                            Barang <span>*</span>
                            <select v-model="borrowForm.inventory_item_id" required>
                                <option value="" disabled>Pilih barang</option>
                                <option v-for="item in items" :key="item.id" :value="item.id">
                                    {{ item.code }} · {{ item.name }} (stok {{ numberFormat(item.stock_on_hand) }} {{ item.unit }})
                                </option>
                            </select>
                            <small v-if="borrowForm.errors.inventory_item_id">{{ borrowForm.errors.inventory_item_id }}</small>
                        </label>
                        <label>
                            Jumlah <span>*</span>
                            <input v-model.number="borrowForm.quantity" type="number" min="1" :max="selectedBorrowItem?.stock_on_hand" required />
                            <small v-if="borrowForm.errors.quantity">{{ borrowForm.errors.quantity }}</small>
                        </label>
                        <button type="submit" :disabled="borrowForm.processing">
                            {{ borrowForm.processing ? 'Mencatat...' : 'Catat peminjaman' }}
                        </button>
                    </form>
                </section>

                <section class="action-card">
                    <div class="section-heading">
                        <span class="step-number">2</span>
                        <div>
                            <h2>Kembalikan barang</h2>
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
                        <p v-if="selectedLoan" class="loan-details">
                            Kode {{ selectedLoan.transaction_code }} · {{ selectedLoan.borrower_name }} · {{ selectedLoan.division }}
                        </p>
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

            <p class="privacy-note">Informasi identitas digunakan untuk mencatat dan mengelola transaksi peminjaman inventaris.</p>
        </section>
    </main>
</template>

<style scoped>
.public-page { min-height: 100vh; padding: 28px 16px 50px; color: #182433; background: #f6f8fb; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
.public-header { display: flex; align-items: center; gap: 11px; width: min(100%, 980px); margin: 0 auto 20px; }
.brand-mark { display: grid; width: 40px; height: 40px; place-items: center; color: #066fd1; background: #e8f2fc; border-radius: 5px; }
.public-header strong, .public-header span { display: block; }
.public-header strong { font-size: 14px; }
.public-header span { margin-top: 3px; color: #667382; font-size: 11px; }
.item-card { width: min(100%, 980px); margin: auto; padding: 26px; background: #fff; border: 1px solid #e6e7e9; border-radius: 6px; }
.item-eyebrow { color: #066fd1; font-size: 10px; font-weight: 600; letter-spacing: 1px; }
h1 { margin: 7px 0 0; font-size: 25px; }
.page-intro { margin: 8px 0 0; color: #667382; font-size: 13px; line-height: 1.5; }
.action-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 22px; }
.action-card { min-width: 0; padding: 19px; border: 1px solid #e6e7e9; border-radius: 5px; }
.section-heading { display: flex; align-items: flex-start; gap: 11px; margin-bottom: 17px; }
.step-number { display: grid; flex: 0 0 27px; height: 27px; place-items: center; color: #066fd1; background: #e8f2fc; border-radius: 50%; font-size: 12px; font-weight: 600; }
.section-heading h2 { margin: 0; font-size: 15px; }
.section-heading p { margin: 5px 0 0; color: #667382; font-size: 11px; line-height: 1.5; }
.transaction-form { display: flex; flex-direction: column; gap: 13px; }
.transaction-form label { display: flex; flex-direction: column; gap: 6px; color: #344054; font-size: 12px; font-weight: 500; }
.transaction-form label > span { color: #d63939; }
.transaction-form input, .transaction-form select { width: 100%; min-height: 39px; padding: 9px 10px; color: #182433; border: 1px solid #dce0e5; border-radius: 4px; outline: none; background: #fff; font: inherit; }
.transaction-form input:focus, .transaction-form select:focus { border-color: #066fd1; box-shadow: 0 0 0 2px rgb(6 111 209 / 10%); }
.transaction-form small { color: #d63939; font-size: 11px; font-weight: 400; }
.transaction-form button { min-height: 40px; margin-top: 3px; padding: 0 14px; color: #fff; background: #066fd1; border: 1px solid #066fd1; border-radius: 4px; font: inherit; font-weight: 500; cursor: pointer; }
.transaction-form button:disabled { opacity: .55; cursor: wait; }
.lookup-form { padding-bottom: 16px; border-bottom: 1px solid #e6e7e9; }
.return-form { margin-top: 16px; }
.loan-details { margin: 0; padding: 10px; color: #344054; background: #f6f8fb; border-radius: 4px; font-size: 11px; line-height: 1.5; }
.form-notice { padding: 12px; color: #667382; background: #f6f8fb; border-radius: 4px; font-size: 12px; line-height: 1.5; }
.search-result { margin: 14px 0 0; }
.receipt { display: flex; flex-direction: column; gap: 6px; margin-top: 18px; padding: 14px; color: #195c2a; background: #eaf7ed; border: 1px solid #ccebd2; border-radius: 5px; font-size: 12px; }
.receipt > strong { font-size: 14px; }
.receipt p { margin: 3px 0 0; color: #315a3b; line-height: 1.5; }
.privacy-note { margin: 18px 0 0; color: #8793a1; font-size: 11px; line-height: 1.5; text-align: center; }
@media (max-width: 760px) {
    .item-card { padding: 20px 16px; }
    .action-grid { grid-template-columns: 1fr; }
    h1 { font-size: 22px; }
}
</style>
