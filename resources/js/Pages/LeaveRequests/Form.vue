<script setup>
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { IconArrowLeft, IconDeviceFloppy } from "@tabler/icons-vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import LeaveSignaturePad from "@/Components/LeaveSignaturePad.vue";

defineOptions({ layout: AppLayout });

const props = defineProps({
    request: { type: Object, default: null },
    today: { type: String, required: true },
    employeeName: { type: String, required: true },
    action: { type: String, required: true },
});

const form = useForm({
    employee_name: props.request?.employee_name ?? props.employeeName,
    department: props.request?.department ?? "",
    work_location: props.request?.work_location ?? "",
    start_date: props.request?.start_date ?? "",
    end_date: props.request?.end_date ?? "",
    destination: props.request?.destination ?? "",
    reason: props.request?.reason ?? "",
    leave_entitlement_year: props.request?.leave_entitlement_year ?? new Date().getFullYear(),
    leave_entitlement_days: props.request?.leave_entitlement_days ?? "",
    leave_taken_days: props.request?.leave_taken_days ?? "",
    transportation: props.request?.transportation ?? "",
    expense_paid_by: props.request?.expense_paid_by ?? "",
    return_to_work_date: props.request?.return_to_work_date ?? "",
    leave_due_date: props.request?.leave_due_date ?? "",
    companions: [
        ...(props.request?.companions ?? []),
        ...Array.from({ length: Math.max(0, 3 - (props.request?.companions?.length ?? 0)) }, () => ({ name: "", notes: "" })),
    ],
    employee_signature: "",
});

const requestedDays = computed(() => {
    if (!form.start_date || !form.end_date) {
        return 0;
    }

    const startDate = Date.parse(`${form.start_date}T00:00:00Z`);
    const endDate = Date.parse(`${form.end_date}T00:00:00Z`);

    return endDate >= startDate
        ? Math.floor((endDate - startDate) / 86400000) + 1
        : 0;
});

const remainingDays = computed(() => {
    const entitlement = Number(form.leave_entitlement_days);
    const taken = Number(form.leave_taken_days);

    return Number.isFinite(entitlement) && Number.isFinite(taken)
        ? entitlement - taken - requestedDays.value
        : 0;
});

const returnDateMinimum = computed(() => {
    if (!form.end_date) {
        return props.today;
    }

    const [year, month, day] = form.end_date.split("-").map(Number);
    const nextDay = new Date(year, month - 1, day + 1);
    const nextYear = nextDay.getFullYear();
    const nextMonth = String(nextDay.getMonth() + 1).padStart(2, "0");
    const nextDate = String(nextDay.getDate()).padStart(2, "0");

    return `${nextYear}-${nextMonth}-${nextDate}`;
});

function submit() {
    const options = { preserveScroll: true };

    if (props.request) {
        form.put(props.action, options);
    } else {
        form.post(props.action, options);
    }
}
</script>

