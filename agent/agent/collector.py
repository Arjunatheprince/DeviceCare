"""
Hardware sensor collector — Windows.
Reads CPU, GPU, RAM, Disk, Battery, Network via psutil + WMI.
"""
import platform
import socket
import time
from datetime import datetime, timezone
from typing import Optional

import psutil

from .models import (
    BatteryData, CpuData, DeviceInfo, DiskData,
    GpuData, HardwareSnapshot, NetworkData, RamData,
    SmartDiskData, TopProcesses, ProcessInfo,
)


# ── internal helpers ──────────────────────────────────────────────────────────

def _get_cpu_temperature() -> Optional[float]:
    """
    Windows exposes CPU temperature through WMI (MSAcpi_ThermalZoneTemperature)
    or Open Hardware Monitor / LibreHardwareMonitor COM interface.

    We try three paths in order:
    1. psutil sensors_temperatures (available on Linux, sometimes on Windows)
    2. WMI MSAcpi_ThermalZoneTemperature
    3. OpenHardwareMonitor WMI bridge (requires OHM to be running)
    """
    # path 1: psutil (usually None on Windows without drivers)
    try:
        temps = psutil.sensors_temperatures()
        if temps:
            for key in ("coretemp", "k10temp", "acpitz", "cpu_thermal"):
                if key in temps:
                    readings = temps[key]
                    if readings:
                        return round(sum(r.current for r in readings) / len(readings), 1)
            # take whatever is first
            first_group = next(iter(temps.values()))
            if first_group:
                return round(first_group[0].current, 1)
    except Exception:
        pass

    # path 2: WMI ACPI thermal zone (raw value is in tenths of Kelvin)
    try:
        import wmi  # type: ignore
        w = wmi.WMI(namespace="root\\wmi")
        zones = w.MSAcpi_ThermalZoneTemperature()
        if zones:
            temps_c = [(z.CurrentTemperature / 10.0) - 273.15 for z in zones]
            return round(sum(temps_c) / len(temps_c), 1)
    except Exception:
        pass

    # path 3: OpenHardwareMonitor WMI bridge
    try:
        import wmi  # type: ignore
        w = wmi.WMI(namespace="root\\OpenHardwareMonitor")
        sensors = w.Sensor()
        cpu_temps = [
            float(s.Value)
            for s in sensors
            if s.SensorType == "Temperature" and "CPU" in s.Name
        ]
        if cpu_temps:
            return round(sum(cpu_temps) / len(cpu_temps), 1)
    except Exception:
        pass

    return None


def _get_device_info() -> DeviceInfo:
    hostname = socket.gethostname()
    os_name = platform.system()
    os_version = platform.version()

    manufacturer = "Unknown"
    model = "Unknown"
    cpu_name = "Unknown"

    try:
        import wmi  # type: ignore
        w = wmi.WMI()
        cs = w.Win32_ComputerSystem()
        if cs:
            manufacturer = cs[0].Manufacturer or "Unknown"
            model = cs[0].Model or "Unknown"
        proc = w.Win32_Processor()
        if proc:
            cpu_name = proc[0].Name.strip() or "Unknown"
    except Exception:
        try:
            import subprocess
            result = subprocess.run(
                ["wmic", "computersystem", "get", "Manufacturer,Model", "/value"],
                capture_output=True, text=True, timeout=5,
            )
            for line in result.stdout.splitlines():
                if line.startswith("Manufacturer="):
                    manufacturer = line.split("=", 1)[1].strip() or "Unknown"
                elif line.startswith("Model="):
                    model = line.split("=", 1)[1].strip() or "Unknown"
        except Exception:
            pass

    ram = psutil.virtual_memory()
    disk = psutil.disk_usage("/")

    return DeviceInfo(
        hostname=hostname,
        os=os_name,
        os_version=os_version,
        manufacturer=manufacturer,
        model=model,
        cpu_name=cpu_name,
        ram_total_gb=round(ram.total / (1024 ** 3), 1),
        disk_total_gb=round(disk.total / (1024 ** 3), 1),
    )


# ── public read functions ─────────────────────────────────────────────────────

def read_cpu() -> CpuData:
    usage = psutil.cpu_percent(interval=0.5)
    freq = psutil.cpu_freq()
    freq_mhz = round(freq.current, 1) if freq else 0.0
    cores = psutil.cpu_count(logical=False) or 1
    threads = psutil.cpu_count(logical=True) or 1
    temp = _get_cpu_temperature()

    return CpuData(
        usage_percent=usage,
        temperature_celsius=temp,
        frequency_mhz=freq_mhz,
        core_count=cores,
        thread_count=threads,
    )


