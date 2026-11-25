<script setup>
import { computed, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import {
    IconLayoutDashboard,
    IconUsers,
    IconFileText,
    IconPackage,
    IconMessageCircle,
    IconClipboardCheck,
    IconReportAnalytics,
    IconHistory,
    IconUserCircle,
    IconChevronLeft,
    IconChevronRight,
    IconChevronDown,
    IconX,
    IconLogout,
    IconPrinter,
    IconTags,
} from "@tabler/icons-vue";
import pertaminaLogo from "../../../logo pertamina.jpg";

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
    mobileOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["toggle", "close"]);

const page = usePage();

const currentUrl = computed(() => page.url.split("?")[0]);
const currentQuery = computed(() => new URLSearchParams(page.url.split("?")[1] ?? ""));

const expandedMenus = ref({
    inventory: currentUrl.value.startsWith("/inventory"),
    ptpp: currentUrl.value.startsWith("/ptpp"),
    documents:
        currentUrl.value.startsWith("/leave-requests") ||
        currentUrl.value.startsWith("/leave-reviews") ||
        currentUrl.value.startsWith("/leave-administration") ||
        currentUrl.value.startsWith("/surat-tugas"),
});

const user = computed(() => page.props.auth?.user ?? {});

const role = computed(() => {
    const value = user.value.role;

    if (typeof value === "string") {
        return value.toLowerCase();
    }

    return (value?.name ?? user.value.role_name ?? "karyawan").toLowerCase();
});

const menuGroups = computed(() => {
    const adminMenus = [
        {
            title: "Utama",
            items: [
                {
                    label: "Dashboard",
                    href: "/dashboard",
                    icon: IconLayoutDashboard,
                },
            ],
        },
        {
            title: "Manajemen",
            items: [
                {
                    label: "Manajemen Pengguna",
                    href: "/users",
                    icon: IconUsers,
                },
                {
                    label: "Dokumen Kepegawaian",
                    href: "/leave-administration",
                    icon: IconFileText,
                    key: "documents",
                    children: [
                        {
                            label: "Data Cuti",
                            href: "/leave-administration",
                            icon: IconFileText,
                        },
                        {
                            label: "Surat Tugas",
                            href: "/surat-tugas",
                            icon: IconFileText,
                        },
                    ],
                },
            ],
        },
        {
            title: "Operasional",
            items: [
                {
                    label: "Inventaris",
                    href: "/inventory",
                    icon: IconPackage,
                    key: "inventory",
                    children: [
                        {
                            label: "Data barang",
                            href: "/inventory",
                            icon: IconPackage,
                        },
                        {
                            label: "Cetak QR",
                            href: "/inventory/transactions/qr",
                            icon: IconPrinter,
                        },
                        {
                            label: "Transaksi",
                            href: "/inventory/transactions",
                            icon: IconHistory,
                        },
                        {
                            label: "Kategori",
                            href: "/inventory/categories",
                            icon: IconTags,
                        },
                    ],
                },
                {
                    label: "Keluhan Pelanggan",
                    href: "/complaints",
                    icon: IconMessageCircle,
                },
                {
                    label: "PTPP",
                    href: "/ptpp",
                    icon: IconClipboardCheck,
                },
            ],
        },
        {
            title: "Pelaporan",
            items: [
                {
                    label: "Laporan",
                    href: "/reports",
                    icon: IconReportAnalytics,
                },
            ],
        },
    ];

    const managerMenus = [
        adminMenus[0],
        {
            title: "Dokumen Kepegawaian",
            items: [
                {
                    label: "Dokumen Kepegawaian",
                    href: "/leave-reviews",
                    icon: IconFileText,
                    key: "documents",
                    children: [
                        {
                            label: "Tinjauan Cuti",
                            href: "/leave-reviews",
                            icon: IconClipboardCheck,
                        },
                        {
                            label: "Surat Tugas",
                            href: "/surat-tugas",
                            icon: IconFileText,
                        },
                    ],
                },
            ],
        },
        {
            title: "Operasional",
            items: [
                ...adminMenus[2].items.filter((item) => item.href !== "/ptpp"),
                {
                    label: "PTPP",
                    href: "/ptpp",
                    icon: IconClipboardCheck,
                },
            ],
        },
        adminMenus[3],
    ];

    const employeeMenus = [
        {
            title: "Utama",
            items: [
                {
                    label: "Dashboard",
                    href: "/dashboard",
                    icon: IconLayoutDashboard,
                },
            ],
        },
        {
            title: "Dokumen",
            items: [
                {
                    label: "Dokumen Kepegawaian",
                    href: "/leave-requests",
                    icon: IconFileText,
                    key: "documents",
                    children: [
                        {
                            label: "Pengajuan Cuti",
                            href: "/leave-requests",
                            icon: IconHistory,
                        },
                        {
                            label: "Surat Tugas",
                            href: "/surat-tugas",
                            icon: IconFileText,
                        },
                    ],
                },
            ],
        },
        {
            title: "Operasional",
            items: [
                {
                    label: "PTPP",
                    href: "/ptpp",
                    icon: IconClipboardCheck,
                    key: "ptpp",
                    children: [
                        {
                            label: "Diajukan",
                            href: "/ptpp?tab=diajukan",
                            icon: IconFileText,
                        },
                        {
                            label: "Masuk",
                            href: "/ptpp?tab=masuk",
                            icon: IconClipboardCheck,
                        },
                    ],
                },
            ],
        },
        {
            title: "Akun",
            items: [
                {
                    label: "Profil",
                    href: "/profile",
                    icon: IconUserCircle,
                },
            ],
        },
    ];

    if (role.value === "admin") {
        return adminMenus;
    }

    return role.value === "manager" ? managerMenus : employeeMenus;
});

