# CSS Architecture Guide

Tujuan: style mudah diubah, scalable, dan tidak membingungkan saat project bertambah.

## Struktur

- `themes/`  
  Hanya design tokens per tema (warna, shadow, surface, text).
- `base/`  
  Fondasi global UI (layout utama, button, form, table, modal, responsive shell).
- `components/`  
  Komponen reusable lintas halaman (contoh: richtext modal).
- `pages/`  
  Style khusus halaman tertentu (tasks, submissions, trash, settings fields, dashboard nanti).

## Prinsip Pakai

1. Ubah warna/nuansa brand: edit file di `themes/`.
2. Ubah gaya global komponen: edit `base/app-base.css`.
3. Ubah satu halaman saja: edit file di `pages/`.
4. Hindari `<style>` di view, simpan di assets.
5. Hindari inline `style=""` kecuali benar-benar dinamis dari data runtime.

## Naming Convention

- `u-*` utility class kecil
- `c-*` reusable component class
- `p-*` page-specific class
- `is-*` state class (mis. `is-active`, `is-hidden`)

## Menambah Halaman Baru

Contoh untuk halaman `dashboard`:

1. Buat `pages/dashboard.css`
2. Include di view dashboard:
   `<link rel="stylesheet" href="/assets/css/pages/dashboard.css" />`
3. Pakai komponen global dari `base/` dan tambahkan hanya style khusus dashboard.