def read_gpu() -> Optional[GpuData]:
    """Try nvidia-smi first, then WMI for basic info."""
    # nvidia-smi path
    try:
        import subprocess
        result = subprocess.run(
            [
                "nvidia-smi",
                "--query-gpu=name,utilization.gpu,temperature.gpu,memory.used,memory.total",
                "--format=csv,noheader,nounits",
            ],
            capture_output=True, text=True, timeout=5,
        )
        if result.returncode == 0 and result.stdout.strip():
            parts = [p.strip() for p in result.stdout.strip().split(",")]
            if len(parts) >= 5:
                return GpuData(
                    name=parts[0],
                    usage_percent=float(parts[1]),
                    temperature_celsius=float(parts[2]),
                    memory_used_mb=float(parts[3]),
                    memory_total_mb=float(parts[4]),
                )
    except Exception:
        pass

    # AMD / generic via WMI
    try:
        import wmi  # type: ignore
        w = wmi.WMI()
        gpus = w.Win32_VideoController()
        if gpus:
            g = gpus[0]
            return GpuData(
                name=g.Caption or "Unknown GPU",
                usage_percent=None,
                temperature_celsius=None,
                memory_used_mb=None,
                memory_total_mb=(
                    round(int(g.AdapterRAM) / (1024 ** 2), 1)
                    if g.AdapterRAM else None
                ),
            )
    except Exception:
        pass

    return None


def read_ram() -> RamData:
    m = psutil.virtual_memory()
    return RamData(
        total_gb=round(m.total / (1024 ** 3), 1),
        used_gb=round(m.used / (1024 ** 3), 1),
        available_gb=round(m.available / (1024 ** 3), 1),
        usage_percent=m.percent,
    )


def read_disk(path: str = "C:\\") -> DiskData:
    usage = psutil.disk_usage(path)

    # read/write speed via disk_io_counters delta
    io1 = psutil.disk_io_counters()
    time.sleep(0.5)
    io2 = psutil.disk_io_counters()

    read_speed = write_speed = None
    if io1 and io2:
        read_speed = round((io2.read_bytes - io1.read_bytes) / (0.5 * 1024 * 1024), 2)
        write_speed = round((io2.write_bytes - io1.write_bytes) / (0.5 * 1024 * 1024), 2)

    return DiskData(
        total_gb=round(usage.total / (1024 ** 3), 1),
        used_gb=round(usage.used / (1024 ** 3), 1),
        free_gb=round(usage.free / (1024 ** 3), 1),
        usage_percent=usage.percent,
        read_speed_mbps=read_speed,
        write_speed_mbps=write_speed,
    )


def read_battery() -> Optional[BatteryData]:
    batt = psutil.sensors_battery()
    if batt is None:
        return None
    time_left = None
    if batt.secsleft not in (psutil.POWER_TIME_UNLIMITED, psutil.POWER_TIME_UNKNOWN):
        time_left = round(batt.secsleft / 60, 1)

    # Baca battery wear level via WMI (Win32_Battery + BatteryFullChargedCapacity)
    design_cap = None
    full_cap    = None
    wear_level  = None
    cycle_count = None

    try:
        import wmi  # type: ignore
        w = wmi.WMI()

        # Design capacity & full charge capacity dari BatteryStaticData / BatteryFullChargedCapacity
        w2 = wmi.WMI(namespace="root\\wmi")

        # Full charged capacity (mWh)
        try:
            for item in w2.BatteryFullChargedCapacity():
                full_cap = int(item.FullChargedCapacity)
                break
        except Exception:
            pass

        # Design capacity dari BatteryStaticData
        try:
            for item in w2.BatteryStaticData():
                design_cap = int(item.DesignedCapacity)
                break
        except Exception:
            pass

        # Cycle count dari BatteryCycleCount (tidak selalu tersedia)
        try:
            for item in w2.BatteryCycleCount():
                cycle_count = int(item.CycleCount)
                break
        except Exception:
            pass

        # Fallback: design capacity dari Win32_Battery
        if design_cap is None:
            try:
                for b in w.Win32_Battery():
                    if hasattr(b, 'DesignCapacity') and b.DesignCapacity:
                        design_cap = int(b.DesignCapacity)
                    break
            except Exception:
                pass

        if design_cap and full_cap and design_cap > 0:
            wear_level = round((full_cap / design_cap) * 100, 1)

    except Exception:
        pass

    return BatteryData(
        percentage=round(batt.percent, 1),
        is_charging=batt.power_plugged,
        time_remaining_minutes=time_left,
        design_capacity_mwh=design_cap,
        full_charge_capacity_mwh=full_cap,
        wear_level_percent=wear_level,
        cycle_count=cycle_count,
    )


