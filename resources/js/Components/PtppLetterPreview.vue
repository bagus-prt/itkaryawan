<script setup>
import companyLogo from "../../../pertamina_patra.webp";

defineProps({
    ptppRequest: { type: Object, required: true },
});

function isSourceSelected(request, value) {
    return request.source_category === value;
}
</script>

<template>
    <article class="ptpp-sheet">
        <header class="letter-header">
            <div class="number-cell">
                <strong>PTPP - {{ ptppRequest.ptpp_number }}</strong>
                <span>Tanggal</span>
                <strong>{{ ptppRequest.submission_date }}</strong>
            </div>
            <div class="title-cell">
                <h1>PERMINTAAN TINDAKAN PERBAIKAN<br />DAN PENCEGAHAN (PTPP)</h1>
                <strong>IT BANJARMASIN</strong>
            </div>
            <div class="logo-cell">
                <img :src="companyLogo" alt="Pertamina Patra Niaga" />
            </div>
        </header>

        <section class="address-grid">
            <div class="address-rows">
                <div>
                    <strong>Kepada / Fungsi:</strong>
                    <span>{{ ptppRequest.recipient_name || "—" }}</span>
                    <b>/</b>
                    <span>{{ ptppRequest.to_function || "—" }}</span>
                </div>
                <div>
                    <strong>Dari / Fungsi:</strong>
                    <span>{{ ptppRequest.sender_signature_name || "—" }}</span>
                    <b>/</b>
                    <span>{{ ptppRequest.from_function || "—" }}</span>
                </div>
            </div>
            <div class="location-cell">
                <strong>Area / Lokasi Temuan</strong>
                <span>{{ ptppRequest.location || "—" }}</span>
            </div>
        </section>

        <div class="band">Di isi oleh pemohon / auditor</div>

        <section class="document-section">
            <h2>SUMBER KETIDAKSESUAIAN ATAU POTENSINYA</h2>
            <div class="source-grid">
                <span><i :class="{ checked: isSourceSelected(ptppRequest, 'keluhan_pelanggan') }"></i>Keluhan Pelanggan</span>
                <span><i :class="{ checked: isSourceSelected(ptppRequest, 'tinjauan_manajemen') }"></i>Tinjauan Manajemen</span>
                <span><i :class="{ checked: isSourceSelected(ptppRequest, 'usulan_saran') }"></i>Usulan / Saran untuk ....</span>
                <span><i :class="{ checked: isSourceSelected(ptppRequest, 'audit') }"></i>Audit</span>
                <span><i :class="{ checked: isSourceSelected(ptppRequest, 'survey_pelanggan') }"></i>Survey Pelanggan</span>
                <span><i :class="{ checked: isSourceSelected(ptppRequest, 'lainnya') }"></i>Lainnya:</span>
            </div>
        </section>

        <section class="document-section">
            <h2>KETIDAKSESUAIAN ATAU POTENSI YANG DITEMUKAN</h2>
            <p class="finding-text">{{ ptppRequest.description }}</p>
            <div class="finding-metadata">
                <span>Batas waktu reply / jawab :</span>
                <span>*) Kategori : Temuan / Observasi</span>
            </div>
        </section>

        <section class="reply-row">
            <div class="illustration-area">
                <strong>ILUSTRASI / GAMBAR (Jika ada):</strong>
                <div class="illustration-content">
                    <img v-if="ptppRequest.illustration_url" :src="ptppRequest.illustration_url" alt="Ilustrasi temuan PTPP" />
                    <span class="short-name">{{ ptppRequest.sender_short_name }}</span>
                </div>
            </div>
            <div class="signatures">
                <div class="signature-heading">
                    <span>Pemohon,</span>
                    <span>Disetujui oleh,</span>
                </div>
                <div class="signature-functions">
                    <span>{{ ptppRequest.from_function || " " }}</span>
                    <span>IT Manager Banjarmasin</span>
                </div>
                <div class="signature-spaces">
                    <div class="signature-box">
                        <img v-if="ptppRequest.sender_signature_url" :src="ptppRequest.sender_signature_url" alt="Tanda tangan pemohon" />
                        <strong class="signature-name">{{ ptppRequest.sender_signature_name }}</strong>
                    </div>
                    <div class="signature-box">
                        <img v-if="ptppRequest.reviewer_signature_url" :src="ptppRequest.reviewer_signature_url" alt="Tanda tangan manager" />
                        <strong v-if="ptppRequest.reviewer_signature_url" class="signature-name">{{ ptppRequest.manager_name }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <div class="band">Di isi oleh penerima CAR / PAR</div>
        <section class="document-section follow-up">
            <h2>TINDAK LANJUT</h2>
            <div class="follow-up-grid">
                <div class="temporary-action">
                    <strong>PERBAIKAN / TINDAKAN SEMENTARA (Jika ada) :</strong>
                    <span>:</span>
                </div>
                <div class="received-date">
                    <span>Tgl Terima</span>
                    <div></div>
                </div>
                <div class="receiver-section">
                    <strong>Penerima CAR PAR</strong>
                    <div class="receiver-headings">
                        <span>Penanggung Jawab</span>
                        <span>Disetujui oleh,</span>
                    </div>
                    <div class="receiver-signatures">
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
            <div class="analysis-heading">
                <strong>No</strong>
                <strong>Analisa Penyebab</strong>
                <strong>Tindakan Perbaikan dan Pencegahan</strong>
                <strong>PIC</strong>
                <strong>Waktu Pelaksanaan</strong>
            </div>
            <div class="analysis-row">
                <span>1</span>
                <span></span>
                <span></span>
                <span></span>
                <span class="schedule-cells"><i></i><i></i></span>
            </div>
        </section>

        <section class="document-section verification">
            <div class="verification-notes">
                <strong>Dokumen yang direvisi (jika ada) :</strong>
                <div class="revision-docs">
                    <span><i></i> Pedoman / Manual</span>
                    <span><i></i> TKI</span>
                    <span><i></i> Formulir</span>
                    <span><i></i> TKO</span>
                    <span><i></i> TKPA</span>
                </div>
            </div>
            <div class="verification-date">
                <strong>Target Waktu Verifikasi</strong>
                <span>Tgl :</span>
            </div>
        </section>

        <div class="band">Di isi oleh pemohon / auditor</div>
        <section class="document-section final-verification">
            <h2>VERIFIKASI PELAKSANAAN TINDAKAN PERBAIKAN DAN PENCEGAHAN</h2>
            <div class="final-status">
                <div class="verification-fields">
                    <strong>Status :</strong>
                    <span><i></i> Close</span>
                    <span><i></i> Perlu follow up</span>
                    <span>Tanggal　　　　　　　　　:</span>
                    <span>Target verifikasi selanjutnya　:</span>
                    <p>Catatan :<br />Jika tindakan perbaikan/pencegahan belum memenuhi maka terbitkan PTPP Baru</p>
                    <em>*) Khusus untuk kategori audit</em>
                </div>
                <div class="final-signature">
                    <strong>Approval Pemohon / Auditor</strong>
                    <div><span></span><span></span></div>
                </div>
            </div>
        </section>
        <footer class="document-footer">SQ-D-009/2011</footer>
    </article>
