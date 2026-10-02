"""
Diagnostic rule engine.

Rules deliberately consider context, not just raw thresholds.
Example: CPU 87°C with only 22% load is more suspicious than 87°C under 100% load.
"""
from typing import Optional
from .models import (
    BatteryData, CpuData, DiskData, GpuData,
    HardwareSnapshot, NetworkData, RamData, SmartDiskData,
)

STATUS_NORMAL   = "NORMAL"
STATUS_WARNING  = "WARNING"
STATUS_CRITICAL = "CRITICAL"


# ── individual component rules ────────────────────────────────────────────────

def diagnose_cpu(cpu: CpuData) -> CpuData:
    usage = cpu.usage_percent
    temp  = cpu.temperature_celsius

    status      = STATUS_NORMAL
    message     = "CPU beroperasi dalam kondisi normal."
    suggestions: list[str] = []

    if temp is not None:
        if temp >= 95:
            status  = STATUS_CRITICAL
            message = (
                f"Temperatur CPU sangat tinggi ({temp}°C). "
                "Risiko thermal throttling atau kerusakan komponen."
            )
            suggestions = [
                "Matikan aplikasi berat segera",
                "Pastikan ventilasi laptop tidak tertutup",
                "Periksa apakah kipas masih berfungsi",
                "Pertimbangkan penggantian thermal paste",
            ]
        elif temp >= 85 and usage < 40:
            # Panas di bawah beban rendah — indikator masalah pendinginan
            status  = STATUS_WARNING
            message = (
                f"Temperatur CPU tinggi ({temp}°C) padahal beban CPU rendah ({usage}%). "
                "Ini mengindikasikan masalah pada sistem pendinginan."
            )
            suggestions = [
                "Periksa ventilasi laptop",
                "Bersihkan kipas dan heatsink dari debu",
                "Periksa kondisi thermal paste",
                "Hindari penggunaan di permukaan empuk (kasur, sofa)",
            ]
        elif temp >= 85:
            status  = STATUS_WARNING
            message = (
                f"Temperatur CPU cukup tinggi ({temp}°C) di bawah beban {usage}%. "
                "Pada beban berat hal ini bisa terjadi, namun perlu diperhatikan."
            )
            suggestions = [
                "Periksa ventilasi laptop",
                "Bersihkan kipas secara berkala",
                "Hindari penggunaan di permukaan yang menutup ventilasi",
            ]
        elif temp >= 75 and usage < 20:
            status  = STATUS_WARNING
            message = (
                f"Temperatur CPU ({temp}°C) relatif tinggi untuk beban ringan ({usage}%). "
                "Perlu diperhatikan."
            )
            suggestions = [
                "Periksa program yang berjalan di background",
                "Pastikan ventilasi tidak tersumbat",
            ]

    if usage >= 95 and status == STATUS_NORMAL:
        status  = STATUS_WARNING
        message = f"CPU usage sangat tinggi ({usage}%). Performa sistem mungkin terdampak."
        suggestions = ["Tutup aplikasi yang tidak dibutuhkan", "Cek Task Manager untuk proses berat"]

    cpu.status  = status
    cpu.message = message
    if suggestions:
        cpu.message += "\n\nSaran:\n" + "\n".join(f"- {s}" for s in suggestions)
    return cpu


def diagnose_gpu(gpu: Optional[GpuData]) -> Optional[GpuData]:
    if gpu is None:
        return None

    status      = STATUS_NORMAL
    message     = "GPU beroperasi dalam kondisi normal."
    suggestions: list[str] = []

    temp  = gpu.temperature_celsius
    usage = gpu.usage_percent

    if temp is not None:
        if temp >= 90:
            status  = STATUS_CRITICAL
            message = f"Temperatur GPU sangat tinggi ({temp}°C). Risiko kerusakan komponen."
            suggestions = [
                "Kurangi beban grafis segera",
                "Periksa sistem pendinginan GPU",
                "Bersihkan debu pada laptop",
            ]
        elif temp >= 80 and (usage is None or usage < 30):
            status  = STATUS_WARNING
            message = (
                f"GPU {temp}°C padahal utilisasi rendah. "
                "Kemungkinan masalah pada sistem pendinginan."
            )
            suggestions = ["Bersihkan kipas dan ventilasi", "Periksa thermal paste GPU"]
        elif temp >= 80:
            status  = STATUS_WARNING
            message = f"Temperatur GPU tinggi ({temp}°C)."
            suggestions = ["Pastikan ventilasi laptop tidak tertutup"]

    gpu.status  = status
    gpu.message = message
    if suggestions:
        gpu.message += "\n\nSaran:\n" + "\n".join(f"- {s}" for s in suggestions)
    return gpu


