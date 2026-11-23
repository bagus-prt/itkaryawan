---
name: Tabler Admin Dashboard (Folded Sidebar)
version: 1.0
reference: https://preview.tabler.io/layout-folded.html
base: Tabler v1.6.x (Bootstrap 5.3 based)
target: Vue 3
# Legenda keandalan nilai:
#   [V] = terverifikasi dari docs.tabler.io / tabler.io (warna, nama variabel, font)
#   [D] = default Tabler/Bootstrap 1.x yang umum; cek ulang di tabler.css versi Anda
colors:
  # --- Brand / semantic [V] ---
  primary: "#066fd1"
  secondary: "#6b7280"
  tertiary: "#9ca3af"
  success: "#2fb344"      # = green
  warning: "#f76707"      # = orange (status Pending)
  danger: "#d63939"       # = red
  info: "#4299e1"         # = azure
  link: "#066fd1"
  link-hover: "#045db0"
  # --- Palet dasar [V] ---
  blue: "#066fd1"
  azure: "#4299e1"
  indigo: "#4263eb"
  purple: "#ae3ec9"
  pink: "#d6336c"
  red: "#d63939"
  orange: "#f76707"
  yellow: "#f59f00"
  lime: "#74b816"
  green: "#2fb344"
  teal: "#0ca678"
  cyan: "#17a2b8"
  # --- Varian light (latar badge/alert) [V kecuali teal & cyan = D] ---
  blue-lt: "#e9f0f9"
  azure-lt: "#ecf5fc"
  indigo-lt: "#eceffd"
  purple-lt: "#f7ecfa"
  pink-lt: "#fbebf0"
  red-lt: "#fbebeb"
  orange-lt: "#fef0e6"
  yellow-lt: "#fef5e6"
  lime-lt: "#f1f8e8"
  green-lt: "#eaf7ec"
  teal-lt: "#e7f6f2"
  cyan-lt: "#e8f6f8"
  # --- Skala abu-abu [V: 50-800] ---
  gray-50: "#f9fafb"
  gray-100: "#f3f4f6"
  gray-200: "#e5e7eb"
  gray-300: "#d1d5db"
  gray-400: "#9ca3af"
  gray-500: "#6b7280"
  gray-600: "#4b5563"
  gray-700: "#374151"
  gray-800: "#1f2937"
  gray-900: "#111827"     # [D]
  gray-950: "#030712"     # [D]
  # --- Permukaan mode terang [D] ---
  body-bg: "#f6f8fb"
  surface: "#ffffff"
  border: "#e6e7e9"
  text: "#182433"
  text-muted: "#667382"
  # --- Permukaan mode gelap [D] ---
  dark-body-bg: "#151f2c"
  dark-surface: "#182433"
  dark-border: "#243245"
  dark-text: "#dce1e7"
typography:
  # Font [V]: Inter, bobot 300/400/500/600. Variabel: --tblr-font-sans-serif
  font-family-sans: "Inter, -apple-system, BlinkMacSystemFont, 'San Francisco', 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
  font-family-mono: "'Source Code Pro', Menlo, Monaco, Consolas, monospace"
  # Skala [D]; variabel: --tblr-body-font-size, --tblr-font-size-h1..h6 [V nama]
  body:      { fontSize: 0.875rem,  fontWeight: 400, lineHeight: 1.4285714 }
  body-sm:   { fontSize: 0.75rem,   fontWeight: 400, lineHeight: 1.333 }
  h1:        { fontSize: 1.5rem,    fontWeight: 600, lineHeight: 2rem }
  h2:        { fontSize: 1.25rem,   fontWeight: 600, lineHeight: 1.75rem }
  h3:        { fontSize: 1rem,      fontWeight: 600, lineHeight: 1.5rem }
  h4:        { fontSize: 0.875rem,  fontWeight: 600, lineHeight: 1.25rem }
  h5:        { fontSize: 0.75rem,   fontWeight: 600, lineHeight: 1rem }
  subheader: { fontSize: 0.625rem,  fontWeight: 600, letterSpacing: 0.04em, textTransform: uppercase }
  stat-value:{ fontSize: 1.5rem,    fontWeight: 600, lineHeight: 1 }
  button:    { fontSize: 0.875rem,  fontWeight: 500, lineHeight: 1.25rem }
  table-head:{ fontSize: 0.625rem,  fontWeight: 600, letterSpacing: 0.04em, textTransform: uppercase }
