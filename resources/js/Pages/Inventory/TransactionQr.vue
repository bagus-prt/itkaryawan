<script setup>
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import logo from '../../../../pertamina_patra.webp'

const props = defineProps({
    qrUrl: { type: String, required: true },
})

const canvas = ref(null)
const qrError = ref('')
const loopbackHosts = ['localhost', '127.0.0.1', '[::1]']
const localOnly = loopbackHosts.includes(new URL(props.qrUrl).hostname)

onMounted(async () => {
    try {
        await QRCode.toCanvas(canvas.value, props.qrUrl, {
            width: 240,
            margin: 2,
            errorCorrectionLevel: 'M',
        })
    } catch (error) {
        qrError.value = 'QR tidak dapat dibuat. Muat ulang halaman atau hubungi admin.'
        console.error('Gagal membuat QR transaksi inventaris.', error)
    }
})

function printLabel() {
    window.print()
}
</script>

<template>
    <main class="qr-page inventory-workspace">
        <div class="screen-actions print-hidden">
            <Link href="/inventory" class="back-link">Kembali ke inventaris</Link>
            <a :href="qrUrl" target="_blank" rel="noopener">Buka Halaman QR</a>
            <button type="button" @click="printLabel">Cetak QR</button>
        </div>

        <article class="qr-label">
            <div class="qr-heading">
                <img :src="logo" class="qr-logo" alt="Pertamina Patra Niaga" />
                <h1>Transaksi Inventaris</h1>
            </div>

            <canvas ref="canvas" aria-label="QR transaksi inventaris"></canvas>
            <p v-if="qrError" class="qr-error" role="alert">{{ qrError }}</p>
            <p class="qr-instruction">Pindai untuk pinjam, kembalikan, atau ambil barang habis pakai.</p>
            <div class="qr-address print-hidden">
                <small>{{ qrUrl }}</small>
                <p v-if="localOnly">Alamat ini hanya bisa dibuka dari komputer ini. Untuk memindai dari HP, buka halaman Cetak QR lewat alamat IP komputer pada jaringan yang sama.</p>
            </div>
        </article>
    </main>
</template>

<style scoped>
.qr-page { min-height: 100vh; padding: 24px; color: #182433; background: #f6f8fb; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
.screen-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; width: min(100%, 680px); margin: 0 auto 20px; }
.screen-actions a, .screen-actions button { display: inline-flex; align-items: center; min-height: 38px; padding: 0 13px; color: #182433; background: #fff; border: 1px solid #dce0e5; border-radius: 4px; font: inherit; text-decoration: none; cursor: pointer; }
.screen-actions button { color: #fff; background: #066fd1; border-color: #066fd1; }
.qr-label { display: flex; align-items: center; flex-direction: column; width: min(100%, 420px); margin: auto; padding: 28px; background: #fff; border: 1px solid #e6e7e9; text-align: center; }
.qr-heading > span { color: #667382; font-size: 10px; font-weight: 600; letter-spacing: 1px; }
.qr-heading h1 { margin: 9px 0 0; font-size: 22px; }
.qr-heading p { margin: 7px 0 0; color: #667382; font-size: 12px; }
canvas { display: block; max-width: 100%; margin: 18px auto 8px; }
.qr-instruction { margin: 7px 0; font-size: 13px; font-weight: 500; }
.qr-label small { color: #8793a1; font-size: 10px; }
.qr-address { max-width: 100%; overflow-wrap: anywhere; }
.qr-address p { margin-top: 7px; color: #485568; font-size: 12px; }
.qr-error { color: #c02c2c; font-size: 12px; }
@media print {
    .qr-page { min-height: 0; padding: 0; background: #fff; }
    .print-hidden { display: none !important; }
    .qr-label { width: 100%; max-width: none; margin: 0; padding: 16px; border: 0; box-shadow: none; break-inside: avoid; }
}
</style>

<style scoped src="../../../css/inventory.css"></style>