def diagnose_ram(ram: RamData) -> RamData:
    usage = ram.usage_percent

    if usage >= 90:
        ram.status  = STATUS_CRITICAL
        ram.message = (
            f"RAM hampir penuh ({usage}%, {ram.used_gb}/{ram.total_gb} GB). "
            "Sistem mungkin akan menggunakan virtual memory (swap) yang sangat memperlambat laptop."
            "\n\nSaran:\n- Tutup aplikasi yang tidak dibutuhkan"
            "\n- Restart aplikasi berat\n- Pertimbangkan upgrade RAM"
        )
    elif usage >= 75:
        ram.status  = STATUS_WARNING
        ram.message = (
            f"Penggunaan RAM cukup tinggi ({usage}%, {ram.used_gb}/{ram.total_gb} GB). "
            "Performa bisa terdampak jika dibuka lebih banyak aplikasi."
            "\n\nSaran:\n- Tutup tab browser yang tidak dipakai"
            "\n- Periksa aplikasi yang berjalan di background"
        )
    else:
        ram.status  = STATUS_NORMAL
        ram.message = (
            f"RAM dalam kondisi baik ({usage}%, tersedia {ram.available_gb} GB)."
        )
    return ram


def diagnose_disk(disk: DiskData) -> DiskData:
    usage = disk.usage_percent

    if usage >= 95:
        disk.status  = STATUS_CRITICAL
        disk.message = (
            f"Storage hampir penuh ({usage}%, {disk.free_gb} GB tersisa). "
            "Windows membutuhkan ruang kosong untuk beroperasi normal."
            "\n\nSaran:\n- Hapus file tidak penting segera"
            "\n- Jalankan Disk Cleanup\n- Pindahkan file besar ke external storage"
        )
    elif usage >= 85:
        disk.status  = STATUS_WARNING
        disk.message = (
            f"Storage mulai penuh ({usage}%, {disk.free_gb} GB tersisa)."
            "\n\nSaran:\n- Jalankan Disk Cleanup"
            "\n- Hapus file sementara\n- Pindahkan file yang jarang dipakai"
        )
    else:
        disk.status  = STATUS_NORMAL
        disk.message = f"Storage dalam kondisi normal ({usage}%, {disk.free_gb} GB tersisa)."
    return disk