rounded:            # [D] bisa diatur 0 / 0.5 / 1 / 1.5 / 2 lewat panel tema
  none: 0
  sm: 2px
  md: 4px           # default kartu, input, tombol
  lg: 8px
  xl: 12px
  pill: 100rem      # badge, avatar bulat, status dot
spacing:            # [D] skala Bootstrap/Tabler (rem, basis 16px)
  0: 0
  1: 0.25rem        # 4px
  2: 0.5rem         # 8px
  3: 1rem           # 16px  <- gutter kartu
  4: 1.5rem         # 24px  <- padding kartu / jarak antar section
  5: 2rem           # 32px
  6: 3rem           # 48px
layout:
  sidebar-width-expanded: 15rem     # [D]
  sidebar-width-folded: 4.5rem      # [D] perkiraan, sesuaikan
  topbar-height: 3.5rem             # [D]
  container-max-width: 1320px       # [D] container "Default"
  grid-columns: 12
  breakpoints: { sm: 576px, md: 768px, lg: 992px, xl: 1200px, xxl: 1400px }
elevation:          # [D]
  card: "0 0 4px rgba(24, 36, 51, 0.04)"
  dropdown: "0 16px 24px rgba(24, 36, 51, 0.06), 0 2px 6px rgba(24, 36, 51, 0.04)"
  modal: "0 16px 32px rgba(24, 36, 51, 0.12)"
  focus-ring: "0 0 0 0.25rem rgba(6, 111, 209, 0.25)"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "0.4375rem 1rem"
    typography: "{typography.button}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    border: "1px solid {colors.border}"
    rounded: "{rounded.md}"
  card:
    backgroundColor: "{colors.surface}"
    border: "1px solid {colors.border}"
    rounded: "{rounded.md}"
    padding: "{spacing.4}"
    shadow: "{elevation.card}"
  stat-card:
    extends: card
    value: "{typography.stat-value}"
    label: "{typography.subheader}"
  sidebar:
    backgroundColor: "{colors.surface}"
    width: "{layout.sidebar-width-expanded}"
    widthFolded: "{layout.sidebar-width-folded}"
    itemActiveColor: "{colors.primary}"
    itemActiveBackground: "{colors.blue-lt}"
  topbar:
    backgroundColor: "{colors.surface}"
    height: "{layout.topbar-height}"
    border: "0 0 1px 0 solid {colors.border}"
  input:
    backgroundColor: "{colors.surface}"
    border: "1px solid {colors.gray-300}"
    rounded: "{rounded.md}"
    focusBorder: "{colors.primary}"
  badge:
    rounded: "{rounded.pill}"
    typography: "{typography.body-sm}"
    variants: { paid: "{colors.green-lt}/{colors.green}", pending: "{colors.orange-lt}/{colors.orange}", overdue: "{colors.red-lt}/{colors.red}" }
  table:
    headTypography: "{typography.table-head}"
    rowBorder: "1px solid {colors.border}"
    cellPadding: "0.75rem"
  modal:
    rounded: "{rounded.lg}"
    shadow: "{elevation.modal}"
  chart:
    library: ApexCharts
    palette: ["{colors.primary}", "{colors.azure}", "{colors.green}", "{colors.yellow}", "{colors.orange}", "{colors.purple}"]
---

# Design System — Tabler Admin Dashboard (Folded Sidebar)

## 1. Overview

Dashboard admin yang bersih, padat informasi, dan netral, dengan **sidebar kiri
yang dapat dilipat**, topbar ringkas, serta konten berbasis kartu pada grid 12
kolom. Halaman acuan (`layout-folded`) menampilkan pola khas sistem admin:
kartu statistik di atas, grafik di tengah, lalu tabel dan daftar aktivitas.

**Prinsip desain**

1. **Datanya yang utama.** Warna kuat hanya dipakai untuk status, aksi utama, dan seri grafik.
2. **Netral dulu.** Latar abu-abu sangat terang, kartu putih, border tipis. Biru `#066fd1` jadi satu-satunya aksen dominan.
3. **Konsisten.** Satu skala jarak, satu radius, satu keluarga font.
4. **Bisa disesuaikan.** Mode terang/gelap, aksen, radius, font, dan perilaku sidebar bisa diganti pengguna (panel "Make Tabler your own").
5. **Aksesibel.** Fokus terlihat, kontras cukup, status tidak hanya dibedakan lewat warna.

