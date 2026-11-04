# DESIGN.md — Sistem Desain SIM-SCM UP Kendari

Panduan gaya komponen aplikasi **SIM-SCM UP Kendari** (Corporate Premium PLN Nusantara Power), disusun agar bisa dipakai ulang di aplikasi lain.

Isi panduan ini:
- token warna dan tipografi (CSS siap salin);
- aturan desain;
- resep class Tailwind untuk setiap komponen.

**Stack acuan:** Tailwind CSS v4, React 19, komponen gaya shadcn/ui (Radix UI + `class-variance-authority`), ikon Lucide, dan font Inter.

> Semua class di dokumen ini adalah class Tailwind v4. Token (`bg-primary`, `text-muted-foreground`, `bg-status-berjalan-surface`, dsb.) berasal dari blok `@theme` di bagian 3. Salin blok itu lebih dulu.

---

## 1. Prinsip Desain

**Karakter yang dituju:** aplikasi kerja kelas ERP/BUMN yang terpercaya, rapi, dan formal. Bukan landing page, bukan template AI.

1. **Serius, bukan playful.** Tanpa ilustrasi kartun, tanpa emoji sebagai ikon, tanpa animasi berlebihan.
2. **Satu aksen biru PLN** untuk tombol utama, tautan, tab aktif, dan highlight data. Selebihnya putih dan biru sangat muda.
3. **Kuning pisang (logo PLN) hanya untuk status "berjalan".** Jangan dipakai sebagai warna dekorasi.
4. **Hierarki lewat tipografi dan warna**, bukan dekorasi. Angka penting dibuat semi-bold dengan angka tabular.
5. **Densitas tinggi tetapi rapi.** Tabel dan grid yang padat dengan spacing presisi.
6. **Border tipis, shadow minimal** (`shadow-xs` paling banyak), dan radius kecil (6px).
7. **Satu gaya per komponen** di seluruh aplikasi: satu gaya tombol, satu gaya badge status, satu gaya kartu.

**Hindari:**
- gradien ungu-biru atau pink-oranye;
- emoji (📊 ✅) sebagai ikon;
- kartu melayang dengan shadow tebal tanpa border;
- warna neon atau pastel;
- lebih dari dua warna aksen dalam satu tampilan;
- whitespace berlebihan di aplikasi kerja.

---

## 2. Fondasi

### 2.1 Tipografi

| Peran | Class | Keterangan |
|---|---|---|
| Font | `font-sans` (Inter 400/500/600/700) | Dimuat lewat `laravel-vite-plugin/fonts` (Bunny Fonts) atau Google Fonts |
| Judul halaman | `text-xl font-semibold tracking-tight text-foreground` | Satu per halaman |
| Judul kartu / section | `text-sm font-semibold text-foreground` | |
| Label section (micro-label) | `.section-label` → `text-[0.6875rem] font-semibold tracking-[0.08em] uppercase text-muted-foreground` | Kesan formal; dipakai untuk label form, judul KPI, eyebrow |
| Body | `text-sm text-foreground` | Ukuran default aplikasi kerja |
| Teks pendukung | `text-xs text-muted-foreground` | Deskripsi, hint, tanggal |
| Angka penting / KPI | `tabular text-2xl font-semibold tracking-tight` | `.tabular` = angka tabular agar sejajar |
| Angka di tabel | otomatis tabular (`table { font-variant-numeric: tabular-nums }`) | |

Fitur font Inter yang diaktifkan: `font-feature-settings: 'cv02','cv03','cv04'` (angka dan huruf lebih tegas).

### 2.2 Warna

**Palet dasar (light).** Semua warna didefinisikan sebagai token di bagian 3.

| Token | Fungsi | Nilai |
|---|---|---|
| `background` | Latar halaman (biru-putih sangat muda) | `oklch(0.98 0.008 240)` ≈ #F4F8FC |
| `card` / `popover` | Permukaan kartu, menu | putih |
| `foreground` | Teks utama (biru keabuan gelap, bukan hitam) | `oklch(0.28 0.03 252)` ≈ #19344F |
| `muted-foreground` | Teks sekunder | `oklch(0.52 0.035 248)` ≈ #64788B |
| `primary` | Biru PLN: tombol, tautan, tab aktif | `oklch(0.55 0.14 245)` ≈ #0878C9 |
| `secondary` / `accent` / `muted` | Latar lembut biru muda (hover, header tabel) | ≈ #EAF4FC |
| `border` | Garis kartu dan tabel | `oklch(0.9 0.02 240)` ≈ #DCE6EF |
| `input` | Garis field (lebih gelap dari border agar terlihat) | `oklch(0.72 0.035 245)` |
| `destructive` | Error, hapus (merah gelap) | `oklch(0.5 0.17 26)` |
| `sidebar` | Rel navigasi biru PLN, teks putih | `oklch(0.44 0.115 250)` |

**Warna status.** Satu kategori punya satu warna, dan selalu berpasangan dengan `-surface` sebagai latarnya.

