# DeviceCare

DeviceCare adalah aplikasi monitoring kesehatan laptop dengan dashboard Laravel dan agent Python lokal. Agent membaca kondisi hardware, mengirim data ke backend, lalu dashboard menampilkan health score, diagnosis, alert, riwayat, trend, dan report.

## Struktur Project

```text
backend/   Laravel dashboard dan API
agent/     Python hardware collector dan sender
```

## Menjalankan Dashboard

```bash
cd backend
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Mengirim Data Laptop

Di terminal lain:

```bash
cd agent
pip install -r requirements.txt
python main.py --loop --send --interval 1 --url http://127.0.0.1:8000
```

## GitHub

Nama project yang disarankan untuk repository adalah `devicecare`, tanpa kata `final`.