<template>
    <Head :title="request ? 'Perbaiki Pengajuan Cuti' : 'Ajukan Cuti'" />

    <section class="leave-page">
        <Link href="/leave-requests" class="back-link">
            <IconArrowLeft :size="17" /> Kembali ke riwayat
        </Link>
        <header class="page-heading">
            <div>
                <p class="eyebrow">Dokumen Kepegawaian / Formulir</p>
                <h1>{{ request ? "Perbaiki Pengajuan Cuti" : "Pengajuan Cuti" }}</h1>
                <p>Isi data sesuai formulir internal dan bubuhkan tanda tangan sebelum mengirim.</p>
            </div>
        </header>

        <div v-if="request?.review_notes" class="revision-note">
            <strong>Catatan peninjau</strong>
            <p>{{ request.review_notes }}</p>
        </div>

        <form class="leave-card form-card" @submit.prevent="submit">
            <div class="form-section">
                <h2>Data pemohon</h2>
                <div class="field-grid">
                    <label class="field">
                        <span>Nama pemohon</span>
                        <input v-model="form.employee_name" maxlength="255" required />
                        <small v-if="form.errors.employee_name">{{ form.errors.employee_name }}</small>
                    </label>
                    <label class="field">
                        <span>Bagian / Departemen</span>
                        <input v-model="form.department" maxlength="150" required />
                        <small v-if="form.errors.department">{{ form.errors.department }}</small>
                    </label>
                    <label class="field">
                        <span>Lokasi kerja</span>
                        <input v-model="form.work_location" maxlength="150" required />
                        <small v-if="form.errors.work_location">{{ form.errors.work_location }}</small>
                    </label>
                </div>
            </div>

            <div class="form-section">
                <h2>Rencana cuti</h2>
                <div class="field-grid">
                    <label class="field">
                        <span>Tanggal mulai</span>
                        <input v-model="form.start_date" type="date" :min="today" required />
                        <small v-if="form.errors.start_date">{{ form.errors.start_date }}</small>
                    </label>
                    <label class="field">
                        <span>Tanggal selesai</span>
                        <input v-model="form.end_date" type="date" :min="form.start_date || today" required />
                        <small v-if="form.errors.end_date">{{ form.errors.end_date }}</small>
                    </label>
                    <label class="field">
                        <span>Tempat tujuan / negara</span>
                        <input v-model="form.destination" maxlength="255" required />
                        <small v-if="form.errors.destination">{{ form.errors.destination }}</small>
                    </label>
                    <label class="field">
                        <span>Tanggal masuk kerja kembali</span>
                        <input v-model="form.return_to_work_date" type="date" :min="returnDateMinimum" required />
                        <small v-if="form.errors.return_to_work_date">{{ form.errors.return_to_work_date }}</small>
                    </label>
                    <label class="field">
                        <span>Hak cuti tahun ke</span>
                        <input v-model="form.leave_entitlement_year" type="number" min="2000" max="2100" required />
                        <small v-if="form.errors.leave_entitlement_year">{{ form.errors.leave_entitlement_year }}</small>
                    </label>
                    <label class="field">
                        <span>Hak cuti tahunan (hari)</span>
                        <input v-model="form.leave_entitlement_days" type="number" min="0" max="366" required />
                        <small v-if="form.errors.leave_entitlement_days">{{ form.errors.leave_entitlement_days }}</small>
                    </label>
                    <label class="field">
                        <span>Cuti yang telah diambil (hari)</span>
                        <input v-model="form.leave_taken_days" type="number" min="0" max="366" required />
                        <small v-if="form.errors.leave_taken_days">{{ form.errors.leave_taken_days }}</small>
                    </label>
                    <label class="field">
                        <span>Sisa cuti (hari)</span>
                        <input :value="remainingDays" disabled />
                    </label>
                    <label class="field">
                        <span>Cuti jatuh tempo pada</span>
                        <input v-model="form.leave_due_date" type="date" :min="form.end_date || today" />
                        <small v-if="form.errors.leave_due_date">{{ form.errors.leave_due_date }}</small>
                    </label>
                    <label class="field">
                        <span>Kendaraan</span>
                        <input v-model="form.transportation" maxlength="100" placeholder="Contoh: kendaraan pribadi" required />
                        <small v-if="form.errors.transportation">{{ form.errors.transportation }}</small>
                    </label>
                    <label class="field">
                        <span>Biaya ditanggung oleh</span>
                        <input v-model="form.expense_paid_by" maxlength="100" placeholder="Contoh: pribadi" required />
                        <small v-if="form.errors.expense_paid_by">{{ form.errors.expense_paid_by }}</small>
                    </label>
                </div>
                <label class="field">
                    <span>Keperluan / keterangan</span>
                    <textarea v-model="form.reason" rows="4" maxlength="5000" required />
                    <small v-if="form.errors.reason">{{ form.errors.reason }}</small>
                </label>
            </div>

            <div class="form-section">
                <h2>Nama pengikut (opsional)</h2>
                <div v-for="(companion, index) in form.companions" :key="index" class="field-grid companion-row">
                    <label class="field">
                        <span>Pengikut {{ index + 1 }}</span>
                        <input v-model="companion.name" maxlength="150" />
                        <small v-if="form.errors[`companions.${index}.name`]">{{ form.errors[`companions.${index}.name`] }}</small>
                    </label>
                    <label class="field">
                        <span>Keterangan</span>
                        <input v-model="companion.notes" maxlength="150" />
                        <small v-if="form.errors[`companions.${index}.notes`]">{{ form.errors[`companions.${index}.notes`] }}</small>
                    </label>
                </div>
            </div>

            <div class="form-section">
                <h2>Pernyataan pengirim</h2>
                <p class="section-hint">Tanda tangan ini milik akun yang mengirim pengajuan. Jika mengajukan atas nama rekan, tanda tangan ini bukan tanda tangan pemohon yang dipilih.</p>
                <LeaveSignaturePad
                    v-model="form.employee_signature"
                    label="Tanda tangan pengirim"
                    :error="form.errors.employee_signature"
                />
            </div>

            <div class="form-actions">
                <Link href="/leave-requests" class="secondary-button">Batal</Link>
                <button class="primary-button" type="submit" :disabled="form.processing">
                    <IconDeviceFloppy :size="18" />
                    {{ form.processing ? "Mengirim..." : request ? "Kirim perbaikan" : "Kirim pengajuan" }}
                </button>
            </div>
        </form>
    </section>
</template>

<style scoped>
.leave-page { display: grid; gap: 18px; color: #182433; }
.back-link { display: flex; align-items: center; gap: 7px; color: #536273; text-decoration: none; }
.page-heading h1 { margin: 4px 0 6px; font-size: 25px; }
.page-heading p, .section-hint { margin: 0; color: #667382; }
.eyebrow { font-size: 12px; }
.leave-card { background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; }
.form-card { padding: 24px; }
.form-section + .form-section { padding-top: 24px; margin-top: 24px; border-top: 1px solid #edf0f2; }
.form-section h2 { margin: 0 0 16px; font-size: 16px; }
.field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; margin-bottom: 15px; }
.field { display: grid; gap: 6px; min-width: 0; margin-bottom: 15px; color: #354252; font-size: 13px; font-weight: 600; }
.field input, .field textarea { width: 100%; padding: 10px 11px; color: #182433; background: #fff; border: 1px solid #cbd4de; border-radius: 5px; font: inherit; font-weight: 400; }
.field textarea { resize: vertical; }
.field input:disabled { background: #f6f8fb; color: #667382; }
.field small { color: #be123c; font-weight: 400; }
.revision-note { padding: 14px 16px; color: #7c4600; background: #fff7e6; border: 1px solid #f4d28e; border-radius: 6px; }
.revision-note p { margin: 5px 0 0; }
.section-hint { margin: -6px 0 16px; font-size: 12px; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid #edf0f2; }
.primary-button, .secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 9px 14px; border-radius: 5px; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
.primary-button { color: #fff; background: #066fd1; border: 1px solid #066fd1; }
.primary-button:disabled { opacity: .6; cursor: wait; }
.secondary-button { color: #354252; background: #fff; border: 1px solid #cbd4de; }
@media (max-width: 680px) { .form-card { padding: 16px; } .field-grid { grid-template-columns: 1fr; gap: 0; } .form-actions { flex-direction: column-reverse; } }
</style>
