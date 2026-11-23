<script setup>
import { ref, computed } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import { IconMenu2, IconBell } from "@tabler/icons-vue";
import AppSidebar from "../Components/AppSidebar.vue";

const page = usePage();

const collapsed = ref(false);
const mobileOpen = ref(false);

const userName = computed(() => page.props.auth?.user?.name ?? "Pengguna");

function toggleSidebar() {
    collapsed.value = !collapsed.value;
}

function openMobileSidebar() {
    collapsed.value = false;
    mobileOpen.value = true;
}

function closeMobileSidebar() {
    mobileOpen.value = false;
}
</script>

<template>
    <Head />

    <div class="app-shell">
        <AppSidebar
            :collapsed="collapsed"
            :mobile-open="mobileOpen"
            @toggle="toggleSidebar"
            @close="closeMobileSidebar"
        />

        <div class="app-main" :class="{ 'sidebar-collapsed': collapsed }">
            <header class="app-topbar">
                <div class="topbar-left">
                    <button
                        class="topbar-icon-button mobile-menu-button"
                        type="button"
                        aria-label="Buka navigasi"
                        @click="openMobileSidebar"
                    >
                        <IconMenu2 :size="21" />
                    </button>

                    <div class="topbar-context">
                        <span class="topbar-label">Workspace</span>
                        <span class="topbar-separator">/</span>
                        <span class="topbar-current">IT Karyawan</span>
                    </div>
                </div>

                <div class="topbar-right">
                    <button
                        class="topbar-icon-button notification-button"
                        type="button"
                        aria-label="Notifikasi"
                        title="Notifikasi"
                    >
                        <IconBell :size="20" />
                    </button>

                    <div class="topbar-user">
                        <div class="topbar-avatar">
                            {{ userName.charAt(0).toUpperCase() }}
                        </div>

                        <span class="topbar-user-name">
                            {{ userName }}
                        </span>
                    </div>
                </div>
            </header>

            <main class="app-content">
                <slot />
            </main>
        </div>
    </div>
</template>

<style>
:root {
    --app-sidebar-expanded: 240px;
    --app-sidebar-collapsed: 72px;
    --app-topbar-height: 56px;
    --app-background: #f6f8fb;
    --app-surface: #ffffff;
    --app-border: #e6e7e9;
    --app-text: #182433;
    --app-muted: #667382;
    --app-primary: #066fd1;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    color: var(--app-text);
    background: var(--app-background);
    font-family: Inter, ui-sans-serif, system-ui, sans-serif;
    font-size: 14px;
    -webkit-font-smoothing: antialiased;
}

button {
    font: inherit;
}

.app-main {
    min-height: 100vh;
    margin-left: var(--app-sidebar-expanded);
    transition: margin-left 200ms ease;
}

.app-main.sidebar-collapsed {
    margin-left: var(--app-sidebar-collapsed);
}

.app-topbar {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: var(--app-topbar-height);
    padding: 0 24px;
    background: var(--app-surface);
    border-bottom: 1px solid var(--app-border);
}

.topbar-left,
.topbar-right,
.topbar-context,
.topbar-user {
    display: flex;
    align-items: center;
}

.topbar-context {
    gap: 10px;
    color: var(--app-muted);
    font-size: 13px;
}

.topbar-current {
    color: var(--app-text);
    font-weight: 500;
}

.topbar-separator {
    color: #c2c9d0;
}

.topbar-right {
    gap: 18px;
}

.topbar-icon-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    color: #52606d;
    background: transparent;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
    transition:
        color 150ms ease,
        background 150ms ease;
}

.topbar-icon-button:hover {
    color: var(--app-primary);
    background: #f1f5f9;
}

.topbar-icon-button:focus-visible {
    outline: 2px solid var(--app-primary);
    outline-offset: 2px;
}

.topbar-user {
    gap: 9px;
    padding-left: 16px;
    border-left: 1px solid var(--app-border);
}

.topbar-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    color: #066fd1;
    background: #e8f2fc;
    border-radius: 50%;
    font-size: 13px;
    font-weight: 600;
}

.topbar-user-name {
    max-width: 180px;
    overflow: hidden;
    color: var(--app-text);
    font-size: 12px;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.app-content {
    width: 100%;
    max-width: 1440px;
    padding: 28px;
    margin: 0 auto;
}

.mobile-menu-button {
    display: none;
}

@media (max-width: 767px) {
    .app-main,
    .app-main.sidebar-collapsed {
        margin-left: 0;
    }

    .app-topbar {
        padding: 0 14px;
    }

    .mobile-menu-button {
        display: flex;
        margin-right: 8px;
    }

    .topbar-label,
    .topbar-separator,
    .notification-button,
    .topbar-user-name {
        display: none;
    }

    .topbar-right {
        gap: 0;
    }

    .topbar-user {
        padding-left: 0;
        border-left: 0;
    }

    .app-content {
        padding: 20px 14px;
    }
}
</style>