</template>

<style scoped>
.ptpp-sheet { width: min(100%, 210mm); padding: 8mm; margin: 0 auto; color: #17212b; background: #fff; border: 1px solid #d8dde3; box-shadow: 0 8px 26px rgb(25 42 60 / 9%); font-family: Arial, Helvetica, sans-serif; font-size: 10px; line-height: 1.35; }
.letter-header { display: grid; grid-template-columns: 1fr 2.1fr .95fr; min-height: 24mm; border: 1px solid #1f2933; }
.number-cell, .title-cell, .logo-cell { display: flex; align-items: center; justify-content: center; flex-direction: column; padding: 5px; text-align: center; }
.number-cell { gap: 5px; border-right: 1px solid #1f2933; }
.number-cell span { width: 100%; padding: 3px 0; border-top: 1px solid #1f2933; border-bottom: 1px solid #1f2933; }
.title-cell h1 { margin: 0 0 4px; font-size: 13px; line-height: 1.2; }
.title-cell strong { font-size: 12px; }
.logo-cell { border-left: 1px solid #1f2933; }
.logo-cell img { width: 100%; max-height: 48px; object-fit: contain; }
.address-grid { display: grid; grid-template-columns: 1.55fr .9fr; border: 1px solid #1f2933; border-top: 0; }
.address-rows { display: grid; }
.address-rows > div { display: grid; grid-template-columns: 30% minmax(0, 1fr) auto minmax(0, .8fr); min-height: 24px; align-items: center; gap: 5px; padding: 4px; }
.address-rows > div + div { border-top: 1px solid #1f2933; }
.address-rows > div > span { overflow-wrap: anywhere; }
.location-cell { display: grid; align-content: start; text-align: center; border-left: 1px solid #1f2933; }
.location-cell strong { padding: 4px; border-bottom: 1px solid #1f2933; }
.location-cell span { min-height: 28px; padding: 6px; background: #fff2a8; font-weight: 600; }
.band { padding: 4px 6px; margin-top: 6px; color: #17212b; background: #e8ebf3; border: 1px solid #1f2933; font-style: italic; font-weight: 700; }
.document-section { border: 1px solid #1f2933; border-top: 0; }
.document-section h2 { margin: 0; padding: 5px; border-bottom: 1px solid #1f2933; font-size: 11px; text-align: center; }
.source-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 7px 12px; padding: 9px; }
.source-grid span { display: flex; align-items: center; gap: 5px; }
.source-grid i, .revision-docs i, .verification-fields i { display: inline-flex; align-items: center; justify-content: center; width: 13px; height: 13px; border: 1px solid #1f2933; font-style: normal; }
.source-grid i.checked::after { content: "✓"; font-size: 12px; font-weight: 700; }
.finding-text { min-height: 22mm; padding: 7px; margin: 0; white-space: pre-wrap; }
.finding-metadata { display: grid; grid-template-columns: 1fr 1fr; min-height: 13mm; border-top: 1px solid #1f2933; }
.finding-metadata > * { padding: 6px; }
.finding-metadata > * + * { border-left: 1px solid #1f2933; }
.reply-row { display: grid; grid-template-columns: 1.1fr 1fr; min-height: 47mm; border: 1px solid #1f2933; border-top: 0; }
.illustration-area { display: grid; grid-template-rows: auto 1fr; min-width: 0; border-right: 1px solid #1f2933; }
.illustration-area > strong { padding: 5px; border-bottom: 1px solid #1f2933; }
.illustration-content { position: relative; display: flex; align-items: center; justify-content: center; min-height: 40mm; padding: 6px; }
.illustration-content img { max-width: 100%; max-height: 42mm; object-fit: contain; }
.short-name { position: absolute; top: 0; right: 0; min-width: 38px; padding: 4px 7px; background: #fff2a8; border-left: 1px solid #e8d77b; border-bottom: 1px solid #e8d77b; text-align: center; }
.signatures { display: grid; grid-template-rows: auto auto 1fr; text-align: center; }
.signature-heading, .signature-functions { display: grid; grid-template-columns: 1fr 1fr; }
.signature-heading > *, .signature-functions > * { padding: 4px; border-bottom: 1px solid #1f2933; }
.signature-heading > * + *, .signature-functions > * + * { border-left: 1px solid #1f2933; }
.signature-spaces { display: grid; grid-template-columns: 1fr 1fr; min-height: 30mm; }
.signature-box { position: relative; display: flex; align-items: center; justify-content: center; min-width: 0; padding: 3px; overflow: hidden; }
.signature-box + .signature-box { border-left: 1px solid #1f2933; }
.signature-box img { width: 100%; height: 100%; min-height: 29mm; object-fit: contain; }
.signature-name { position: absolute; right: 0; bottom: 0; left: 0; padding: 2px; background: rgb(255 255 255 / 90%); font-size: 10px; }
.follow-up { border-top: 0; }
.follow-up-grid { display: grid; grid-template-columns: 1.1fr .42fr 1.2fr; min-height: 25mm; border-bottom: 1px solid #1f2933; }
.temporary-action { display: grid; align-content: start; gap: 5px; padding: 5px; border-right: 1px solid #1f2933; }
.received-date { display: grid; grid-template-rows: auto 1fr; text-align: center; border-right: 1px solid #1f2933; }
.received-date > * { padding: 5px; }
.received-date > span { border-bottom: 1px solid #1f2933; }
.receiver-section { display: grid; grid-template-rows: auto auto 1fr; text-align: center; }
.receiver-section > strong { padding: 4px; border-bottom: 1px solid #1f2933; }
.receiver-headings, .receiver-signatures { display: grid; grid-template-columns: 1fr 1fr; }
.receiver-headings > * { padding: 4px; border-bottom: 1px solid #1f2933; }
.receiver-headings > * + *, .receiver-signatures > * + * { border-left: 1px solid #1f2933; }
.receiver-signatures { min-height: 17mm; }
.analysis-heading, .analysis-row { display: grid; grid-template-columns: .3fr 1.2fr 1.45fr .55fr .95fr; }
.analysis-heading { border-bottom: 1px solid #1f2933; text-align: center; }
.analysis-heading > * { padding: 5px 2px; }
.analysis-heading > * + * { border-left: 1px solid #1f2933; }
.analysis-row { min-height: 23mm; }
.analysis-row > * { padding: 6px 4px; border-right: 1px solid #1f2933; }
.analysis-row > *:last-child { border-right: 0; }
.schedule-cells { display: grid; grid-template-rows: 1fr 1fr; padding: 0 !important; }
.schedule-cells i + i { border-top: 1px solid #1f2933; }
.verification { display: grid; grid-template-columns: 2fr .9fr; }
.verification-notes, .verification-date { display: grid; align-content: start; gap: 5px; padding: 7px; }
.revision-docs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3px 6px; }
.revision-docs span, .verification-fields > span { display: flex; align-items: center; gap: 5px; }
.verification-date { grid-template-rows: auto 1fr; border-left: 1px solid #1f2933; }
.verification-date strong { padding-bottom: 5px; border-bottom: 1px solid #1f2933; text-align: center; }
.final-verification h2 { font-size: 10px; }
.final-status { display: grid; grid-template-columns: 1.25fr 1fr; min-height: 31mm; }
.verification-fields { display: grid; grid-template-columns: auto 1fr 1fr; align-content: start; align-items: center; gap: 4px 7px; padding: 6px; }
.verification-fields > strong, .verification-fields > p, .verification-fields > em { grid-column: 1 / -1; }
.verification-fields p { margin: 3px 0 0; font-style: italic; }
.verification-fields em { font-size: 9px; }
.final-signature { display: grid; grid-template-rows: auto 1fr; border-left: 1px solid #1f2933; text-align: center; }
.final-signature > strong { padding: 4px; border-bottom: 1px solid #1f2933; }
.final-signature > div { display: grid; grid-template-columns: .6fr 1fr; }
.final-signature > div > * + * { border-left: 1px solid #1f2933; }
.document-footer { padding-top: 5px; font-size: 8px; font-style: italic; font-weight: 700; }
@media (max-width: 600px) {
    .ptpp-sheet { padding: 10px; font-size: 9px; }
    .letter-header { grid-template-columns: 1fr 1.6fr .7fr; }
    .title-cell h1 { font-size: 10px; }
    .title-cell strong { font-size: 10px; }
    .address-grid { grid-template-columns: 1fr; }
    .location-cell { border-top: 1px solid #1f2933; border-left: 0; }
    .address-rows > div { grid-template-columns: 30% minmax(0, 1fr) auto minmax(0, .8fr); }
    .finding-metadata { grid-template-columns: 1fr; }
    .finding-metadata > * + * { border-top: 1px solid #1f2933; border-left: 0; }
    .reply-row { grid-template-columns: 1fr; }
    .illustration-area { border-right: 0; border-bottom: 1px solid #1f2933; }
    .analysis-heading, .analysis-row { grid-template-columns: .3fr 1fr 1fr .65fr .8fr; font-size: 8px; }
    .follow-up-grid { grid-template-columns: 1fr .45fr 1fr; }
    .verification { grid-template-columns: 1fr .8fr; }
}
</style>