**Referensi teknis:** `@tabler/core` (CSS variable berawalan `--tblr-`),
`@tabler/icons-vue`, `vue3-apexcharts`.

---

## 2. Colors

### 2.1 Warna semantik

| Peran | Token | Hex | Dipakai untuk |
|-------|-------|-----|---------------|
| Primary | `--tblr-primary` | `#066fd1` | Tombol utama, link, item menu aktif, seri grafik utama |
| Secondary | `--tblr-secondary` | `#6b7280` | Teks sekunder, ikon netral |
| Success | `--tblr-green` | `#2fb344` | Paid, sukses, tren naik |
| Warning | `--tblr-orange` | `#f76707` | Pending, peringatan |
| Danger | `--tblr-red` | `#d63939` | Error, hapus, tren turun |
| Info | `--tblr-azure` | `#4299e1` | Informasi netral |

### 2.2 Palet dasar (12 warna) dan varian light

| Nama | Base | Light (`-lt`) |
|------|------|---------------|
| Blue | `#066fd1` | `#e9f0f9` |
| Azure | `#4299e1` | `#ecf5fc` |
| Indigo | `#4263eb` | `#eceffd` |
| Purple | `#ae3ec9` | `#f7ecfa` |
| Pink | `#d6336c` | `#fbebf0` |
| Red | `#d63939` | `#fbebeb` |
| Orange | `#f76707` | `#fef0e6` |
| Yellow | `#f59f00` | `#fef5e6` |
| Lime | `#74b816` | `#f1f8e8` |
| Green | `#2fb344` | `#eaf7ec` |
| Teal | `#0ca678` | `#e7f6f2` *(perkiraan)* |
| Cyan | `#17a2b8` | `#e8f6f8` *(perkiraan)* |

Varian `-lt` dipakai sebagai latar badge/alert dengan teks memakai warna base.

### 2.3 Skala abu-abu

`gray-50 #f9fafb` · `100 #f3f4f6` · `200 #e5e7eb` · `300 #d1d5db` · `400 #9ca3af`
· `500 #6b7280` · `600 #4b5563` · `700 #374151` · `800 #1f2937` · `900 #111827` · `950 #030712`

Tabler menyediakan beberapa basis abu-abu (Slate, Gray, Zinc, Neutral, Stone)
lewat atribut `data-bs-theme-base`. Pilih satu dan konsisten.

### 2.4 Permukaan

| Peran | Terang | Gelap |
|-------|--------|-------|
| Latar halaman | `#f6f8fb` | `#151f2c` |
| Permukaan kartu | `#ffffff` | `#182433` |
| Border | `#e6e7e9` | `#243245` |
| Teks utama | `#182433` | `#dce1e7` |
| Teks redup | `#667382` | `#9aa5b3` |

### 2.5 Mode warna

Auto (ikut sistem), Light, Dark, Dark nav (navigasi gelap + konten terang),
Colored. Terapkan lewat `data-bs-theme="light|dark"` pada `<html>`.

### 2.6 Override cepat

```css
:root {
  --tblr-primary: #066fd1;
  --tblr-primary-rgb: 6, 111, 209;   /* wajib jika dipakai di rgba() */
  --tblr-primary-fg: #fff;           /* teks di atas warna primary */
  --tblr-font-sans-serif: "Inter";
  --tblr-body-font-size: 0.875rem;
}
```

---

## 3. Typography

**Font:** Inter (bobot 300, 400, 500, 600). Opsi alternatif di panel tema:
Sans-serif, Serif, Monospace.

```html
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
```

| Gaya | Ukuran | Bobot | Line-height | Dipakai untuk |
|------|--------|-------|-------------|---------------|
| H1 / judul halaman | 1.5rem (24px) | 600 | 2rem | Judul di page header |
| H2 | 1.25rem (20px) | 600 | 1.75rem | Judul section besar |
| H3 | 1rem (16px) | 600 | 1.5rem | **Judul kartu** |
| H4 | 0.875rem (14px) | 600 | 1.25rem | Sub-judul |
| H5 | 0.75rem (12px) | 600 | 1rem | Label kecil |
| Body | 0.875rem (14px) | 400 | 1.43 | Teks umum, isi tabel |
| Body kecil | 0.75rem (12px) | 400 | 1.33 | Keterangan, timestamp |
| Subheader | 0.625rem (10px) | 600 | – | Pretitle, label kelompok menu, header tabel; huruf kapital, letter-spacing 0.04em |
| Angka statistik | 1.5rem (24px) | 600 | 1 | Nilai di stat card |
| Tombol | 0.875rem (14px) | 500 | 1.25rem | Label tombol |