| Kategori | Arti | Teks | Latar |
|---|---|---|---|
| `pending` | Usulan / belum mulai | abu-abu `status-pending` | `status-pending-surface` |
| `berjalan` | Sedang diproses | kuning pisang `status-berjalan` | `status-berjalan-surface` |
| `selesai` | Selesai | hijau tua `status-selesai` | `status-selesai-surface` |
| `batal` | Dibatalkan / ditolak | merah gelap `status-batal` | `status-batal-surface` |

**Palet grafik (`--viz-*`):**

| Token | Hex | Pemakaian |
|---|---|---|
| `viz-blue` | #0878C9 | Total, nilai uang, seri utama |
| `viz-navy` | #064477 | Seri kedua, tahap akhir |
| `viz-sky` | #6AAEE3 | Seri ketiga |
| `viz-pale` | #B8D8F1 | Seri keempat |
| `viz-yellow` | #F5C928 | **Hanya** untuk "berjalan" |
| `viz-green` | #237A50 | Selesai |
| `viz-red` | #A83232 | Dibatalkan |
| `viz-grey` | #8795A3 | Usulan / pending / "Lainnya" |
| `viz-grid` | #DCE6EF | Garis grid grafik |

### 2.3 Radius, border, shadow, spacing

- **Radius:**
  - `--radius: 0.375rem` (6px);
  - `rounded-md` untuk kartu, tombol, dan field;
  - `rounded-sm` untuk badge status;
  - `rounded-full` hanya untuk titik dan angka bulat kecil.
- **Border:** `border border-border`, selalu 1px. Aksen tepi memakai garis 2px (`border-l-2 border-l-primary`, atau strip `h-0.5` di atas kartu KPI).
- **Shadow:** tanpa shadow pada kartu. `shadow-xs` hanya untuk tombol dan field. `shadow-sm` hanya untuk popover dan tooltip.
- **Spacing:**
  - jarak antar-section halaman `gap-5`;
  - antar-kartu dalam grid `gap-3` sampai `gap-4`;
  - padding halaman `p-4 md:p-6`;
  - padding kartu `px-4 py-3` (header) dan `p-4` (isi).

### 2.4 Ikon

Gunakan **Lucide** (`lucide-react`) saja.

| Konteks | Ukuran |
|---|---|
| Di dalam tombol | `size-4` |
| Ikon kartu / KPI | `size-4` sampai `size-5`, di kotak `size-7` sampai `size-9 rounded-md` dengan latar warna 10–12% |
| Ikon dekoratif besar (empty state) | `size-7 text-muted-foreground/60` dengan `strokeWidth={1.5}` |

---

## 3. Token CSS (salin ke `resources/css/app.css`)

Blok di bawah adalah bagian inti `app.css` aplikasi ini: tema Tailwind v4, palet light dan dark, status, grafik, serta utilitas dasar.

Untuk aplikasi baru:
1. Salin blok ini.
2. Pasang `tw-animate-css` atau hapus import-nya.
3. Sesuaikan baris `@source`.

