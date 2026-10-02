# DeviceCare Agent — Milestone 1

Python local agent untuk membaca sensor hardware laptop dan menghasilkan data JSON terstruktur.

## Struktur

```
devicecare-agent/
├── agent/
│   ├── __init__.py
│   ├── collector.py      # baca semua sensor hardware
│   ├── models.py         # dataclass untuk tiap komponen
│   └── diagnostics.py    # rule engine: Normal / Warning / Critical
├── main.py               # entry point
├── requirements.txt
└── README.md
```

## Instalasi

```bash
# Buat virtual environment (disarankan)
python -m venv venv
venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt
```

> **Catatan:** `WMI` membutuhkan `pywin32`. Jika gagal, jalankan:
> ```bash
> pip install pywin32
> python -m pywin32_postinstall install
> ```

## Menjalankan

```bash
# Output sekali — pretty print
python main.py

# Output sekali — raw JSON
python main.py --json

# Loop setiap 1 detik — pretty print
python main.py --loop

# Loop setiap 1 detik + kirim ke backend Laravel
python main.py --loop --send --interval 1 --url http://127.0.0.1:8000
```

## Contoh Output

```
══════════════════════════════════════════════════════
  DEVICECARE — Hardware Health Monitor
  2026-09-23T05:10:00+00:00
  MyLaptop  |  ASUS VivoBook 15
  Windows 10.0.19045
══════════════════════════════════════════════════════

  CPU  Intel(R) Core(TM) i5-11300H @ 3.10GHz
  Usage       24.0%
  Temperature 82.0°C
  Clock       3100 MHz   Cores 4 / Threads 8
  Status      WARNING
    Temperatur CPU tinggi (82.0°C) padahal beban CPU rendah (24%).
    Ini mengindikasikan masalah pada sistem pendinginan.

    Saran:
    - Periksa ventilasi laptop
    - Bersihkan kipas dan heatsink dari debu
    - Periksa kondisi thermal paste

  RAM
  Usage       73.0%  (11.7/16.0 GB)
  Available   4.3 GB
  Status      NORMAL

  Storage
  Usage       46.0%  (230.0/500.0 GB)
  Free        270.0 GB
  Status      NORMAL

  Battery
  Level       82%   🔋 On battery
  Remaining   3h 20m
  Status      NORMAL

──────────────────────────────────────────────────────
  Overall Health Score   78 / 100
  [███████████████░░░░░]
══════════════════════════════════════════════════════
```

## Contoh JSON Output

```json
{
  "timestamp": "2026-09-23T05:10:00+00:00",
  "cpu_usage": 24.0,
  "cpu_temperature": 82.0,
  "ram_usage": 73.0,
  "disk_usage": 46.0,
  "battery_percentage": 82.0,
  "health_score": 78
}
```

## Cara Kerja Diagnostic Engine

Diagnosis tidak berdasarkan satu angka saja, melainkan kombinasi kondisi:

| Kondisi | Status |
|---------|--------|
| CPU temp ≥ 95°C (berapapun load) | CRITICAL |
| CPU temp ≥ 85°C + load < 40% | WARNING — masalah pendinginan |
| CPU temp ≥ 85°C (load berat) | WARNING — perlu diperhatikan |
| CPU temp ≥ 75°C + load < 20% | WARNING |
| RAM ≥ 90% | CRITICAL |
| RAM ≥ 75% | WARNING |
| Storage ≥ 95% | CRITICAL |
| Storage ≥ 85% | WARNING |
| Baterai ≤ 10% (tidak charging) | CRITICAL |
| Baterai ≤ 20% (tidak charging) | WARNING |

### Bobot Health Score

| Komponen | Bobot | Alasan |
|----------|-------|--------|
| CPU | 35% | Paling berdampak pada performa & umur laptop |
| RAM | 20% | Bottleneck langsung terhadap responsivitas |
| Storage | 15% | Penuh = potensi crash OS |
| Battery | 15% | Menentukan portabilitas |
| GPU | 15% | Penting untuk workload grafis |

## Tentang Pembacaan Temperatur (Windows)

Browser dan aplikasi biasa tidak bisa membaca suhu CPU langsung dari OS.
Agent ini mencoba 3 cara secara berurutan:

1. `psutil.sensors_temperatures()` — kadang tersedia di Windows dengan driver tertentu
2. WMI `MSAcpi_ThermalZoneTemperature` — thermal zone dari ACPI (tersedia di kebanyakan laptop)
3. OpenHardwareMonitor WMI bridge — jika OHM sedang berjalan di background

Jika ketiga cara gagal, suhu ditampilkan sebagai `N/A`. Untuk hasil terbaik,
install dan jalankan [LibreHardwareMonitor](https://github.com/LibreHardwareMonitor/LibreHardwareMonitor)
sebelum menjalankan agent.

## Milestone Berikutnya

- **Milestone 2:** Kirim data JSON ke Laravel API, tampilkan di web dashboard
- **Milestone 3:** Diagnostic engine lebih kompleks + riwayat data
- **Milestone 4:** Device-aware diagnosis + anomaly detection
- **Milestone 5:** AI Assistant berbasis data sensor