**Aturan**
- Judul kartu H3, bukan H1/H2, agar hierarki halaman tetap jelas.
- Gunakan `font-variant-numeric: tabular-nums` pada kolom angka agar rata.
- Angka di tabel rata kanan, teks rata kiri.
- Jangan memakai lebih dari 3 bobot dalam satu halaman.
- Variabel penyesuaian: `--tblr-body-font-size`, `--tblr-font-size-h1` sampai `-h6`,
  `--tblr-line-height-h1` sampai `-h6`.

---

## 4. Layout

### 4.1 Kerangka

```
┌────────┬───────────────────────────────────────────────┐
│Sidebar │ Topbar  [search] ........ [tema][🔔][apps][🌐][user] │
│ 15rem  ├───────────────────────────────────────────────┤
│  atau  │ Page header: pretitle / judul / tombol aksi    │
│ 4.5rem │ Page body : row > col-*  (grid 12 kolom)       │
│(folded)│ Footer                                         │
└────────┴───────────────────────────────────────────────┘
```

### 4.2 Grid dan jarak

- Grid 12 kolom, gutter 1rem (`spacing.3`); jarak vertikal antar baris kartu 1rem.
- Padding kartu 1.5rem (`spacing.4`); padding header kartu 1rem 1.5rem.
- Jarak page header ke body 1.5rem; body ke footer 1.5rem.
- Pola kolom umum: stat card `col-sm-6 col-lg-3`, grafik `col-lg-8` + `col-lg-4`, tabel `col-12`.

### 4.3 Breakpoint

| Nama | Lebar | Perilaku |
|------|-------|----------|
| sm | ≥576px | Stat card 2 kolom |
| md | ≥768px | Sidebar jadi offcanvas di bawah titik ini |
| lg | ≥992px | Sidebar permanen, grid penuh |
| xl | ≥1200px | Container diperlebar |
| xxl | ≥1400px | Container maksimum 1320px |

### 4.3b Varian container

Default (terpusat), Fluid (penuh), Boxed (kotak berbatas). Navbar: Attached atau Floating, Sticky opsional.

### 4.4 Sidebar yang bisa dibuka-tutup

| Mode | Lebar | Perilaku |
|------|-------|----------|
| `expanded` | 15rem | Ikon + label + chevron submenu |
| `folded` | 4.5rem | Ikon saja; submenu muncul sebagai flyout, label sebagai tooltip |
| `hover` | 4.5rem → 15rem | Folded, melebar sementara saat kursor di atasnya, tanpa menggeser konten |
| `offcanvas` (mobile) | 15rem | Geser dari kiri, ada backdrop, tutup dengan tap di luar / Esc |

**Aturan perilaku**
- Tombol toggle (`IconLayoutSidebarLeftCollapse` / `IconMenu2`) ada di dasar sidebar atau topbar.
- Transisi lebar 200ms `ease`; konten utama menyesuaikan margin kiri.
- Pilihan pengguna disimpan di `localStorage` (`ui.sidebar`).
- Saat folded, grup submenu tertutup; item aktif tetap ditandai.
- Atur `aria-expanded` pada tombol toggle dan `aria-current="page"` pada item aktif.

**Contoh composable (Vue 3)**

```ts
// composables/useSidebar.ts
import { ref, watch, computed } from 'vue'

export type SidebarMode = 'expanded' | 'folded' | 'hover'
const KEY = 'ui.sidebar'
const mode = ref<SidebarMode>((localStorage.getItem(KEY) as SidebarMode) || 'expanded')
const mobileOpen = ref(false)

watch(mode, v => localStorage.setItem(KEY, v))

export function useSidebar() {
  const isFolded = computed(() => mode.value !== 'expanded')
  const toggle = () => (mode.value = mode.value === 'expanded' ? 'folded' : 'expanded')
  return { mode, isFolded, mobileOpen, toggle }
}
```

```vue
<!-- AppSidebar.vue (ringkas) -->
<aside class="sidebar" :class="{ 'is-folded': isFolded, 'is-open': mobileOpen }">
  <nav aria-label="Menu utama"> ... </nav>
  <button @click="toggle" :aria-expanded="!isFolded" aria-label="Lipat sidebar">
    <IconLayoutSidebarLeftCollapse />
  </button>
</aside>
```