```css
@import 'tailwindcss';

@import 'tw-animate-css';

@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';

@custom-variant dark (&:is(.dark *));

@theme {
    --font-sans:
        'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji',
        'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';

    --radius-lg: var(--radius);
    --radius-md: calc(var(--radius) - 2px);
    --radius-sm: calc(var(--radius) - 3px);

    --color-background: var(--background);
    --color-foreground: var(--foreground);

    --color-card: var(--card);
    --color-card-foreground: var(--card-foreground);

    --color-popover: var(--popover);
    --color-popover-foreground: var(--popover-foreground);

    --color-primary: var(--primary);
    --color-primary-foreground: var(--primary-foreground);

    --color-secondary: var(--secondary);
    --color-secondary-foreground: var(--secondary-foreground);

    --color-muted: var(--muted);
    --color-muted-foreground: var(--muted-foreground);

    --color-accent: var(--accent);
    --color-accent-foreground: var(--accent-foreground);

    --color-destructive: var(--destructive);
    --color-destructive-foreground: var(--destructive-foreground);

    --color-border: var(--border);
    --color-input: var(--input);
    --color-ring: var(--ring);

    --color-chart-1: var(--chart-1);
    --color-chart-2: var(--chart-2);
    --color-chart-3: var(--chart-3);
    --color-chart-4: var(--chart-4);
    --color-chart-5: var(--chart-5);

    --color-sidebar: var(--sidebar);
    --color-sidebar-foreground: var(--sidebar-foreground);
    --color-sidebar-primary: var(--sidebar-primary);
    --color-sidebar-primary-foreground: var(--sidebar-primary-foreground);
    --color-sidebar-accent: var(--sidebar-accent);
    --color-sidebar-accent-foreground: var(--sidebar-accent-foreground);
    --color-sidebar-border: var(--sidebar-border);
    --color-sidebar-ring: var(--sidebar-ring);

    /* Progress status palette - one muted tone per lifecycle category. */
    --color-status-pending: var(--status-pending);
    --color-status-pending-surface: var(--status-pending-surface);
    --color-status-batal: var(--status-batal);
    --color-status-batal-surface: var(--status-batal-surface);
    --color-status-berjalan: var(--status-berjalan);
    --color-status-berjalan-surface: var(--status-berjalan-surface);
    --color-status-selesai: var(--status-selesai);
    --color-status-selesai-surface: var(--status-selesai-surface);

    /* Dashboard chart palette (PLN Nusantara Power). */
    --color-viz-blue: var(--viz-blue);
    --color-viz-navy: var(--viz-navy);
    --color-viz-sky: var(--viz-sky);
    --color-viz-pale: var(--viz-pale);
    --color-viz-yellow: var(--viz-yellow);
    --color-viz-green: var(--viz-green);
    --color-viz-red: var(--viz-red);
    --color-viz-grey: var(--viz-grey);
    --color-viz-grid: var(--viz-grid);
}

:root {
    /* Light blue and white corporate palette (PLN). A single blue accent runs
       across the whole app; banana yellow appears only on the in-progress
       status badge, echoing the PLN logo without taking over. */
    --background: oklch(0.98 0.008 240);
    --foreground: oklch(0.28 0.03 252);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.28 0.03 252);
    --popover: oklch(1 0 0);
    --popover-foreground: oklch(0.28 0.03 252);

    /* PLN blue accent: clear and light, with white text for contrast. */
    --primary: oklch(0.55 0.14 245);
    --primary-foreground: oklch(0.99 0.01 240);
    --secondary: oklch(0.94 0.028 240);
    --secondary-foreground: oklch(0.36 0.1 248);
    --muted: oklch(0.955 0.016 240);
    --muted-foreground: oklch(0.52 0.035 248);
    --accent: oklch(0.92 0.04 240);
    --accent-foreground: oklch(0.36 0.1 248);
    --destructive: oklch(0.5 0.17 26);
    --destructive-foreground: oklch(0.99 0.01 240);
    --border: oklch(0.9 0.02 240);
    /* Darker than the card borders, so every field to fill in stands out. */
    --input: oklch(0.72 0.035 245);
    --ring: oklch(0.6 0.13 245);
    --radius: 0.375rem;

    --chart-1: oklch(0.55 0.14 245);
    --chart-2: oklch(0.64 0.12 240);
    --chart-3: oklch(0.72 0.1 238);
    --chart-4: oklch(0.8 0.075 236);
    --chart-5: oklch(0.87 0.05 235);

    /* Blue navigation rail with white text, anchoring every page. */
    --sidebar: oklch(0.44 0.115 250);
    --sidebar-foreground: oklch(0.96 0.02 240);
    --sidebar-primary: oklch(0.74 0.12 238);
    --sidebar-primary-foreground: oklch(0.18 0.03 252);
    --sidebar-accent: oklch(0.52 0.1 249);
    --sidebar-accent-foreground: oklch(0.98 0.01 240);
    --sidebar-border: oklch(0.56 0.08 249);
    --sidebar-ring: oklch(0.74 0.12 238);

    /* Status: grey pending, red batal, banana yellow berjalan, green selesai. */
    --status-pending: oklch(0.47 0.02 248);
    --status-pending-surface: oklch(0.95 0.012 240);
    --status-batal: oklch(0.46 0.16 26);
    --status-batal-surface: oklch(0.95 0.025 26);
    --status-berjalan: oklch(0.54 0.12 84);
    --status-berjalan-surface: oklch(0.95 0.08 95);
    --status-selesai: oklch(0.46 0.1 158);
    --status-selesai-surface: oklch(0.94 0.03 158);

    /* Dashboard charts: blue for totals and money, banana yellow only for
       running jobs, dark green for done, dark red for cancelled, grey for
       proposals. */
    --viz-blue: #0878c9;
    --viz-navy: #064477;
    --viz-sky: #6aaee3;
    --viz-pale: #b8d8f1;
    --viz-yellow: #f5c928;
    --viz-green: #237a50;
    --viz-red: #a83232;
    --viz-grey: #8795a3;
    --viz-grid: #dce6ef;
}

.dark {
    --background: oklch(0.19 0.014 252);
    --foreground: oklch(0.93 0.006 250);
    --card: oklch(0.23 0.016 252);
    --card-foreground: oklch(0.93 0.006 250);
    --popover: oklch(0.23 0.016 252);
    --popover-foreground: oklch(0.93 0.006 250);

    --primary: oklch(0.66 0.115 247);
    --primary-foreground: oklch(0.17 0.02 252);
    --secondary: oklch(0.28 0.018 252);
    --secondary-foreground: oklch(0.93 0.006 250);
    --muted: oklch(0.28 0.018 252);
    --muted-foreground: oklch(0.68 0.014 250);
    --accent: oklch(0.31 0.025 250);
    --accent-foreground: oklch(0.93 0.006 250);
    --destructive: oklch(0.56 0.17 26);
    --destructive-foreground: oklch(0.97 0.01 26);
    --border: oklch(0.32 0.018 252);
    --input: oklch(0.48 0.025 252);
    --ring: oklch(0.55 0.09 248);

    --chart-1: oklch(0.66 0.115 247);
    --chart-2: oklch(0.58 0.1 247);
    --chart-3: oklch(0.5 0.085 247);
    --chart-4: oklch(0.42 0.07 247);
    --chart-5: oklch(0.35 0.05 247);

    --sidebar: oklch(0.17 0.018 252);
    --sidebar-foreground: oklch(0.88 0.01 250);
    --sidebar-primary: oklch(0.62 0.13 245);
    --sidebar-primary-foreground: oklch(0.99 0 0);
    --sidebar-accent: oklch(0.26 0.03 250);
    --sidebar-accent-foreground: oklch(0.96 0.004 250);
    --sidebar-border: oklch(0.28 0.025 250);
    --sidebar-ring: oklch(0.62 0.13 245);

    --status-pending: oklch(0.75 0.014 252);
    --status-pending-surface: oklch(0.3 0.012 252);
    --status-batal: oklch(0.72 0.13 26);
    --status-batal-surface: oklch(0.31 0.06 26);
    --status-berjalan: oklch(0.79 0.11 82);
    --status-berjalan-surface: oklch(0.32 0.055 82);
    --status-selesai: oklch(0.75 0.11 158);
    --status-selesai-surface: oklch(0.3 0.05 158);

    --viz-blue: #4ea3e6;
    --viz-navy: #9cc3e8;
    --viz-sky: #2f78b4;
    --viz-pale: #24527a;
    --viz-yellow: #e9c84a;
    --viz-green: #4fae7f;
    --viz-red: #d46a6a;
    --viz-grey: #9aa6b2;
    --viz-grid: oklch(0.32 0.018 252);
}

@layer base {
    * {
        @apply border-border;
    }

    body {
        @apply bg-background text-foreground;
        font-feature-settings:
            'cv02' 1,
            'cv03' 1,
            'cv04' 1;
    }

    /* Numeric data must stay column-aligned inside dense tables. */
    table,
    .tabular {
        font-variant-numeric: tabular-nums;
    }
}

@layer components {
    /* Uppercase micro-label used for section headers across the app. */
    .section-label {
        @apply text-[0.6875rem] font-semibold tracking-[0.08em] text-muted-foreground uppercase;
    }
}
```

