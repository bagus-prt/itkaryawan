<script setup>
import { Head } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Dashboard" />

    <section class="dashboard-page">
        <div class="page-heading">
            <div>
                <h1>Dashboard</h1>
                <p>Selamat datang di Sistem Informasi IT Karyawan.</p>
            </div>
        </div>

        <div class="dashboard-placeholder">
            <h2>Layout berhasil dipasang</h2>
            <p>
                Area ini nantinya akan berisi ringkasan informasi sesuai dengan
                hak akses pengguna.
            </p>
        </div>
    </section>
</template>

<style scoped>
.page-heading {
    margin-bottom: 24px;
}

.page-heading h1 {
    margin: 0;
    color: #182433;
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.025em;
}

.page-heading p {
    margin: 7px 0 0;
    color: #667382;
    font-size: 13px;
    line-height: 1.6;
}

.dashboard-placeholder {
    padding: 24px;
    background: #fff;
    border: 1px solid #e6e7e9;
    border-radius: 4px;
}

.dashboard-placeholder h2 {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 600;
}

.dashboard-placeholder p {
    margin: 0;
    color: #667382;
    font-size: 13px;
    line-height: 1.7;
}
</style>