```css
.sidebar { width: 15rem; transition: width .2s ease; }
.sidebar.is-folded { width: 4.5rem; }
.sidebar.is-folded .nav-label { display: none; }
.page-wrapper { margin-left: 15rem; transition: margin-left .2s ease; }
.sidebar.is-folded ~ .page-wrapper { margin-left: 4.5rem; }
@media (max-width: 991.98px) {
  .sidebar { position: fixed; transform: translateX(-100%); }
  .sidebar.is-open { transform: none; }
  .page-wrapper { margin-left: 0 !important; }
}
```

### 4.5 Struktur menu

Menu dibangkitkan dari satu array konfigurasi (label, ikon, rute, anak, peran).

| Seksi | Item contoh | Ikon yang disarankan |
|-------|-------------|----------------------|
| Overview | Dashboard | `IconLayoutDashboard` |
| Data | Pelanggan / Pengguna | `IconUsers` |
| | Produk / Inventaris | `IconPackage` |
| | Pesanan | `IconShoppingCart` |
| Keuangan | Invoice | `IconFileInvoice` |
| | Pembayaran | `IconWallet` |
| Analitik | Laporan | `IconReportAnalytics` |
| | Grafik | `IconChartBar` |
| Jadwal | Kalender | `IconCalendar` |
| Sistem | Pengaturan | `IconSettings` |
| | Log aktivitas | `IconHistory` |

---

## 5. Elevation & Depth

Tabler memakai **kedalaman yang sangat tipis**. Pemisahan terutama lewat
border, bukan bayangan tebal.

| Level | Elemen | Gaya |
|-------|--------|------|
| 0 | Latar halaman | `#f6f8fb`, tanpa bayangan |
| 1 | Kartu, sidebar, topbar | Putih + border 1px `#e6e7e9` + `0 0 4px rgba(24,36,51,.04)` |
| 2 | Dropdown, popover, tooltip | `0 16px 24px rgba(24,36,51,.06), 0 2px 6px rgba(24,36,51,.04)` |
| 3 | Modal, offcanvas | `0 16px 32px rgba(24,36,51,.12)` + backdrop `rgba(24,36,51,.5)` |
| Fokus | Input, tombol (keyboard) | Ring `0 0 0 .25rem rgba(6,111,209,.25)` |

**Mode gelap:** bayangan hampir tak terlihat; bedakan lapisan lewat selisih
warna permukaan (`#151f2c` → `#182433`) dan border.

**Navbar floating / offcanvas floating:** elevasi level 2, sudut membulat penuh, jarak dari tepi layar.

---

## 6. Shapes

| Token | Nilai | Dipakai untuk |
|-------|-------|---------------|
| `rounded.none` | 0 | Tabel, divider |
| `rounded.sm` | 2px | Checkbox, tag kecil |
| `rounded.md` | **4px** | Kartu, tombol, input, dropdown (default) |
| `rounded.lg` | 8px | Modal, offcanvas floating |
| `rounded.xl` | 12px | Ilustrasi, kartu gambar |
| `rounded.pill` | 100rem | Badge, avatar bulat, status dot, toggle |

- Panel tema menyediakan faktor radius 0 / 0.5 / 1 / 1.5 / 2; kalikan semua token di atas dengan faktor itu.
- Border default 1px solid; jangan campur border 1px dan 2px dalam satu kartu.
- Ikon: stroke 2px (bisa 1.5px untuk tampilan lebih halus), ujung membulat, ukuran 20px (menu) atau 24px (stat card).
- Avatar: 32px (tabel), 40px (daftar), 48px+ (profil); bentuk bulat atau radius `md`.

---

## 7. Components

### 7.1 Stat card
`card` + subheader (label) + `stat-value` + indikator tren (`IconTrendingUp` hijau / `IconTrendingDown` merah) + sparkline atau progress opsional.
Selalu sertakan teks/ikon arah, bukan hanya warna.

### 7.2 Chart card
Header: judul (H3) + dropdown rentang (Last 7 days / 30 days / 3 months). Isi: grafik tinggi 10–15rem. Legend di bawah memakai `legend-dot`.

### 7.3 Data table
Header subheader huruf kapital, baris dengan border bawah, hover `gray-50`, kolom aksi `IconDotsVertical` → dropdown. Di atas: *show entries* + pencarian; di bawah: info baris + pagination.