---

## 4. Komponen Dasar (shadcn/ui yang disesuaikan)

### 4.1 Button

`variant`: `default` (biru utama), `outline`, `secondary`, `ghost`, `destructive`, `link`. `size`: `default` (h-9), `sm` (h-8), `lg` (h-10), `icon` (9×9).

```ts
const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-[color,box-shadow] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground shadow-xs hover:bg-primary/90",
        destructive: "bg-destructive text-white shadow-xs hover:bg-destructive/90",
        outline: "border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground",
        secondary: "bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80",
        ghost: "hover:bg-accent hover:text-accent-foreground",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-9 px-4 py-2 has-[>svg]:px-3",
        sm: "h-8 rounded-md px-3 has-[>svg]:px-2.5",
        lg: "h-10 rounded-md px-6 has-[>svg]:px-4",
        icon: "size-9",
      },
    },
    defaultVariants: { variant: "default", size: "default" },
  },
)
```

**Aturan tombol:**
- Satu tombol `default` (biru) per area, untuk aksi utama seperti "Buat Pengadaan" atau "Simpan".
- Aksi sekunder memakai `outline`.
- Aksi di dalam tabel memakai `outline size="sm"` atau `ghost`.
- Hapus memakai `destructive` dan selalu dengan konfirmasi.

### 4.2 Input dan Textarea

```
Input:    flex h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 py-1 text-base md:text-sm shadow-xs
          placeholder:text-muted-foreground transition-[color,box-shadow] outline-none
          focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]
          aria-invalid:border-destructive aria-invalid:ring-destructive/20
          disabled:cursor-not-allowed disabled:opacity-50
Textarea: sama, ditambah field-sizing-content min-h-16 py-2
```

### 4.3 Select (Radix)

```
Trigger: flex w-fit items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm
         whitespace-nowrap shadow-xs data-[size=default]:h-9 data-[size=sm]:h-8 data-[placeholder]:text-muted-foreground
         focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]
Ikon:    ChevronDown size-4 opacity-50
```

Pola lebar di baris filter: `className="w-0 min-w-full"` pada trigger, dengan pembungkus `w-full sm:w-auto sm:min-w-36 sm:flex-[1_1_auto]`.

### 4.4 Checkbox

```
size-4 shrink-0 rounded-[4px] border border-input shadow-xs
data-[state=checked]:bg-primary data-[state=checked]:border-primary data-[state=checked]:text-primary-foreground
focus-visible:ring-ring/50 focus-visible:ring-[3px]
```

### 4.5 Label form

