<script setup>
defineProps({
    request: { type: Object, required: true },
});

function displayDate(value) {
    if (!value) {
        return "—";
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "long",
        year: "numeric",
    });
}
</script>

<template>
    <article class="letter-sheet">
        <header class="letter-header">
            <h1>SURAT IJIN CUTI</h1>
            <p>PT. Pertamina Training &amp; Consulting</p>
        </header>

        <p class="intro">Dengan ini mengijinkan / menugaskan,</p>
        <dl class="identity">
            <div><dt>Nama</dt><dd>{{ request.employee_name }}</dd></div>
            <div><dt>Bagian</dt><dd>{{ request.department }}</dd></div>
            <div><dt>Lokasi Kerja</dt><dd>{{ request.work_location }}</dd></div>
        </dl>

        <div class="leave-type">
            <strong>UNTUK MELAKSANAKAN / MENDAPATKAN / DIPERGUNAKAN</strong>
            <span class="leave-option"><i aria-hidden="true"></i> PD-DN</span>
            <span class="leave-option"><i aria-hidden="true"></i> PN-LN</span>
            <span class="leave-option"><i aria-hidden="true"></i> SIJ</span>
            <span class="leave-option"><i class="selected" aria-hidden="true">✓</i> CUTI</span>
        </div>

        <div class="leave-facts">
            <dl>
                <div><dt>Dari / Asal</dt><dd>{{ request.work_location }}</dd></div>
                <div><dt>Tempat Tujuan / Negara</dt><dd>{{ request.destination }}</dd></div>
                <div><dt>Terhitung Mulai Tanggal</dt><dd>{{ displayDate(request.start_date) }} s/d {{ displayDate(request.end_date) }}</dd></div>
                <div><dt>Keperluan / Keterangan</dt><dd>{{ request.reason }}</dd></div>
                <div><dt>Berkendaraan</dt><dd>{{ request.transportation }}</dd></div>
                <div><dt>Biaya ditanggung oleh</dt><dd>{{ request.expense_paid_by }}</dd></div>
            </dl>
            <dl>
                <div><dt>Hak cuti tahun ke</dt><dd>{{ request.leave_entitlement_year }}</dd></div>
                <div><dt>Cuti yang telah diambil</dt><dd>{{ request.leave_taken_days }} hari</dd></div>
                <div><dt>Cuti yang akan dilaksanakan</dt><dd>{{ request.leave_requested_days }} hari</dd></div>
                <div><dt>Sisa cuti yang belum diambil</dt><dd>{{ request.leave_remaining_days }} hari</dd></div>
                <div><dt>Harus masuk bekerja kembali</dt><dd>{{ displayDate(request.return_to_work_date) }}</dd></div>
                <div><dt>Cuti yang akan jatuh pada</dt><dd>{{ displayDate(request.leave_due_date) }}</dd></div>
            </dl>
        </div>

        <section class="reason-box">
            <strong>Keterangan / Keperluan:</strong>
            <p>{{ request.reason }}</p>
        </section>

        <table class="companions-table">
            <thead><tr><th>No</th><th>Nama Pengikut</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr v-for="index in 3" :key="index">
                    <td>{{ index }}</td>
                    <td>{{ request.companions[index - 1]?.name ?? "" }}</td>
                    <td>{{ request.companions[index - 1]?.notes ?? "" }}</td>
                </tr>
            </tbody>
        </table>

        <section class="signatures">
            <div class="signature-block applicant-signature">
                <span>Diajukan oleh,</span>
                <img
                    v-if="request.employee_signature_url"
                    :src="request.employee_signature_url"
                    alt="Tanda tangan pengirim pengajuan"
                />
                <strong>{{ request.submitted_by_name }}</strong>
            </div>
            <div class="signature-block manager-signature">
                <span>Tanggal permohonan: {{ request.submission_date }}</span>
                <strong>IT Manager</strong>
                <img
                    v-if="request.manager_signature_url"
                    :src="request.manager_signature_url"
                    alt="Tanda tangan IT Manager"
                />
                <span v-else class="signature-space" aria-hidden="true"></span>
                <strong>{{ request.manager_name }}</strong>
            </div>
            <div class="signature-block acknowledgment-signature">
                <span>Balikpapan,<br />Mengetahui,<br />PT. Pertamina Training &amp; Consulting</span>
                <span class="signature-space" aria-hidden="true"></span>
                <strong>Ricky Roy Sagita</strong>
            </div>
        </section>

        <footer v-if="request.review_notes" class="letter-footer">
            <strong>Catatan:</strong>
            <p>{{ request.review_notes }}</p>
        </footer>
    </article>
</template>

