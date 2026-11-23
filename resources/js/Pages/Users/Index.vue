
<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconUsers,
    IconUserCheck,
    IconUserX,
    IconSearch,
    IconPlus,
    IconEdit,
    IconX,
    IconShieldCheck,
    IconRefresh,
    IconTrash,
} from '@tabler/icons-vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import TablePagination from '../../Components/TablePagination.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    users: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: {
        type: Object,
        default: () => ({ total: 0, active: 0, inactive: 0 }),
    },
});

const search = ref(props.filters.search ?? '');
const roleId = ref(props.filters.role_id ?? '');
const status = ref(props.filters.status ?? '');

const modalOpen = ref(false);
const selectedUser = ref(null);
const confirmation = ref(null);
const toast = ref('');
const actionError = ref('');
const actionProcessing = ref(false);
let toastTimer;

const isEditing = computed(() => selectedUser.value !== null);

const form = useForm({
    name: '',
    email: '',
    role_id: '',
    password: '',
    password_confirmation: '',
});

function showToast(message) {
    toast.value = message;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.value = '';
    }, 3000);
}

function applyFilters() {
    router.get('/users', {
        search: search.value || undefined,
        role_id: roleId.value || undefined,
        status: status.value || undefined,
        per_page: props.users.per_page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    search.value = '';
    roleId.value = '';
    status.value = '';
    applyFilters();
}

function openCreateModal() {
    selectedUser.value = null;
    actionError.value = '';
    form.reset();
    form.clearErrors();
    form.role_id = props.roles[0]?.id ?? '';
    modalOpen.value = true;
}

function openEditModal(user) {
    selectedUser.value = user;
    actionError.value = '';
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.role_id = user.role_id;
    form.password = '';
    form.password_confirmation = '';
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    selectedUser.value = null;
    actionError.value = '';
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (isEditing.value) {
        form.put(`/users/${selectedUser.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                showToast('Data pengguna berhasil diperbarui.');
            },
        });
        return;
    }

    form.post('/users', {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            showToast('Pengguna berhasil ditambahkan.');
        },
    });
}

function toggleStatus(user) {
    actionError.value = '';
    confirmation.value = {
        type: 'status',
        user,
    };
}

function requestDeleteSelectedUser() {
    if (!selectedUser.value) {
        return;
    }
    actionError.value = '';
    confirmation.value = {
        type: 'delete',
        user: selectedUser.value,
        stage: 1,
    };
}

function closeConfirmation() {
    if (actionProcessing.value) {
        return;
    }

    confirmation.value = null;
    actionError.value = '';
}

function confirmAccountAction() {
    if (!confirmation.value || actionProcessing.value) {
        return;
    }

    if (confirmation.value.type === 'delete' && confirmation.value.stage === 1) {
        confirmation.value = {
            ...confirmation.value,
            stage: 2,
        };
        return;
    }

    const { type, user } = confirmation.value;
    actionError.value = '';
    actionProcessing.value = true;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            confirmation.value = null;
            actionProcessing.value = false;

            if (type === 'delete') {
                closeModal();
                showToast('Akun pengguna berhasil dihapus.');
                return;
            }

            showToast(
                user.is_active
                    ? 'Akun berhasil dinonaktifkan.'
                    : 'Akun berhasil diaktifkan.'
            );
        },
        onError: (errors) => {
            actionError.value = errors.user ?? (
                type === 'delete'
                    ? 'Akun gagal dihapus.'
                    : 'Status akun gagal diperbarui.'
            );
        },
        onFinish: () => {
            actionProcessing.value = false;
        },
    };

    if (type === 'delete') {
        router.delete(`/users/${user.id}`, options);
        return;
    }

    router.patch(`/users/${user.id}/status`, {}, options);
}

</script>

<template>
    <Head title="Manajemen Pengguna" />

    <section class="users-page">
        <header class="page-heading">
            <div>
                <h1>Manajemen Pengguna</h1>
                <p>Kelola akun, hak akses, dan status pengguna sistem.</p>
            </div>

            <button class="btn btn-primary" type="button" @click="openCreateModal">
                <IconPlus :size="18" />
                <span>Tambah Pengguna</span>
            </button>
        </header>

        <div class="summary-grid">
            <article class="summary-card">
                <div class="summary-icon icon-blue">
                    <IconUsers :size="22" />
                </div>
                <div>
                    <p>Total Pengguna</p>
                    <strong>{{ props.summary.total }}</strong>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon icon-green">
                    <IconUserCheck :size="22" />
                </div>
                <div>
                    <p>Akun Aktif</p>
                    <strong>{{ props.summary.active }}</strong>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon icon-orange">
                    <IconUserX :size="22" />
                </div>
                <div>
                    <p>Akun Nonaktif</p>
                    <strong>{{ props.summary.inactive }}</strong>
                </div>
            </article>
        </div>

        <div class="content-card">
            <div class="content-heading">
                <div>
                    <h2>Daftar Pengguna</h2>
                    <p>Data akun yang terdaftar dalam sistem.</p>
                </div>
                <span class="total-label">{{ props.users.total }} pengguna</span>
            </div>

            <form class="filter-bar" @submit.prevent="applyFilters">
                <label class="search-box">
                    <IconSearch :size="18" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama atau email..."
                    />
                </label>

                <select v-model="roleId" aria-label="Filter berdasarkan role">
                    <option value="">Semua Role</option>
                    <option v-for="role in props.roles" :key="role.id" :value="role.id">
                        {{ role.label }}
                    </option>
                </select>

                <select v-model="status" aria-label="Filter berdasarkan status">
                    <option value="">Semua Status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>

                <button class="btn btn-secondary" type="submit">Terapkan</button>
                <button
                    class="btn btn-icon"
                    type="button"
                    title="Reset filter"
                    aria-label="Reset filter"
                    @click="resetFilters"
                >
                    <IconRefresh :size="18" />
                </button>
            </form>

            <div class="table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Nama Pengguna</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="action-column">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in props.users.data" :key="user.id">
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">
                                        {{ (user.name || '?').charAt(0).toUpperCase() }}
                                    </div>
                                    <strong class="user-name">{{ user.name }}</strong>
                                </div>
                            </td>

                            <td class="email-cell">{{ user.email }}</td>

                            <td>
                                <span class="role-badge">
                                    <IconShieldCheck :size="14" />
                                    {{ user.role?.label ?? user.role?.name ?? 'Belum ada role' }}
                                </span>
                            </td>

                            <td>
                                <span
                                    class="status-badge"
                                    :class="user.is_active ? 'status-active' : 'status-inactive'"
                                >
                                    <span class="status-dot"></span>
                                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <button
                                        class="action-button action-edit"
                                        type="button"
                                        title="Edit pengguna"
                                        aria-label="Edit pengguna"
                                        @click="openEditModal(user)"
                                    >
                                        <IconEdit :size="17" />
                                    </button>

                                    <button
                                        class="action-button"
                                        :class="user.is_active ? 'action-disable' : 'action-enable'"
                                        type="button"
                                        :title="user.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'"
                                        :aria-label="user.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'"
                                        @click="toggleStatus(user)"
                                    >
                                        <IconUserX v-if="user.is_active" :size="17" />
                                        <IconUserCheck v-else :size="17" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="props.users.data.length === 0">
                            <td colspan="5">
                                <div class="empty-state">
                                    <IconUsers :size="34" stroke-width="1.5" />
                                    <strong>Pengguna tidak ditemukan</strong>
                                    <p>Coba ubah kata kunci atau filter pencarian.</p>
                                    <button
                                        class="btn btn-secondary"
                                        type="button"
                                        @click="resetFilters"
                                    >
                                        Reset Filter
                                    </button>
                                </div>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <TablePagination :pagination="props.users" label="pengguna" />
        </div>
    </section>

    <div v-if="modalOpen" class="modal-overlay" @click.self="closeModal">
        <section
            class="user-modal"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="'user-modal-title'"
        >
            <header class="modal-heading">
                <div>
                    <h2 id="user-modal-title">
                        {{ isEditing ? 'Edit Pengguna' : 'Tambah Pengguna' }}
                    </h2>
                    <p>
                        {{
                            isEditing
                                ? 'Perbarui informasi dan hak akses akun.'
                                : 'Masukkan informasi untuk membuat akun baru.'
                        }}
                    </p>
                </div>

                <button
                    class="btn btn-icon"
                    type="button"
                    aria-label="Tutup modal"
                    @click="closeModal"
                >
                    <IconX :size="20" />
                </button>
            </header>

            <form class="modal-form" @submit.prevent="submitForm">
                <div class="form-field">
                    <label for="user-name">Nama lengkap</label>
                    <input id="user-name" v-model="form.name" type="text" autocomplete="name" required />
                    <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
                </div>

                <div class="form-field">
                    <label for="user-email">Alamat email</label>
                    <input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                    />
                    <small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small>
                </div>

                <div class="form-field">
                    <label for="user-role">Role pengguna</label>
                    <select id="user-role" v-model="form.role_id" required>
                        <option value="" disabled>Pilih role</option>
                        <option v-for="role in props.roles" :key="role.id" :value="role.id">
                            {{ role.label }}
                        </option>
                    </select>
                    <small v-if="form.errors.role_id" class="field-error">{{ form.errors.role_id }}</small>
                </div>

                <div v-if="!isEditing" class="form-field">
                    <label for="user-password">Password</label>
                    <input
                        id="user-password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    />
                    <small class="field-hint">Minimal 8 karakter.</small>
                    <small v-if="form.errors.password" class="field-error">{{ form.errors.password }}</small>
                </div>

                <div v-if="!isEditing" class="form-field">
                    <label for="user-password-confirmation">Konfirmasi password</label>
                    <input
                        id="user-password-confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                </div>

                <p v-if="isEditing" class="form-note">
                    Password tidak diubah melalui formulir ini.
                </p>

                <section v-if="isEditing" class="account-actions" aria-label="Tindakan akun">
                    <button
                        class="btn btn-danger"
                        type="button"
                        :disabled="form.processing"
                        @click="requestDeleteSelectedUser"
                    >
                        <IconTrash :size="16" />
                        Hapus akun
                    </button>
                </section>

                <footer class="modal-actions">
                    <button class="btn btn-secondary" type="button" @click="closeModal">
                        Batal
                    </button>
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? 'Menyimpan...'
                                : isEditing
                                    ? 'Simpan Perubahan'
                                    : 'Buat Pengguna'
                        }}
                    </button>
                </footer>
            </form>
        </section>
    </div>

    <Transition name="confirm">
        <div
            v-if="confirmation"
            class="confirmation-overlay"
            @click.self="closeConfirmation"
        >
            <section
                class="confirmation-modal"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="confirmation-title"
                aria-describedby="confirmation-description"
            >
                <div
                    class="confirmation-icon"
                    :class="confirmation.type === 'delete' || confirmation.user.is_active
                        ? 'confirmation-icon-danger'
                        : 'confirmation-icon-success'"
                >
                    <IconTrash v-if="confirmation.type === 'delete'" :size="22" />
                    <IconUserX v-else-if="confirmation.user.is_active" :size="22" />
                    <IconUserCheck v-else :size="22" />
                </div>

                <h2 id="confirmation-title">
                    {{
                        confirmation.type === 'delete' && confirmation.stage === 2
                            ? 'Konfirmasi terakhir'
                            : confirmation.type === 'delete'
                                ? 'Hapus akun karyawan?'
                            : confirmation.user.is_active
                                ? 'Nonaktifkan akun karyawan?'
                                : 'Aktifkan kembali akun?'
                    }}
                </h2>
                <p id="confirmation-description">
                    {{
                        confirmation.type === 'delete' && confirmation.stage === 2
                            ? `Konfirmasi terakhir: hapus permanen akun ${confirmation.user.email}? Tindakan ini tidak dapat dibatalkan.`
                            : confirmation.type === 'delete'
                                ? `Anda akan menghapus akun ${confirmation.user.name} (${confirmation.user.email}). Lanjutkan untuk konfirmasi penghapusan permanen.`
                            : confirmation.user.is_active
                                ? `Akun ${confirmation.user.name} tidak akan dapat digunakan untuk login sampai diaktifkan kembali.`
                                : `Akun ${confirmation.user.name} akan dapat login kembali setelah diaktifkan.`
                    }}
                </p>
                <small v-if="actionError" class="field-error confirmation-error" role="alert">
                    {{ actionError }}
                </small>

                <footer class="confirmation-actions">
                    <button
                        class="btn confirmation-cancel"
                        type="button"
                        :disabled="actionProcessing"
                        @click="closeConfirmation"
                    >
                        Batal
                    </button>
                    <button
                        class="btn"
                        :class="confirmation.type === 'delete' || confirmation.user.is_active
                            ? 'btn-danger-solid'
                            : 'btn-success-solid'"
                        type="button"
                        :disabled="actionProcessing"
                        @click="confirmAccountAction"
                    >
                        {{
                            actionProcessing
                                ? 'Memproses...'
                                : confirmation.type === 'delete'
                                    ? confirmation.stage === 2
                                        ? 'Ya, hapus permanen'
                                        : 'Lanjutkan'
                                    : confirmation.user.is_active
                                        ? 'Ya, nonaktifkan'
                                        : 'Ya, aktifkan'
                        }}
                    </button>
                </footer>
            </section>
        </div>
    </Transition>

    <Transition name="toast">
        <div v-if="toast" class="toast-message" role="status">
            <span class="toast-check">✓</span>
            {{ toast }}
        </div>
    </Transition>
</template>

<style scoped>
.users-page {
    color: #182433;
    font-size: 14px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.page-heading h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    letter-spacing: -0.5px;
}

.page-heading p,
.content-heading p,
.modal-heading p {
    margin: 6px 0 0;
    color: #667382;
    font-size: 13px;
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    min-height: 36px;
    padding: 8px 13px;
    border: 1px solid transparent;
    border-radius: 4px;
    font: inherit;
    font-weight: 500;
    cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
}

.btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.btn-primary {
    color: #fff;
    background: #066fd1;
    border-color: #066fd1;
}

.btn-primary:hover:not(:disabled) {
    background: #055eb3;
}

.btn-secondary {
    color: #344054;
    background: #fff;
    border-color: #dce1e7;
}

.btn-secondary:hover {
    background: #f6f8fb;
    border-color: #b9c3d0;
}

.btn-icon {
    width: 36px;
    min-width: 36px;
    padding: 7px;
    color: #667382;
    background: #fff;
    border-color: #e1e5eb;
}

.btn-icon:hover {
    background: #f6f8fb;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 94px;
    padding: 18px;
    background: #fff;
    border: 1px solid #e6e7e9;
    border-radius: 4px;
}

.summary-icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 4px;
}

.icon-blue {
    color: #066fd1;
    background: #eaf3fc;
}

.icon-green {
    color: #2b8a3e;
    background: #eaf7ed;
}

.icon-orange {
    color: #c77716;
    background: #fff4e5;
}

.summary-card p {
    margin: 0 0 5px;
    color: #667382;
    font-size: 13px;
}

.summary-card strong {
    font-size: 23px;
    font-weight: 600;
    line-height: 1.2;
}

.content-card {
    overflow: hidden;
    background: #fff;
    border: 1px solid #e6e7e9;
    border-radius: 4px;
}

.content-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 20px 22px;
}

.content-heading h2,
.modal-heading h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.total-label {
    padding: 5px 9px;
    color: #526171;
    background: #f1f4f8;
    border-radius: 4px;
    font-size: 12px;
}

.filter-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 22px;
    background: #fbfcfd;
    border-top: 1px solid #e6e7e9;
    border-bottom: 1px solid #e6e7e9;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1 1 220px;
    max-width: 340px;
    height: 36px;
    padding: 0 10px;
    color: #8793a1;
    background: #fff;
    border: 1px solid #dce1e7;
    border-radius: 4px;
}

.search-box:focus-within {
    border-color: #066fd1;
    box-shadow: 0 0 0 2px rgb(6 111 209 / 10%);
}

.search-box input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    color: #182433;
    background: transparent;
    font: inherit;
}

.filter-bar select,
.form-field input,
.form-field select {
    height: 36px;
    padding: 0 10px;
    color: #344054;
    background: #fff;
    border: 1px solid #dce1e7;
    border-radius: 4px;
    font: inherit;
    outline: none;
}

.filter-bar select:focus,
.form-field input:focus,
.form-field select:focus {
    border-color: #066fd1;
    box-shadow: 0 0 0 2px rgb(6 111 209 / 10%);
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.users-table th {
    padding: 12px 22px;
    color: #667382;
    background: #fff;
    border-bottom: 1px solid #e6e7e9;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.users-table td {
    padding: 15px 22px;
    border-bottom: 1px solid #edf0f3;
    vertical-align: middle;
}

.users-table tbody tr:last-child td {
    border-bottom: 0;
}

.users-table tbody tr:hover {
    background: #fbfcfe;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 230px;
}

.user-avatar {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    color: #066fd1;
    background: #eaf3fc;
    border-radius: 50%;
    font-size: 15px;
    font-weight: 600;
}

.user-identity {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.user-identity strong {
    color: #182433;
    font-size: 13px;
    font-weight: 600;
}

.user-identity span {
    color: #667382;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.role-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 500;
}

.role-badge {
    padding: 5px 8px;
    color: #526171;
    background: #f1f4f8;
    border-radius: 4px;
}

.status-badge {
    padding: 5px 8px;
    border-radius: 4px;
}

.status-active {
    color: #24743a;
    background: #eaf7ed;
}

.status-inactive {
    color: #9a5b14;
    background: #fff4e5;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.action-column {
    width: 110px;
    text-align: center;
}

.row-actions {
    display: flex;
    justify-content: center;
    gap: 6px;
}

.action-button {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    color: #667382;
    background: #fff;
    border: 1px solid #e1e5eb;
    border-radius: 4px;
    cursor: pointer;
}

.action-button:hover {
    color: #066fd1;
    background: #f0f7ff;
    border-color: #c7e0fa;
}


/* Edit: hijau */
.action-button.action-edit {
    color: #218739;
    background: #eaf7ed;
    border-color: #c8e8cf;
}

.action-button.action-edit:hover {
    color: #17692a;
    background: #d8f0de;
    border-color: #9fd5ad;
}

/* Nonaktifkan akun: merah */
.action-button.action-disable {
    color: #c0392b;
    background: #fff0ee;
    border-color: #f3c7c2;
}

.action-button.action-disable:hover {
    color: #a52a1e;
    background: #ffe0dc;
    border-color: #e9a7a0;
}

/* Aktifkan akun: hijau */
.action-button.action-enable {
    color: #218739;
    background: #eaf7ed;
    border-color: #c8e8cf;
}

.action-button.action-enable:hover {
    color: #17692a;
    background: #d8f0de;
    border-color: #9fd5ad;
}

.email-cell {
    min-width: 220px;
    color: #526171;
    overflow-wrap: anywhere;
}

.user-name {
    color: #182433;
    font-size: 13px;
    font-weight: 600;
}

.action-button.action-disable:hover {
    color: #b54708;
    background: #fff8eb;
    border-color: #f5d6a8;
}

.action-button.action-enable:hover {
    color: #24743a;
    background: #eaf7ed;
    border-color: #b6e2c0;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 46px 16px;
    color: #8793a1;
    text-align: center;
}

.empty-state strong {
    color: #344054;
    font-size: 14px;
}

.empty-state p {
    margin: 0 0 6px;
    font-size: 13px;
}

.pagination-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    padding: 16px 22px;
    border-top: 1px solid #e6e7e9;
}

.pagination-bar p {
    margin: 0;
    color: #667382;
    font-size: 12px;
}

.pagination-bar p strong {
    color: #344054;
}

.pagination-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pagination-button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 32px;
    padding: 5px 9px;
    color: #344054;
    background: #fff;
    border: 1px solid #dce1e7;
    border-radius: 4px;
    font: inherit;
    font-size: 12px;
    cursor: pointer;
}

.pagination-button:hover:not(:disabled) {
    background: #f6f8fb;
}

.pagination-button:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.page-indicator {
    color: #667382;
    font-size: 12px;
    white-space: nowrap;
}

.modal-overlay {
    position: fixed;
    z-index: 1000;
    inset: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    overflow-y: auto;
    background: rgb(24 36 51 / 42%);
}

.user-modal {
    width: 100%;
    max-width: 500px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e6e7e9;
    border-radius: 6px;
    box-shadow: 0 16px 48px rgb(24 36 51 / 18%);
}

.modal-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 22px;
    border-bottom: 1px solid #e6e7e9;
}

.modal-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 22px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-field label {
    color: #344054;
    font-size: 13px;
    font-weight: 500;
}

.form-field input,
.form-field select {
    width: 100%;
    height: 39px;
    box-sizing: border-box;
}

.field-hint {
    color: #8793a1;
    font-size: 12px;
}

.field-error {
    color: #c0392b;
    font-size: 12px;
}

.form-note {
    margin: 0;
    padding: 10px 12px;
    color: #526171;
    background: #f6f8fb;
    border-radius: 4px;
    font-size: 12px;
}

.account-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px 0;
    border-top: 1px solid #edf0f3;
    border-bottom: 1px solid #edf0f3;
}

.btn-danger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 8px 12px;
    color: #fff;
    background: #d63939;
    border: 1px solid #d63939;
    border-radius: 4px;
    font: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.btn-danger:hover:not(:disabled) {
    background: #b02d2d;
    border-color: #b02d2d;
}

.btn-danger:disabled {
    opacity: .55;
    cursor: not-allowed;
}

.confirmation-overlay {
    position: fixed;
    z-index: 1200;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgb(24 36 51 / 48%);
}

.confirmation-modal {
    width: min(100%, 420px);
    padding: 28px;
    background: #fff;
    border: 1px solid #e6e7e9;
    border-radius: 6px;
    box-shadow: 0 16px 32px rgb(24 36 51 / 12%);
    text-align: center;
}

.confirmation-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    margin: 0 auto 16px;
    border-radius: 50%;
}

.confirmation-icon-danger {
    color: #d63939;
    background: #fbebeb;
}

.confirmation-icon-success {
    color: #2fb344;
    background: #eaf7ec;
}

.confirmation-modal h2 {
    margin: 0;
    color: #182433;
    font-size: 18px;
    font-weight: 600;
}

.confirmation-modal p {
    margin: 10px 0 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

.confirmation-error {
    display: block;
    margin-top: 12px;
}

.confirmation-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 24px;
}

.confirmation-cancel {
    color: #182433;
    background: #fff;
    border-color: #e6e7e9;
}

.confirmation-cancel:hover:not(:disabled) {
    background: #f6f8fb;
}

.btn-danger-solid,
.btn-success-solid {
    color: #fff;
    border-color: transparent;
}

.btn-danger-solid {
    background: #d63939;
}

.btn-danger-solid:hover:not(:disabled) {
    background: #b02d2d;
}

.btn-success-solid {
    background: #2fb344;
}

.btn-success-solid:hover:not(:disabled) {
    background: #26943a;
}

.confirmation-actions .btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.confirm-enter-active,
.confirm-leave-active {
    transition: opacity .16s ease;
}

.confirm-enter-active .confirmation-modal,
.confirm-leave-active .confirmation-modal {
    transition: transform .16s ease;
}

.confirm-enter-from,
.confirm-leave-to {
    opacity: 0;
}

.confirm-enter-from .confirmation-modal,
.confirm-leave-to .confirmation-modal {
    transform: translateY(8px) scale(.98);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 8px;
    border-top: 1px solid #edf0f3;
}

.toast-message {
    position: fixed;
    z-index: 1100;
    right: 24px;
    bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    max-width: calc(100vw - 32px);
    padding: 13px 17px;
    color: #24743a;
    background: #fff;
    border: 1px solid #b6e2c0;
    border-radius: 4px;
    box-shadow: 0 5px 20px rgb(24 36 51 / 10%);
}

.toast-check {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    color: #fff;
    background: #2b8a3e;
    border-radius: 50%;
    font-size: 13px;
}

.toast-enter-active,
.toast-leave-active {
    transition: opacity .18s, transform .18s;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

@media (max-width: 900px) {
    .summary-grid {
        gap: 10px;
    }

    .summary-card {
        padding: 14px;
    }

    .filter-bar select {
        flex: 1 1 130px;
    }
}

@media (max-width: 640px) {
    .page-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .summary-card {
        min-height: 76px;
    }

    .content-heading,
    .filter-bar,
    .pagination-bar {
        padding-left: 14px;
        padding-right: 14px;
    }

    .search-box {
        max-width: none;
        flex-basis: 100%;
    }

    .filter-bar select {
        flex: 1 1 calc(50% - 8px);
        min-width: 0;
    }

    .users-table th,
    .users-table td {
        padding-left: 14px;
        padding-right: 14px;
    }

    .pagination-bar {
        align-items: flex-start;
        flex-direction: column;
    }

    .pagination-actions {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