### 7.4 Badge status

| Status | Latar | Teks |
|--------|-------|------|
| Paid / Selesai | `green-lt` | `green` |
| Pending / Menunggu | `orange-lt` | `orange` |
| Overdue / Gagal | `red-lt` | `red` |
| Draft | `gray-100` | `gray-600` |
| Info | `azure-lt` | `azure` |

### 7.5 Tombol

| Varian | Gaya |
|--------|------|
| Primary | Latar `#066fd1`, teks putih |
| Secondary / default | Latar putih, border `#e6e7e9`, teks `#182433` |
| Ghost | Tanpa latar, teks primary |
| Danger | Latar `#d63939` |
| Ikon saja | 36×36px, `aria-label` wajib |

### 7.6 Form
Label di atas input, tinggi input ±36px, border `gray-300`, fokus: border primary + ring. Validasi: pesan di bawah input + ikon, warna `red`/`green`. Radio-card untuk pilihan bertipe (Simple/Advanced).

### 7.7 Topbar
Urutan: pencarian · toggle tema (`IconSun`/`IconMoon`) · notifikasi (`IconBell`, dengan titik merah) · My Apps · bahasa (termasuk Bahasa Indonesia) · menu pengguna (Profile, Settings, Help, Sign out `IconLogout`).

### 7.8 Activity feed
Avatar/inisial + kalimat dengan nama dan objek **tebal** + waktu relatif ("2 hari lalu") berwarna `text-muted`.

### 7.9 Modal & Offcanvas
Modal untuk form pendek (misal "New report"); offcanvas untuk panel pengaturan tema. Tombol Cancel di kiri, aksi utama di kanan.

### 7.10 Data grafik (chart)

**Library:** `vue3-apexcharts` (dipakai Tabler sendiri). Alternatif: Chart.js/`vue-chartjs`, ECharts.

**Pemilihan jenis grafik**

| Kebutuhan data | Jenis | Contoh di halaman |
|----------------|-------|-------------------|
| Tren waktu (harian/bulanan) | Line / Area | Sales, Traffic summary |
| Perbandingan antar kategori | Bar (vertikal/horizontal) | Revenue per bulan, sumber trafik |
| Tren kecil di dalam kartu | Sparkline | Active subscriptions |
| Komposisi / porsi (≤5 bagian) | Donut | Storage, status pesanan |
| Capaian vs target | Radial bar / progress | Conversion rate, 75% Complete |
| Sebaran geografis | Peta vektor | Locations |
| Korelasi dua variabel | Scatter | Analitik lanjutan |

**Palet seri (urutan pakai)**

| Urutan | Warna | Hex |
|--------|-------|-----|
| 1 | Primary | `#066fd1` |
| 2 | Azure | `#4299e1` |
| 3 | Green | `#2fb344` |
| 4 | Yellow | `#f59f00` |
| 5 | Orange | `#f76707` |
| 6 | Purple | `#ae3ec9` |

**Bentuk data yang dikirim ke grafik**

```ts
// Tren waktu: satu atau beberapa seri
const series = [
  { name: 'Penjualan',  data: [120, 180, 150, 220, 260, 240, 310] },
  { name: 'Pengunjung', data: [90, 140, 130, 200, 210, 230, 280] },
]
const categories = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']

// Komposisi: donut
const donut = { series: [3520, 1520, 720, 2240], labels: ['Regular', 'Sistem', 'Shared', 'Kosong'] }
```

**Opsi dasar yang disarankan (gaya Tabler)**

```ts
const options = {
  chart: { type: 'area', height: 240, fontFamily: 'Inter, sans-serif',
           toolbar: { show: false }, animations: { enabled: false } },
  stroke: { width: 2, curve: 'smooth' },
  fill: { opacity: 0.16 },
  dataLabels: { enabled: false },
  grid: { strokeDashArray: 4, borderColor: '#e6e7e9' },
  xaxis: { categories, axisBorder: { show: false }, tooltip: { enabled: false } },
  colors: ['#066fd1', '#4299e1', '#2fb344', '#f59f00', '#f76707', '#ae3ec9'],
  legend: { show: true, position: 'bottom', fontSize: '12px' },
  tooltip: { theme: 'light' },
}
```