Label berada di atas field: `<Label className="section-label">Nama Field</Label>`, dibungkus `space-y-1.5`. Pesan error memakai `text-sm text-destructive` tepat di bawah field.

### 4.6 Tabs (garis bawah, aktif biru)

```
TabsList:    inline-flex h-9 items-center gap-1 border-b border-border
TabsTrigger: -mb-px inline-flex h-9 items-center gap-2 border-b-2 px-3 text-sm font-medium whitespace-nowrap transition-colors
  aktif:     border-primary text-primary font-semibold
  tidak:     border-transparent text-muted-foreground hover:text-primary hover:border-primary/40
```

Varian tab "pil" (dipakai di filter tab): `rounded-md px-3 py-1.5 text-sm`, aktif `bg-primary text-primary-foreground`, tidak aktif `text-muted-foreground hover:bg-muted`. Jumlah di dalam tab memakai badge bulat `tabular rounded-full px-1.5 text-[0.6875rem] font-semibold`.

### 4.7 Table

```
Container: relative w-full overflow-x-auto            (tabel lebar menggulir di dalam kartu, bukan halamannya)
Table:     w-full caption-bottom text-sm
Header:    className="bg-muted/60"; baris header: "hover:bg-transparent"
Head:      h-10 px-3 text-left align-middle text-[0.6875rem] font-semibold tracking-[0.06em] uppercase whitespace-nowrap text-muted-foreground
Row:       border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted
Cell:      px-3 py-2.5 align-middle
Footer:    bg-muted/60 border-t font-medium
```

**Aturan tabel:**
- Nilai uang dibuat `text-right tabular whitespace-nowrap font-medium`.
- Nomor dokumen memakai `tabular text-xs`.
- Nama pengadaan menjadi tautan `font-medium hover:text-primary hover:underline`, dengan info kedua di bawahnya (`text-xs text-muted-foreground`).
- Kolom yang bisa diurutkan memakai tombol berisi teks dan ikon `ArrowUpDown` / `ArrowUp` / `ArrowDown` `size-3`, plus `aria-sort` pada `<th>`.

### 4.8 Badge umum

```
inline-flex w-fit items-center justify-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap
default:   border-transparent bg-primary text-primary-foreground
secondary: border-transparent bg-secondary text-secondary-foreground
outline:   text-foreground
```

---

## 5. Komponen Aplikasi

### 5.1 StatusBadge (status progres)

Satu-satunya cara menampilkan status. Bentuknya titik warna ditambah label, dengan latar lembut.

```tsx
const categoryClasses = {
  pending:  'bg-status-pending-surface text-status-pending',
  batal:    'bg-status-batal-surface text-status-batal',
  berjalan: 'bg-status-berjalan-surface text-status-berjalan',
  selesai:  'bg-status-selesai-surface text-status-selesai',
};

<span className={cn('inline-flex w-fit items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap', categoryClasses[category])}>
  <span className="size-1.5 rounded-full bg-current" />
  {label}
</span>
```

`ApprovalBadge` memakai pemetaan yang sama tanpa titik:

| Status persetujuan | Warna |
|---|---|
| `belum_diajukan` | pending |
| `menunggu_persetujuan` | berjalan |
| `disetujui` | selesai |
| `ditolak` | batal |

### 5.2 PageHeader

```tsx
<div className="flex flex-col gap-3 border-b border-border pb-4 sm:flex-row sm:items-end sm:justify-between">
  <div className="space-y-1">
    <p className="section-label">{eyebrow}</p>                                  {/* mis. "SIM-SCM UP Kendari" */}
    <h1 className="text-xl font-semibold tracking-tight text-foreground">{title}</h1>
    <p className="max-w-2xl text-sm text-muted-foreground">{description}</p>
  </div>
  <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>  {/* tombol utama di kanan */}
</div>
```

### 5.3 Panel / Kartu berjudul (pola kartu standar)

Dipakai untuk semua kartu berisi data: grafik, daftar, dan tabel.

```tsx
<section className="flex min-w-0 flex-col rounded-md border border-border bg-card">
  <header className="flex items-start justify-between gap-3 border-b border-border px-4 py-3">
    <div className="min-w-0">
      <h2 className="text-sm font-semibold text-foreground">{title}</h2>
      <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
    </div>
    {action /* mis. <Link className="text-xs font-medium text-primary hover:underline">Lihat semua</Link> */}
  </header>
  <div className="flex-1 p-4">{children}</div>   {/* p-0 bila isinya tabel */}
</section>
```

### 5.4 StatCard (kartu angka ringkas)

```tsx
<div className="flex items-start justify-between gap-3 rounded-md border border-border bg-card p-4">   {/* + "border-l-2 border-l-primary" untuk aksen */}
  <div className="min-w-0 space-y-1">
    <p className="section-label truncate">{label}</p>
    <p className="tabular text-2xl leading-tight font-semibold text-foreground">{value}</p>
    <p className="truncate text-xs text-muted-foreground">{hint}</p>
  </div>
  <Icon className="mt-0.5 size-5 shrink-0 text-muted-foreground/70" strokeWidth={1.75} />
</div>
```