def diagnose_battery(battery: Optional[BatteryData]) -> Optional[BatteryData]:
    if battery is None:
        return None

    pct = battery.percentage

    if battery.is_charging:
        battery.status  = STATUS_NORMAL
        battery.message = f"Sedang mengisi daya ({pct:.0f}%)."
        # Tambahkan info wear level jika tersedia
        if battery.wear_level_percent is not None:
            battery.message += f" Kapasitas baterai: {battery.wear_level_percent:.1f}% dari kapasitas pabrik."
        return battery

    if pct <= 10:
        battery.status  = STATUS_CRITICAL
        battery.message = f"Baterai kritis ({pct:.0f}%). Segera colokkan charger."
    elif pct <= 20:
        battery.status  = STATUS_WARNING
        battery.message = f"Baterai rendah ({pct:.0f}%). Disarankan segera mengisi daya."
    else:
        battery.status  = STATUS_NORMAL
        battery.message = f"Baterai normal ({pct:.0f}%)."

    # Diagnosa wear level — ini insight yang tidak ada di Task Manager
    if battery.wear_level_percent is not None:
        wear = battery.wear_level_percent
        if wear < 50:
            battery.status  = STATUS_CRITICAL
            battery.message += (
                f"\n\nKapasitas baterai sangat rendah ({wear:.1f}% dari kapasitas pabrik). "
                "Baterai sudah sangat aus dan perlu diganti segera."
            )
        elif wear < 70:
            # Jangan override CRITICAL dari level charge
            if battery.status == STATUS_NORMAL:
                battery.status = STATUS_WARNING
            battery.message += (
                f"\n\nKapasitas baterai menurun ({wear:.1f}% dari kapasitas pabrik). "
                "Pertimbangkan penggantian baterai dalam waktu dekat."
            )
        else:
            battery.message += f" Kapasitas: {wear:.1f}% dari pabrik."

    if battery.cycle_count is not None:
        battery.message += f" Siklus charge: {battery.cycle_count}x."

    if battery.time_remaining_minutes is not None:
        hours   = int(battery.time_remaining_minutes // 60)
        minutes = int(battery.time_remaining_minutes % 60)
        battery.message += f" Estimasi sisa waktu: {hours}j {minutes}m."

    return battery


def diagnose_network(network: NetworkData) -> NetworkData:
    dl = network.download_speed_kbps
    ul = network.upload_speed_kbps
    network.status  = STATUS_NORMAL
    network.message = f"Jaringan aktif. ↓{dl:.1f} KB/s  ↑{ul:.1f} KB/s"
    return network


def diagnose_smart(smart: Optional[SmartDiskData]) -> Optional[SmartDiskData]:
    """
    Diagnosa kesehatan disk berdasarkan SMART attributes.
    Ini pembeda utama vs Task Manager — mendeteksi kegagalan disk sebelum terjadi.
    """
    if smart is None:
        return None

    status      = STATUS_NORMAL
    message     = "Disk dalam kondisi sehat."
    suggestions: list[str] = []

    # Health status dari SMART
    if smart.health_status == "Failed":
        status  = STATUS_CRITICAL
        message = "SMART mendeteksi kegagalan disk. Risiko kehilangan data sangat tinggi."
        suggestions = [
            "Backup semua data segera",
            "Ganti disk sesegera mungkin",
            "Jangan simpan data penting baru di disk ini",
        ]
    elif smart.health_status == "Warning":
        status  = STATUS_WARNING
        message = "SMART mendeteksi kondisi disk yang perlu diperhatikan."

    # Reallocated sectors — tanda kerusakan fisik
    if smart.reallocated_sectors is not None and smart.reallocated_sectors > 0:
        if smart.reallocated_sectors > 50:
            status  = STATUS_CRITICAL
            message = (
                f"Disk memiliki {smart.reallocated_sectors} sektor rusak yang di-realokasi. "
                "Kerusakan fisik signifikan — backup data segera."
            )
            suggestions = ["Backup data segera", "Ganti disk sesegera mungkin"]
        elif smart.reallocated_sectors > 5:
            if status == STATUS_NORMAL:
                status = STATUS_WARNING
            message = (
                f"Disk memiliki {smart.reallocated_sectors} sektor rusak. "
                "Tanda awal kerusakan fisik — pantau dan pertimbangkan penggantian."
            )
            suggestions.append("Backup data secara rutin")
            suggestions.append("Pantau jumlah bad sector secara berkala")
        else:
            # 1–5 sector — mungkin masih aman tapi perlu dipantau
            if status == STATUS_NORMAL:
                message = f"Disk memiliki {smart.reallocated_sectors} sektor rusak (minor). Pantau secara berkala."

    # Pending sectors
    if smart.pending_sectors and smart.pending_sectors > 0:
        if status == STATUS_NORMAL:
            status = STATUS_WARNING
        message += f" {smart.pending_sectors} sektor menunggu pemeriksaan."
        suggestions.append("Jalankan disk check (chkdsk /r) untuk memeriksa sektor pending")

    # Uncorrectable sectors
    if smart.uncorrectable_sectors and smart.uncorrectable_sectors > 0:
        status  = STATUS_CRITICAL
        message += f" {smart.uncorrectable_sectors} sektor tidak dapat diperbaiki."
        suggestions.append("Backup data dan ganti disk segera")

    # Suhu disk
    if smart.temperature_celsius is not None:
        if smart.temperature_celsius >= 60:
            if status == STATUS_NORMAL:
                status = STATUS_WARNING
            message += f" Suhu disk tinggi ({smart.temperature_celsius}°C)."
            suggestions.append("Pastikan sirkulasi udara di dalam laptop memadai")
        elif smart.temperature_celsius >= 50:
            message += f" Suhu disk {smart.temperature_celsius}°C (normal)."

    # Power-on hours — indikator usia disk
    if smart.power_on_hours is not None:
        years = smart.power_on_hours / 8760  # jam per tahun
        if smart.power_on_hours > 40000:  # ~4.5 tahun non-stop
            if status == STATUS_NORMAL:
                status = STATUS_WARNING
            message += f" Disk sudah menyala {smart.power_on_hours:,} jam ({years:.1f} tahun) — pertimbangkan penggantian preventif."
        elif smart.power_on_hours > 20000:
            message += f" Disk sudah digunakan {smart.power_on_hours:,} jam."

    # Info tambahan
    disk_type = "SSD" if smart.is_ssd else "HDD"
    message = f"[{disk_type}] {smart.model}: " + message

    smart.status  = status
    smart.message = message
    if suggestions:
        smart.message += "\n\nSaran:\n" + "\n".join(f"- {s}" for s in suggestions)

    return smart


# ── health score ──────────────────────────────────────────────────────────────

def _component_score(status: str) -> int:
    return {STATUS_NORMAL: 100, STATUS_WARNING: 60, STATUS_CRITICAL: 20}.get(status, 50)


def calculate_health_score(snapshot: HardwareSnapshot) -> int:
    """
    Weighted health score 0-100.

    Weights (harus terdokumentasi):
      CPU  30%  — komponen paling berdampak pada performa & umur laptop
      RAM  15%  — bottleneck langsung terhadap responsivitas
      Disk 15%  — storage penuh = crash OS
      SMART 15% — kesehatan fisik disk (bad sector dll) — insight unik vs Task Manager
      Batt 15%  — kesehatan baterai = portabilitas
      GPU  10%  — penting untuk workload grafis
    """
    cpu_score   = _component_score(snapshot.cpu.status)
    ram_score   = _component_score(snapshot.ram.status)
    disk_score  = _component_score(snapshot.disk.status)
    batt_score  = _component_score(snapshot.battery.status) if snapshot.battery else 100
    gpu_score   = _component_score(snapshot.gpu.status)     if snapshot.gpu     else 100
    smart_score = _component_score(snapshot.smart.status)   if snapshot.smart   else 100

    score = (
        cpu_score   * 0.30
        + ram_score   * 0.15
        + disk_score  * 0.15
        + smart_score * 0.15
        + batt_score  * 0.15
        + gpu_score   * 0.10
    )
    return round(score)


# ── main entry ────────────────────────────────────────────────────────────────

def run_diagnostics(snapshot: HardwareSnapshot) -> HardwareSnapshot:
    """Apply all diagnostic rules to a snapshot in-place and return it."""
    snapshot.cpu     = diagnose_cpu(snapshot.cpu)
    snapshot.gpu     = diagnose_gpu(snapshot.gpu)
    snapshot.ram     = diagnose_ram(snapshot.ram)
    snapshot.disk    = diagnose_disk(snapshot.disk)
    snapshot.smart   = diagnose_smart(snapshot.smart)
    snapshot.battery = diagnose_battery(snapshot.battery)
    snapshot.network = diagnose_network(snapshot.network)
    snapshot.health_score = calculate_health_score(snapshot)
    return snapshot