function isActive(href) {
    const [path, query = ""] = href.split("?");

    if (currentUrl.value !== path && !currentUrl.value.startsWith(`${path}/`)) {
        return false;
    }

    return [...new URLSearchParams(query)].every(([key, value]) => {
        const currentValue = currentQuery.value.get(key);

        return currentValue === value || (key === "tab" && value === "diajukan" && currentValue === null);
    });
}

function toggleMenu(menuKey) {
    if (props.collapsed) {
        expandedMenus.value[menuKey] = true;
        emit("toggle");

        return;
    }

    expandedMenus.value[menuKey] = !expandedMenus.value[menuKey];
}

function logout() {
    router.post("/logout");
}
</script>

<template>
    <div v-if="mobileOpen" class="sidebar-overlay" @click="emit('close')" />

    <aside
        class="app-sidebar"
        :class="{
            'is-collapsed': collapsed,
            'is-mobile-open': mobileOpen,
        }"
    >
        <div class="sidebar-brand">
            <div class="brand-icon">
                <img :src="pertaminaLogo" alt="Pertamina" />
            </div>

            <div v-if="!collapsed" class="brand-text">
                <span class="brand-title">IT Karyawan</span>
                <span class="brand-subtitle">Sistem Informasi</span>
            </div>

            <button
                class="sidebar-toggle"
                type="button"
                :title="collapsed ? 'Perluas sidebar' : 'Lipat sidebar'"
                :aria-label="collapsed ? 'Perluas sidebar' : 'Lipat sidebar'"
                @click="mobileOpen ? emit('close') : emit('toggle')"
            >
                <IconX v-if="mobileOpen" :size="19" />
                <IconChevronRight v-else-if="collapsed" :size="19" />
                <IconChevronLeft v-else :size="19" />
            </button>
        </div>

        <nav class="sidebar-navigation">
            <div
                v-for="group in menuGroups"
                :key="group.title"
                class="sidebar-group"
            >
                <div v-if="!collapsed" class="sidebar-group-title">
                    {{ group.title }}
                </div>

                <template v-for="item in group.items" :key="item.href">
                    <button
                        v-if="item.children"
                        type="button"
                        class="sidebar-link"
                        :class="{ 'is-active': isActive(item.href) }"
                        :title="collapsed ? item.label : undefined"
                        :aria-expanded="expandedMenus[item.key]"
                        @click="toggleMenu(item.key)"
                    >
                        <component
                            :is="item.icon"
                            class="sidebar-link-icon"
                            :size="20"
                            stroke-width="1.8"
                        />
                        <span v-if="!collapsed" class="sidebar-link-label">
                            {{ item.label }}
                        </span>
                        <IconChevronDown
                            v-if="!collapsed"
                            class="submenu-chevron"
                            :class="{ 'is-expanded': expandedMenus[item.key] }"
                            :size="16"
                        />
                    </button>

                    <div
                        v-if="item.children && expandedMenus[item.key] && !collapsed"
                        class="sidebar-submenu"
                    >
                        <Link
                            v-for="child in item.children"
                            :key="child.href"
                            :href="child.href"
                            class="sidebar-submenu-link"
                            :class="{ 'is-active': isActive(child.href) }"
                            :aria-current="isActive(child.href) ? 'page' : undefined"
                            @click="emit('close')"
                        >
                            <component
                                :is="child.icon"
                                :size="17"
                                stroke-width="1.8"
                            />
                            <span>{{ child.label }}</span>
                        </Link>
                    </div>

                    <Link
                        v-else-if="!item.children"
                        :href="item.href"
                        class="sidebar-link"
                        :class="{ 'is-active': isActive(item.href) }"
                        :title="collapsed ? item.label : undefined"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                        @click="emit('close')"
                    >
                        <component
                            :is="item.icon"
                            class="sidebar-link-icon"
                            :size="20"
                            stroke-width="1.8"
                        />
                        <span v-if="!collapsed" class="sidebar-link-label">
                            {{ item.label }}
                        </span>
                    </Link>
                </template>
            </div>
        </nav>

        <div class="sidebar-bottom">
            <div v-if="!collapsed" class="sidebar-user">
                <div class="user-avatar">
                    <IconUserCircle :size="22" />
                </div>

                <div class="user-details">
                    <span class="user-name">{{ user.name ?? "Pengguna" }}</span>
                    <span class="user-role">{{ role }}</span>
                </div>
            </div>

            <button
                type="button"
                class="sidebar-link logout-link"
                title="Keluar"
                @click="logout"
            >
                <IconLogout
                    class="sidebar-link-icon"
                    :size="20"
                    stroke-width="1.8"
                />
                <span v-if="!collapsed" class="sidebar-link-label">
                    Keluar
                </span>
            </button>
        </div>
    </aside>