### 5.5 KPI Card (dashboard)

Kartu angka utama dengan strip warna di atas dan ikon dalam kotak berwarna.

```tsx
<section className="@container relative flex min-w-0 flex-col overflow-hidden rounded-md border border-border bg-card p-4 pt-[1.1rem]">
  <span className="absolute inset-x-0 top-0 h-0.5" style={{ backgroundColor: color }} />
  <div className="flex items-start justify-between gap-3">
    <p className="section-label">{label}</p>
    <span className="flex size-8 shrink-0 items-center justify-center rounded-md"
          style={{ color, backgroundColor: `color-mix(in srgb, ${color} 12%, transparent)` }}>
      <Icon className="size-4" />
    </span>
  </div>
  <p className="tabular mt-1 text-[1.625rem] leading-tight font-semibold tracking-tight text-foreground">{value}</p>
  <p className="mt-1.5 text-xs text-muted-foreground">{hint}</p>
</section>
```

**Aturan KPI card:**
- **Nilai panjang (Rupiah):** ganti ukuran angka menjadi `text-[clamp(1rem,7.5cqi,1.625rem)] [overflow-wrap:anywhere]`. Angka mengecil mengikuti lebar kartu (container query `@container`) dan tidak keluar dari kartu.
- **Grid 6 KPI:** `grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6`.
- **Warna strip:** total dan nilai biru, berjalan kuning, selesai hijau, approval navy, dibatalkan merah.

### 5.6 Tile tugas ("Perlu Tindakan")

```tsx
<Link className="group flex h-full items-center gap-3 rounded-md border border-border bg-background px-3.5 py-3 transition-colors hover:border-primary/50 hover:bg-accent/40">
  <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-violet-500/10 text-violet-700"><Icon className="size-5" /></span>
  <span className="min-w-0 flex-1 text-sm leading-snug font-medium text-foreground">{title}</span>
  <span className="tabular shrink-0 text-2xl leading-none font-semibold text-foreground">{count}</span>
  <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
</Link>
```

Grid tile: `grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5`. Nada ikon (latar 10% dan teks 700):

| Warna | Class |
|---|---|
| Biru | `bg-primary/10 text-primary` |
| Langit | `bg-sky-500/10 text-sky-700` |
| Kuning | `bg-amber-500/10 text-amber-700` |
| Hijau | `bg-emerald-500/10 text-emerald-700` |
| Ungu | `bg-violet-500/10 text-violet-700` |
| Merah | `bg-destructive/10 text-destructive` |

### 5.7 Daftar baris yang bisa diklik (list di dalam kartu)

```tsx
<ul className="divide-y divide-border">
  <li>
    <Link className="group flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-accent/40">
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium text-foreground group-hover:text-primary">{name}</p>
        <p className="truncate text-xs text-muted-foreground"><span className="tabular">{number}</span> · {note}</p>
      </div>
      <span className="tabular hidden shrink-0 text-xs text-muted-foreground sm:block">{date}</span>
      <ChevronRight className="size-4 shrink-0 text-muted-foreground group-hover:translate-x-0.5 group-hover:text-primary" />
    </Link>
  </li>
</ul>
<p className="border-t border-border px-4 py-2 text-xs text-muted-foreground">+5 lainnya</p>
```

### 5.8 Notice / peringatan

```tsx
{/* Perlu tindakan (merah gelap) */}
<div className="flex items-start gap-3 rounded-md border border-destructive/40 bg-destructive/5 p-4">
  <RotateCcw className="mt-0.5 size-4 shrink-0 text-destructive" />
  <div className="space-y-0.5">
    <p className="text-sm font-semibold text-destructive">4 perencanaan dikembalikan untuk revisi</p>
    <p className="text-xs text-muted-foreground">Perbaiki sesuai catatan reviewer, lalu ajukan ulang.</p>
  </div>
</div>

{/* Info / tautan aksen biru */}
<Link className="flex items-center justify-between rounded-md border border-l-2 border-border border-l-primary bg-card px-4 py-3 text-sm hover:bg-accent/40">…</Link>
```

### 5.9 Empty state

```tsx
<div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
  <Icon className="size-7 text-muted-foreground/60" strokeWidth={1.5} />
  <p className="text-sm font-medium text-foreground">Belum ada data</p>
  <p className="max-w-sm text-sm text-muted-foreground">Keterangan singkat apa yang perlu dilakukan.</p>
</div>

{/* Varian di dalam grafik / panel: kotak garis putus-putus */}
<div className="flex min-h-40 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border px-4 py-8 text-center">…</div>
```

Teks empty state harus jujur, misalnya "Belum ada data" atau "Data belum tersedia". Jangan pernah mengisi angka palsu.

### 5.10 Baris filter

