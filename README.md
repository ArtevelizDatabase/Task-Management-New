# Task Management (TaskFlow) — CodeIgniter 4

Repo ini berisi source code aplikasi TaskFlow (CodeIgniter 4).

## Keamanan (penting)

- **Jangan commit** file `.env` (berisi secret/credential). Gunakan `.env.example` sebagai template.
- Folder runtime seperti `writable/` dan dependency `vendor/` **tidak** di-commit.

## Setup lokal (development)

1. Install dependency:

```bash
composer install
```

2. Buat `.env` dari template:

```bash
cp .env.example .env
```

3. Atur konfigurasi database di `.env`, lalu jalankan migrasi:

```bash
php spark migrate
```

4. Jalankan server:

```bash
php spark serve
```

## Build paket production (ZIP)

Project ini menyediakan script untuk membuat paket deploy yang siap diupload ke server.

```bash
./build-production-zip.sh
```

Output: `PRODUCTION-DEPLOY.zip` (diignore oleh git).