</template>

<style scoped>
.app-sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 50;
    display: flex;
    flex-direction: column;
    width: 240px;
    background: #fff;
    border-right: 1px solid #e6e7e9;
    transition:
        width 200ms ease,
        transform 200ms ease;
}

.app-sidebar.is-collapsed {
    width: 72px;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 64px;
    padding: 0 16px;
    border-bottom: 1px solid #e6e7e9;
}

.brand-icon {
    display: flex;
    flex: 0 0 58px;
    align-items: center;
    justify-content: center;
    width: 58px;
    height: 36px;
}

.brand-icon img { display: block; width: 100%; max-height: 36px; object-fit: contain; }
.is-collapsed .brand-icon { flex-basis: 50px; width: 50px; }

.brand-text,
.user-details {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.brand-title {
    color: #182433;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
}

.brand-subtitle {
    margin-top: 2px;
    color: #667382;
    font-size: 11px;
    white-space: nowrap;
}

.sidebar-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    margin-left: auto;
    color: #667382;
    background: transparent;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
}

.sidebar-toggle:hover {
    color: #066fd1;
    background: #f1f5f9;
}

.sidebar-toggle:focus-visible,
.sidebar-link:focus-visible {
    outline: 2px solid #066fd1;
    outline-offset: 2px;
}

.is-collapsed .sidebar-brand {
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    padding: 10px 4px;
}

.is-collapsed .sidebar-toggle {
    margin: 0;
}

.sidebar-navigation {
    flex: 1;
    overflow-x: hidden;
    overflow-y: auto;
    padding: 16px 10px;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.sidebar-navigation::-webkit-scrollbar {
    display: none;
}

.sidebar-group + .sidebar-group {
    margin-top: 20px;
}

.sidebar-group-title {
    padding: 0 10px;
    margin-bottom: 7px;
    color: #8995a2;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.07em;
    text-transform: uppercase;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    min-height: 40px;
    padding: 9px 10px;
    margin-bottom: 3px;
    color: #52606d;
    text-align: left;
    text-decoration: none;
    background: transparent;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
    transition:
        color 150ms ease,
        background 150ms ease;
}

.sidebar-link:hover {
    color: #066fd1;
    background: #f1f5f9;
}

.sidebar-link.is-active {
    color: #066fd1;
    font-weight: 500;
    background: #e8f2fc;
}

.sidebar-link-icon {
    flex: 0 0 20px;
}

.sidebar-link-label {
    overflow: hidden;
    font-size: 13px;
    line-height: 1.4;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.submenu-chevron {
    flex: 0 0 16px;
    margin-left: auto;
    transition: transform 150ms ease;
}

.submenu-chevron.is-expanded {
    transform: rotate(180deg);
}

.sidebar-submenu {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 2px 0 5px 25px;
}

.sidebar-submenu-link {
    display: flex;
    align-items: center;
    gap: 9px;
    min-height: 36px;
    padding: 7px 10px;
    color: #667382;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
}

.sidebar-submenu-link:hover,
.sidebar-submenu-link.is-active {
    color: #066fd1;
    background: #f1f5f9;
}

.sidebar-submenu-link:focus-visible {
    outline: 2px solid #066fd1;
    outline-offset: 2px;
}

.is-collapsed .sidebar-navigation {
    padding-right: 10px;
    padding-left: 10px;
}

.is-collapsed .sidebar-link {
    justify-content: center;
    padding: 10px 0;
}

.sidebar-bottom {
    padding: 12px 10px;
    border-top: 1px solid #e6e7e9;
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 6px 13px;
    margin-bottom: 8px;
    border-bottom: 1px solid #e6e7e9;
}

.user-avatar {
    display: flex;
    flex: 0 0 34px;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    color: #52606d;
    background: #f1f5f9;
    border-radius: 50%;
}

.user-name {
    overflow: hidden;
    color: #182433;
    font-size: 12px;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-role {
    margin-top: 3px;
    color: #667382;
    font-size: 11px;
    text-transform: capitalize;
}

.logout-link:hover {
    color: #d63939;
    background: #fff0f0;
}

.sidebar-overlay {
    display: none;
}

@media (max-width: 767px) {
    .app-sidebar,
    .app-sidebar.is-collapsed {
        width: 260px;
        transform: translateX(-100%);
    }

    .app-sidebar.is-mobile-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        position: fixed;
        inset: 0;
        z-index: 40;
        display: block;
        background: rgb(24 36 51 / 35%);
    }
}
</style>