def read_smart_disk() -> Optional[SmartDiskData]:
    """
    Baca data SMART (Self-Monitoring, Analysis and Reporting Technology) dari disk.

    Strategi:
    1. wmi.WMI(namespace="root\\wmi") — MSStorageDriver_ATAPISmartData (raw SMART attributes)
    2. Win32_DiskDrive — info dasar disk (model, serial, ukuran)
    3. subprocess smartctl (jika smartmontools terinstall) — paling lengkap
    4. Fallback: kembalikan info minimal dari Win32_DiskDrive saja
    """
    import subprocess

    model        = "Unknown"
    serial       = None
    health       = "Unknown"
    temp         = None
    reallocated  = None
    pending      = None
    uncorrectable= None
    power_hours  = None
    power_cycles = None
    rotation     = None
    is_ssd       = False

    # ── Path 1: smartctl (paling lengkap, butuh smartmontools) ───────────────
    try:
        result = subprocess.run(
            ["smartctl", "-A", "-i", "-H", "/dev/sda", "--json"],
            capture_output=True, text=True, timeout=8,
        )
        if result.returncode in (0, 4) and result.stdout:
            import json as _json
            data = _json.loads(result.stdout)

            # Info disk
            model  = data.get("model_name", "Unknown")
            serial = data.get("serial_number")

            # Health status
            smart_status = data.get("smart_status", {})
            health = "Healthy" if smart_status.get("passed") else "Failed"

            # Temperature
            temp_obj = data.get("temperature", {})
            if "current" in temp_obj:
                temp = float(temp_obj["current"])

            # SMART attributes
            for attr in data.get("ata_smart_attributes", {}).get("table", []):
                aid  = attr.get("id")
                raw  = attr.get("raw", {}).get("value", 0)
                if aid == 5:   reallocated   = int(raw)
                elif aid == 197: pending      = int(raw)
                elif aid == 198: uncorrectable= int(raw)
                elif aid == 9:   power_hours  = int(raw)
                elif aid == 12:  power_cycles = int(raw)
                elif aid == 194 and temp is None:
                    temp = float(raw)

            # SSD detection
            rotation_rate = data.get("rotation_rate", -1)
            if rotation_rate == 0:
                is_ssd   = True
                rotation = 0
            elif rotation_rate > 0:
                rotation = rotation_rate

            return SmartDiskData(
                model=model, serial=serial, health_status=health,
                temperature_celsius=temp, reallocated_sectors=reallocated,
                pending_sectors=pending, uncorrectable_sectors=uncorrectable,
                power_on_hours=power_hours, power_cycle_count=power_cycles,
                rotation_rate=rotation, is_ssd=is_ssd,
            )
    except FileNotFoundError:
        pass  # smartctl tidak terinstall
    except Exception:
        pass

    # ── Path 2: WMI Win32_DiskDrive + MSStorageDriver ────────────────────────
    try:
        import wmi  # type: ignore
        w  = wmi.WMI()
        w2 = wmi.WMI(namespace="root\\wmi")

        drives = w.Win32_DiskDrive()
        if drives:
            d = drives[0]
            model  = (d.Model or "Unknown").strip()
            serial = (d.SerialNumber or "").strip() or None

            # Deteksi SSD dari nama model
            is_ssd = any(kw in model.upper() for kw in ("SSD", "NVME", "M.2", "SOLID"))
            if d.MediaType:
                is_ssd = "SSD" in d.MediaType.upper() or "Solid" in d.MediaType

        # SMART attributes via MSStorageDriver_ATAPISmartData
        try:
            smart_data = w2.MSStorageDriver_ATAPISmartData()
            if smart_data:
                sd = smart_data[0]
                raw_attrs = bytes(sd.VendorSpecific) if sd.VendorSpecific else b''

                # Parse 12 attributes (masing-masing 12 byte)
                for i in range(0, min(len(raw_attrs), 12 * 30), 12):
                    chunk  = raw_attrs[i:i+12]
                    if len(chunk) < 12:
                        break
                    attr_id = chunk[0]
                    # Raw value: bytes 5-10 (little endian)
                    raw_val = int.from_bytes(chunk[5:11], 'little')

                    if attr_id == 5:    reallocated   = raw_val
                    elif attr_id == 194: temp          = float(raw_val & 0xFF)
                    elif attr_id == 190: temp          = temp or float(raw_val & 0xFF)
                    elif attr_id == 197: pending       = raw_val
                    elif attr_id == 198: uncorrectable = raw_val
                    elif attr_id == 9:   power_hours   = raw_val
                    elif attr_id == 12:  power_cycles  = raw_val
        except Exception:
            pass

        # Health status dari Win32_DiskDrive.Status
        try:
            if drives:
                status_map = {"OK": "Healthy", "Error": "Failed",
                              "Degraded": "Warning", "Unknown": "Unknown"}
                health = status_map.get(drives[0].Status or "Unknown", "Unknown")
        except Exception:
            pass

        return SmartDiskData(
            model=model, serial=serial, health_status=health,
            temperature_celsius=temp, reallocated_sectors=reallocated,
            pending_sectors=pending, uncorrectable_sectors=uncorrectable,
            power_on_hours=power_hours, power_cycle_count=power_cycles,
            rotation_rate=rotation, is_ssd=is_ssd,
        )

    except Exception:
        pass

    # ── Fallback: kembalikan None jika semua path gagal ──────────────────────
    return None