```tsx
<section className="rounded-md border border-border bg-card px-4 py-3">
  <div className="flex flex-wrap items-end gap-3">
    <div className="w-full space-y-1.5 sm:w-auto sm:min-w-36 sm:flex-[1_1_auto]">
      <Label className="section-label">Periode</Label>
      <Select>…<SelectTrigger className="w-0 min-w-full" />…</Select>
    </div>
    …
    <Button variant="ghost" size="sm"><RotateCcw className="size-3.5" />Reset Filter</Button>
  </div>
  <p className="mt-2.5 text-xs text-muted-foreground">Periode data: 1 Jan 2026 – 31 Des 2026</p>
</section>
```

Saat data dimuat ulang, beri tanda di dua tempat:
- area isi: `transition-opacity opacity-60 pointer-events-none` dan `aria-busy`;
- baris keterangan: `Loader2 size-3.5 animate-spin` dan teks "Memperbarui data…".

### 5.11 Paginasi

```
Ringkasan:  tabular text-xs text-muted-foreground   ("Menampilkan 1–10 dari 77 data")
Tombol hal: tabular min-w-8 rounded-sm border px-2 py-1 text-xs
  aktif:    border-primary bg-primary text-primary-foreground
  lainnya:  border-border bg-card hover:bg-muted disabled:opacity-40
Wadah:      flex flex-col gap-2 border-t border-border px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between
```

### 5.12 Timeline (riwayat / aktivitas)

```tsx
<li className="flex gap-3">
  <div className="flex flex-col items-center">
    <span className="flex size-7 shrink-0 items-center justify-center rounded-full border border-border bg-card" style={{ color }}>
      <Icon className="size-3.5" />
    </span>
    <span className="w-px flex-1 bg-border" />           {/* tidak ada pada item terakhir */}
  </div>
  <div className="min-w-0 flex-1 pb-4">
    <div className="flex flex-wrap items-baseline justify-between gap-x-3">
      <p className="text-sm font-semibold text-foreground">{label}</p>
      <time className="tabular text-xs text-muted-foreground">{dateTime}</time>
    </div>
    <p className="text-xs text-muted-foreground">{user}</p>
    <p className="mt-0.5 line-clamp-2 text-xs text-foreground/80">{description}</p>
  </div>
</li>
```

**Ikon menurut hasil aktivitas:**

| Hasil | Ikon dan warna |
|---|---|
| Disetujui | `CheckCircle2` hijau |
| Dikembalikan | `Undo2` merah |
| Ditunjuk | `UserPlus` biru |
| Dibuat | `FilePlus2` biru |
| Selesai | `BadgeCheck` hijau |
| Diajukan | `Send` navy |
| Dokumen | `FileUp` sky |
| Edit | `PencilLine` abu |

### 5.13 Pipeline tahap (stepper horizontal)

Kartu per tahap berisi:
- nomor dalam lingkaran: `size-5 rounded-full border text-[0.625rem] font-semibold`;
- label `text-xs font-semibold`;
- angka `tabular text-2xl font-semibold`;
- persentase `text-xs text-muted-foreground`;
- bilah progres `h-1.5 rounded-full bg-muted` dengan isi berwarna tahap.

Grid tahap: `grid gap-2 sm:grid-cols-2 lg:grid-cols-5`, dengan panah `ChevronRight` di antara kartu (`lg:block`). Kartu aktif: `border-primary ring-1 ring-primary`; kartu lain diredupkan (`opacity-60`).

**Warna tahap:**

| Tahap | Warna |
|---|---|
| Usulan | grey |
| Perencanaan | sky |
| Pelaksanaan | blue |
| Penyerahan & Pembayaran | navy |
| Selesai | green |
| Dibatalkan | red |

---

## 6. Grafik (SVG, tanpa library)

Semua grafik digambar dengan SVG sendiri, tanpa Chart.js maupun ApexCharts, sehingga mengikuti token tema dan dark mode. Komponennya ada di `resources/js/components/charts/`.

| Komponen | Fungsi | Gaya kunci |
|---|---|---|
| `chart-kit.tsx` | `VIZ` (palet), `CATEGORICAL`, `useElementWidth` (ResizeObserver), `niceMax`, `ChartLegend`, `ChartTooltip`, `ChartEmpty` | |
| `grouped-column-chart.tsx` | Kolom berkelompok per bulan | Tinggi 260px; grid putus-putus `3 3` warna `viz-grid`; kolom `rx=1.5`; label sumbu `text-[10px] fill-muted-foreground` |
| `area-chart.tsx` | Garis dengan area (nilai Rupiah) | Garis `strokeWidth=2`; area `fillOpacity=0.1`; garis bantu hover dan titik `r=3.5` |
| `ring-chart.tsx` | Donut dan legend persentase | Diameter 160px, tebal 22; total di tengah `text-2xl font-semibold`; hover menebalkan slice dan meredupkan yang lain (0.35) |
| `gauge-chart.tsx` | Setengah lingkaran 0–100% | Tebal 18; angka `text-3xl font-semibold` |
| `ranking-bars.tsx` | `RankingBars` (peringkat horizontal) dan `PairedBars` (dua seri per orang) | Bilah `h-2 rounded-[2px] bg-muted`; nomor urut, jumlah, dan persentase |