<style scoped>
.letter-sheet { box-sizing: border-box; width: min(100%, 980px); padding: 48px; margin: 0 auto; background: #fff; border: 1px solid #cbd4de; box-shadow: 0 3px 16px rgb(24 36 51 / 8%); font-family: Arial, sans-serif; font-size: 13px; line-height: 1.25; }
.letter-header { margin-bottom: 24px; }
.letter-header h1 { margin: 0 0 20px; font-size: 20px; text-align: center; }
.letter-header p { margin: 0; font-size: 15px; }
.intro { margin: 20px 0 4px; }
.identity { display: grid; gap: 5px; width: min(100%, 560px); margin: 0 0 22px; }
.identity div, .leave-facts dl div { display: grid; grid-template-columns: minmax(150px, 1fr) 14px minmax(0, 2fr); gap: 5px; }
.identity dt::after, .leave-facts dt::after { content: ":"; float: right; }
.identity dd, .leave-facts dd { grid-column: 3; margin: 0; }
.leave-type { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 0 18px; border-bottom: 1px solid #9ba5ae; }
.leave-type strong { max-width: 280px; line-height: 1.5; }
.leave-option { display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
.leave-option i { display: inline-grid; width: 6mm; height: 6mm; flex: 0 0 6mm; place-items: center; border: 1px solid #182433; font-size: 13px; font-style: normal; line-height: 1; }
.leave-option i.selected { font-weight: 700; }
.leave-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 26px; padding: 18px 0; }
.leave-facts dl { display: grid; align-content: start; gap: 8px; margin: 0; }
.leave-facts dl div { grid-template-columns: minmax(132px, 1fr) 10px minmax(0, 1.25fr); }
.reason-box { min-height: 115px; padding: 10px; border: 1px solid #182433; }
.reason-box p { margin: 8px 0; white-space: pre-wrap; }
.companions-table { width: 100%; border-collapse: collapse; }
.companions-table th, .companions-table td { height: 29px; padding: 5px 8px; border: 1px solid #182433; text-align: left; }
.companions-table th { text-align: center; text-transform: uppercase; }
.companions-table th:first-child, .companions-table td:first-child { width: 10%; text-align: center; }
.signatures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); min-height: 175px; border: 1px solid #182433; border-top: 0; }
.signature-block { display: flex; flex-direction: column; align-items: flex-start; min-width: 0; min-height: 175px; padding: 10px; }
.signature-block + .signature-block { border-left: 1px solid #182433; }
.signature-block img { width: min(100%, 180px); height: 70px; margin-top: auto; object-fit: contain; object-position: left center; }
.signature-block strong { margin-top: 5px; }
.manager-signature { gap: 4px; }
.manager-signature img { margin-top: auto; }
.signature-space { flex: 1; min-height: 70px; }
.letter-footer { padding: 10px; border: 1px solid #182433; border-top: 0; }
.letter-footer p { margin: 6px 0 0; white-space: pre-wrap; }
@media (max-width: 700px) {
    .letter-sheet { padding: 22px 14px; }
    .leave-type { align-items: flex-start; flex-direction: column; }
    .leave-facts { grid-template-columns: 1fr; gap: 10px; }
    .signatures { grid-template-columns: 1fr; }
    .signature-block + .signature-block { border-top: 1px solid #182433; border-left: 0; }
}
@media print {
    .letter-sheet { width: 100%; padding: 0; border: 0; box-shadow: none; font-size: 8pt; line-height: 1.12; }
    .letter-header { margin-bottom: 10px; }
    .letter-header h1 { margin-bottom: 8px; font-size: 14pt; }
    .letter-header p { font-size: 10pt; }
    .intro { margin: 8px 0 2px; }
    .identity { gap: 2px; margin-bottom: 8px; }
    .identity div, .leave-facts dl div { grid-template-columns: minmax(110px, 1fr) 8px minmax(0, 2fr); gap: 3px; }
    .leave-type { gap: 5px; padding: 5px 0 8px; }
    .leave-type strong { max-width: 220px; line-height: 1.2; }
    .leave-option { gap: 3px; }
    .leave-option i { width: 5mm; height: 5mm; flex-basis: 5mm; font-size: 9pt; }
    .leave-facts { gap: 12px; padding: 8px 0; }
    .leave-facts dl { gap: 3px; }
    .leave-facts dl div { grid-template-columns: minmax(95px, 1fr) 6px minmax(0, 1.25fr); }
    .reason-box { min-height: 58px; padding: 6px; }
    .reason-box p { margin: 4px 0; }
    .companions-table th, .companions-table td { height: 19px; padding: 2px 5px; }
    .signatures { min-height: 125px; }
    .signature-block { min-height: 125px; padding: 6px; }
    .signature-block img { width: min(100%, 210px); height: 58px; }
    .signature-space { min-height: 58px; }
    .letter-footer { padding: 6px; }
    .letter-sheet, .leave-facts, .reason-box, .companions-table, .signatures, .letter-footer { break-inside: avoid; }
}
</style>