def read_top_processes(top_n: int = 5) -> TopProcesses:
    """
    Baca N proses teratas berdasarkan CPU dan RAM.
    Menjawab pertanyaan 'kenapa CPU/RAM tinggi?' — tidak tersedia di dashboard biasa.
    """
    procs = []
    for p in psutil.process_iter(['pid', 'name', 'cpu_percent', 'memory_info', 'memory_percent', 'status']):
        try:
            info = p.info
            if info['cpu_percent'] is None:
                continue
            mem_mb = round(info['memory_info'].rss / (1024 ** 2), 1) if info['memory_info'] else 0
            procs.append(ProcessInfo(
                pid=info['pid'],
                name=info['name'] or 'Unknown',
                cpu_percent=round(info['cpu_percent'], 1),
                memory_mb=mem_mb,
                memory_percent=round(info['memory_percent'] or 0, 1),
                status=info['status'] or 'unknown',
            ))
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            continue

    # Urutkan dan ambil top N untuk CPU dan RAM terpisah
    by_cpu = sorted(procs, key=lambda p: p.cpu_percent, reverse=True)[:top_n]
    by_mem = sorted(procs, key=lambda p: p.memory_mb, reverse=True)[:top_n]

    return TopProcesses(by_cpu=by_cpu, by_memory=by_mem)


def read_network() -> NetworkData:
    """Return cumulative bytes and a 1-second speed sample."""
    n1 = psutil.net_io_counters()
    time.sleep(1)
    n2 = psutil.net_io_counters()

    upload_kbps = round((n2.bytes_sent - n1.bytes_sent) / 1024, 2)
    download_kbps = round((n2.bytes_recv - n1.bytes_recv) / 1024, 2)

    return NetworkData(
        bytes_sent_mb=round(n2.bytes_sent / (1024 ** 2), 2),
        bytes_recv_mb=round(n2.bytes_recv / (1024 ** 2), 2),
        upload_speed_kbps=upload_kbps,
        download_speed_kbps=download_kbps,
    )


# ── snapshot ──────────────────────────────────────────────────────────────────

_device_info_cache: Optional[DeviceInfo] = None


def collect_snapshot() -> HardwareSnapshot:
    """Collect a full hardware snapshot. Device info is cached after first call."""
    global _device_info_cache
    if _device_info_cache is None:
        _device_info_cache = _get_device_info()

    cpu     = read_cpu()
    gpu     = read_gpu()
    ram     = read_ram()
    disk    = read_disk()
    smart   = read_smart_disk()
    battery = read_battery()
    network = read_network()
    top_processes = read_top_processes()

    return HardwareSnapshot(
        timestamp=datetime.now(timezone.utc).isoformat(),
        device=_device_info_cache,
        cpu=cpu,
        gpu=gpu,
        ram=ram,
        disk=disk,
        smart=smart,
        battery=battery,
        network=network,
        top_processes=top_processes,
    )