**Aturan grafik:**
- **Tooltip:** `rounded-md border border-border bg-popover px-3 py-2 text-xs shadow-sm`; pindah ke sisi kiri bila dekat tepi kanan.
- **Legend:** tombol kotak warna `size-2.5 rounded-[2px]` dengan label `text-xs text-muted-foreground`. Klik untuk menyembunyikan seri (minimal satu seri tetap tampil).
- **Satu makna, satu warna di semua grafik:** kuning hanya untuk berjalan, hijau selesai, merah batal, abu usulan.
- **Kategori lebih dari 5:** gabungkan sisanya menjadi "Lainnya (n)" berwarna abu.
- **Selalu sediakan dua keadaan:** kosong ("Belum ada data") dan gagal dimuat ("Data tidak dapat dimuat").
- **Keterangan jujur:** nilai HPE ≠ realisasi bayar; distribusi PIC = jumlah penugasan, bukan kinerja.

---

## 7. Layout

### 7.1 Kerangka aplikasi

- **Sidebar:**
  - tetap di kiri, berwarna `bg-sidebar` (biru PLN) dengan teks putih;
  - item aktif memakai `bg-sidebar-accent`;
  - judul grup memakai micro-label uppercase;
  - bisa disematkan (pin) dan diciutkan.
- **Header atas:** breadcrumb di kiri; identitas pengguna (nama dan peran uppercase kecil) serta lonceng notifikasi di kanan.
- **Konten:** `flex flex-col gap-5 p-4 md:p-6` di atas `bg-background`.

### 7.2 Grid dashboard

- **Baris grafik:** `grid gap-4 xl:grid-cols-3`. Grafik utama memakai `xl:col-span-2` dan pendampingnya satu kolom.
- **Tanpa lubang:** kartu terakhir di setiap baris melebar mengisi sisa kolom (komponen `FilledGrid`), sehingga baris tidak pernah setengah kosong.
- **Kolom berdampingan dengan tinggi berbeda:** kolom yang panjang (mis. timeline) mengikuti tinggi kolom pendamping dan isinya digulir di dalam kartu (`xl:absolute xl:inset-0 xl:overflow-y-auto`). Jangan biarkan kartu memanjang kosong.
- **Urutan dashboard:** filter → KPI → tugas → pipeline → grafik → tabel → aktivitas.

### 7.3 Responsif

- Lebar HP minimal 375px tanpa geser horizontal halaman; tabel lebar menggulir di dalam wadahnya sendiri.
- Breakpoint yang dipakai: `sm` (640px), `lg` (1024px), `xl` (1280px), dan `2xl` (1536px) untuk 6 KPI dan 5 tile sebaris.
- Label filter dan field turun satu per baris di HP (`w-full`), lalu berjajar di `sm:`.

---

## 8. Aksesibilitas dan Interaksi

- **Fokus:** semua elemen interaktif memakai `focus-visible:ring-[3px] ring-ring/50`. Jangan hapus outline tanpa pengganti.
- **Tombol toggle:** pakai `aria-pressed`, misalnya legend grafik dan tahap pipeline.
- **Tabel:** pakai `aria-sort` pada header tabel yang diurutkan, dan `aria-current="page"` pada halaman aktif paginasi.
- **Status pemuatan:** pakai `aria-live="polite"` pada teks status pemuatan dan `aria-busy` pada area yang dimuat ulang.
- **Grafik:** setiap SVG grafik punya `role="img"` dan `aria-label`.
- **Kontras:** teks sekunder minimal memakai `muted-foreground`. Jangan pakai warna status sebagai satu-satunya pembeda; selalu sertakan label.
- **Transisi:** singkat dan fungsional saja (`transition-colors`, `transition-opacity`, `animate-in fade-in`). Tanpa animasi dekoratif.

---

## 9. Daftar Periksa untuk Aplikasi Baru

1. Pasang Tailwind v4, `tw-animate-css`, `lucide-react`, `class-variance-authority`, `clsx` + `tailwind-merge` (fungsi `cn`), dan Radix (`@radix-ui/react-select`, `-checkbox`, `-dialog`, `-dropdown-menu`, `-tooltip`, `-slot`).
2. Salin blok CSS di bagian 3 dan muat font Inter (400/500/600/700).
3. Salin komponen `ui/` (button, input, select, checkbox, table, tabs, badge, label, dialog).
4. Buat komponen aplikasi: `StatusBadge`, `PageHeader`, `Panel`, `StatCard`, `EmptyState`.
5. Petakan status domain Anda ke 4 kategori: `pending`, `berjalan`, `selesai`, `batal`.
6. Pastikan satu warna aksen (biru `primary`). Kuning hanya untuk "berjalan".
7. Uji di 375px (HP), 1366px (laptop), dan 1920px (monitor), dalam mode terang dan gelap.
