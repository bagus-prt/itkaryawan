<script setup>
import { Head } from "@inertiajs/vue3";
import { IconFileText } from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Surat Tugas" />

    <section class="task-letters-page">
        <p class="eyebrow">Dokumen Kepegawaian</p>
        <h1>Surat Tugas</h1>
        <div class="empty-card">
            <IconFileText :size="30" stroke-width="1.7" />
            <h2>Fitur Surat Tugas belum tersedia</h2>
            <p>
                Alur pengajuan dan persetujuan Surat Tugas belum ditentukan.
                Untuk saat ini, pengajuan cuti dapat dilakukan melalui menu Pengajuan Cuti.
            </p>
        </div>
    </section>
</template>

<style scoped>
.task-letters-page { display: grid; gap: 8px; color: #182433; }
.eyebrow { margin: 0; color: #667382; font-size: 12px; }
.task-letters-page h1 { margin: 0 0 12px; font-size: 25px; }
.empty-card { display: grid; justify-items: center; gap: 9px; padding: 48px 24px; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; text-align: center; }
.empty-card > :first-child { color: #066fd1; }
.empty-card h2 { margin: 0; font-size: 17px; }
.empty-card p { max-width: 520px; margin: 0; color: #667382; line-height: 1.6; }
</style>
