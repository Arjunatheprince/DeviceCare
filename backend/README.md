# DeviceCare Backend

Laravel backend untuk dashboard DeviceCare, API sensor laptop, diagnosis hardware, alert, riwayat, dan AI assistant.

## Fitur

- Dashboard kesehatan laptop real-time.
- API registrasi device dan pengiriman sensor reading.
- Diagnosis CPU, RAM, storage, battery, GPU, dan proses.
- Alert aktif, riwayat, trend, baseline, anomaly detection, dan report.
- Integrasi AI assistant melalui konfigurasi `OPENAI_*`.

## Menjalankan Lokal

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Dashboard tersedia di `http://127.0.0.1:8000`.

## Agent

Jalankan agent dari folder `agent` agar data hardware masuk ke backend:

```bash
python main.py --loop --send --interval 1 --url http://127.0.0.1:8000
```

## Catatan Konfigurasi

- Project memakai SQLite secara default.
- Jangan commit file `.env`; gunakan `.env.example` untuk template konfigurasi.
- Isi `OPENAI_API_KEY` hanya di `.env` lokal jika fitur AI assistant akan dipakai.