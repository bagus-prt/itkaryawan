<script setup>
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import QRCode from 'qrcode'

const props = defineProps({
    qrUrl: { type: String, required: true },
})

const canvas = ref(null)
const qrError = ref('')

onMounted(async () => {
    try {
        await QRCode.toCanvas(canvas.value, props.qrUrl, {
            width: 240,
            margin: 2,
            errorCorrectionLevel: 'M',
        })
    } catch (error) {
        qrError.value = 'QR tidak dapat dibuat. Muat ulang halaman atau hubungi admin.'
        console.error('Gagal membuat QR keluhan SPBU.', error)
    }
})

function printQr() {
    window.print()
}
</script>

<template>
    <main class="qr-page">
        <div class="screen-actions print-hidden">
            <Link href="/complaints" class="back-link">Kembali ke keluhan SPBU</Link>
            <button type="button" @click="printQr">Cetak QR</button>
        </div>

        <article class="qr-card">
            <div class="qr-heading">
                <span>PERTAMINA · LAYANAN UNTUK SPBU</span>
                <h1>Formulir Keluhan SPBU</h1>
                <p>Pindai QR ini untuk menyampaikan keluhan kepada Pertamina.</p>
            </div>

            <canvas ref="canvas" aria-label="QR formulir keluhan SPBU"></canvas>
            <p v-if="qrError" class="qr-error" role="alert">{{ qrError }}</p>
            <p class="qr-instruction">Isi kode dan alamat SPBU serta keterangan keluhan.</p>
        </article>
    </main>
</template>

<style scoped>
.qr-page { min-height: 100vh; padding: 24px; color: #182433; background: #f6f8fb; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
.screen-actions { display: flex; justify-content: space-between; width: min(100%, 680px); margin: 0 auto 20px; }
.screen-actions a, .screen-actions button { min-height: 38px; padding: 0 13px; color: #182433; background: #fff; border: 1px solid #dce0e5; border-radius: 4px; font: inherit; text-decoration: none; cursor: pointer; }
.screen-actions button { color: #fff; background: #066fd1; border-color: #066fd1; }
.qr-card { display: flex; align-items: center; flex-direction: column; width: min(100%, 440px); margin: auto; padding: 28px; background: #fff; border: 1px solid #e6e7e9; text-align: center; }
.qr-heading > span { color: #667382; font-size: 10px; font-weight: 600; letter-spacing: 1px; }
.qr-heading h1 { margin: 9px 0 0; font-size: 22px; }
.qr-heading p { margin: 7px 0 0; color: #667382; font-size: 12px; line-height: 1.5; }
canvas { display: block; max-width: 100%; margin: 18px auto 8px; }
.qr-instruction { margin: 7px 0; font-size: 13px; font-weight: 500; }
.qr-error { color: #c02c2c; font-size: 12px; }
@media print {
    .qr-page { min-height: 0; padding: 0; background: #fff; }
    .print-hidden { display: none !important; }
    .qr-card { width: 100%; max-width: none; margin: 0; padding: 16px; border: 0; box-shadow: none; break-inside: avoid; }
}
</style>