**Aturan grafik**
- Maksimal 6 seri per grafik; lebih dari itu pecah atau agregasi.
- Sumbu Y mulai dari 0 untuk bar; boleh tidak untuk line.
- Format angka: ribuan dipisah titik untuk lokal Indonesia (`id-ID`), mata uang `Rp`.
- Mode gelap: ganti `grid.borderColor` dan warna label sumbu mengikuti token gelap.
- Beri `role="img"` dan `aria-label` pada kontainer grafik; sediakan tabel data alternatif bila penting.
- Jangan membedakan "naik" dan "turun" hanya dengan merah dan hijau (saran resmi Tabler); tambahkan panah atau tanda +/−.

### 7.11 Ikon

**Rekomendasi utama:** **Tabler Icons** (MIT, 5000+ ikon SVG), gaya outline stroke 2px, sudah cocok dengan seluruh komponen.

| Paket | Instalasi | Catatan |
|-------|-----------|---------|
| Vue 3 | `npm i @tabler/icons-vue` | Impor per ikon, tree-shakable |
| Webfont | `npm i @tabler/icons-webfont` | Praktis (`<i class="ti ti-home">`), file lebih besar |
| SVG sprite | `@tabler/icons` | Untuk non-Vue |

```vue
<script setup>
import { IconUsers, IconTrendingUp } from '@tabler/icons-vue'
</script>
<template>
  <IconUsers :size="20" :stroke="2" />
  <IconTrendingUp :size="16" class="text-success" />
</template>
```

**Alternatif:** Lucide (`lucide-vue-next`) jika ingin gaya serupa; Heroicons bila memakai Tailwind.

**Aturan:** satu keluarga ikon saja, ukuran 20px di menu, 16px inline dengan teks, 24px di stat card; ikon tanpa teks wajib punya `aria-label`/tooltip.

---

## 8. Do's and Don'ts

### Do
- **Pakai token**, bukan hex mentah, agar tema gelap dan ganti aksen berfungsi.
- Pertahankan **satu warna aksen** dominan; gunakan palet lain hanya untuk status dan seri grafik.
- Gunakan **skala jarak** (4/8/16/24/32/48px); selaraskan antar kartu.
- Tampilkan **judul + angka + tren** pada setiap stat card.
- Sediakan **tombol lipat sidebar** dan ingat pilihan pengguna.
- Beri **tooltip** pada menu yang terlipat dan pada ikon tanpa label.
- Rata kanan untuk angka, gunakan `tabular-nums`, dan format lokal (`id-ID`, `Rp`).
- Uji kontras di mode terang dan gelap; pastikan fokus keyboard terlihat.
- Tambahkan **skeleton/placeholder** saat memuat data dan **empty state** saat kosong.
- Batasi grafik ke tipe yang sesuai data (lihat tabel 7.10).

### Don't
- **Jangan** mengganti radius, font, atau border per komponen secara acak.
- **Jangan** memakai bayangan tebal atau gradien berat; gaya ini datar dan tipis.
- **Jangan** membedakan status hanya dengan merah vs hijau.
- **Jangan** mencampur lebih dari satu keluarga ikon.
- **Jangan** menumpuk lebih dari 3 level menu atau lebih dari ±8 item per grup tanpa pengelompokan.
- **Jangan** menaruh lebih dari 6 seri dalam satu grafik, atau donut dengan banyak irisan kecil.
- **Jangan** memakai teks putih di atas warna terang (yellow, lime) tanpa cek kontras.
- **Jangan** menyembunyikan aksi penting di balik ikon tanpa label.
- **Jangan** hardcode lebar sidebar di banyak tempat; gunakan satu variabel.
- **Jangan** lupa menimpa `--tblr-primary-rgb` saat mengganti warna primary.

---

## 9. Sumber verifikasi

| Topik | Sumber |
|-------|--------|
| Palet warna dan kode hex | https://docs.tabler.io/ui/base/colors |
| Variabel kustomisasi (font, warna, abu-abu) | https://docs.tabler.io/ui/getting-started/customize |
| Layout dan opsi sidebar | https://preview.tabler.io/layout-folded.html |
| Ikon | https://tabler.io/icons |
| Kode sumber | https://github.com/tabler/tabler |

Nilai bertanda **[D]** (permukaan, ukuran font heading, radius, bayangan, lebar
sidebar) adalah default umum Tabler 1.x. Cocokkan dengan `tabler.css` pada versi
yang Anda pasang sebelum dijadikan standar tim.
