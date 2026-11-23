<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { IconArrowLeft, IconPrinter } from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import LeaveLetterPreview from "@/Components/LeaveLetterPreview.vue";

defineOptions({ layout: AppLayout });

defineProps({
    request: { type: Object, required: true },
});

function printLetter() {
    window.print();
}
</script>

<template>
    <Head title="Surat Ijin Cuti" />

    <section class="letter-page">
        <div class="letter-actions">
            <Link :href="`/leave-requests/${request.id}`" class="secondary-button">
                <IconArrowLeft :size="17" /> Kembali
            </Link>
            <button type="button" class="primary-button" @click="printLetter">
                <IconPrinter :size="17" /> Cetak / simpan sebagai PDF
            </button>
        </div>

        <LeaveLetterPreview :request="request" />
    </section>
</template>

<style scoped>
@page { size: A4 portrait; margin: 0; }
.letter-page { display: grid; gap: 16px; color: #182433; }
.letter-actions { display: flex; justify-content: space-between; }
.primary-button, .secondary-button { display: inline-flex; align-items: center; gap: 7px; padding: 9px 13px; border: 1px solid #cbd4de; border-radius: 5px; font: inherit; text-decoration: none; cursor: pointer; }
.primary-button { color: #fff; background: #066fd1; border-color: #066fd1; }
.secondary-button { color: #354252; background: #fff; }
@media print {
    :global(body) { background: #fff; }
    :global(html), :global(body) { width: 210mm; margin: 0 !important; padding: 0 !important; }
    :global(.app-sidebar), :global(.app-topbar), :global(.letter-actions) { display: none !important; }
    :global(.app-main), :global(.app-main.sidebar-collapsed) { margin: 0 !important; }
    :global(.app-content) { width: 210mm; max-width: none; padding: 8mm; margin: 0; }
    .letter-page { display: block; }
    :global(.letter-sheet) { width: 194mm !important; max-width: none !important; margin: 0 !important; }
}
</style>
