from dataclasses import dataclass
from typing import Optional


@dataclass
class CpuData:
    usage_percent: float
    temperature_celsius: Optional[float]
    frequency_mhz: float
    core_count: int
    thread_count: int
    status: str = "UNKNOWN"
    message: str = ""


@dataclass
class GpuData:
    name: str
    usage_percent: Optional[float]
    temperature_celsius: Optional[float]
    memory_used_mb: Optional[float]
    memory_total_mb: Optional[float]
    status: str = "UNKNOWN"
    message: str = ""


@dataclass
class RamData:
    total_gb: float
    used_gb: float
    available_gb: float
    usage_percent: float
    status: str = "UNKNOWN"
    message: str = ""


@dataclass
class DiskData:
    total_gb: float
    used_gb: float
    free_gb: float
    usage_percent: float
    read_speed_mbps: Optional[float]
    write_speed_mbps: Optional[float]
    status: str = "UNKNOWN"
    message: str = ""


@dataclass
class SmartDiskData:
    """
    Data kesehatan disk dari SMART (Self-Monitoring, Analysis and Reporting Technology).
    Insight ini tidak tersedia di Task Manager — hanya aplikasi monitoring khusus.
    """
    model: str                              # nama model disk
    serial: Optional[str]                   # serial number
    health_status: str                      # "Healthy" / "Warning" / "Failed" / "Unknown"
    temperature_celsius: Optional[float]    # suhu disk
    reallocated_sectors: Optional[int]      # sektor rusak yang di-realokasi (>0 = tanda kerusakan)
    pending_sectors: Optional[int]          # sektor menunggu realokasi
    uncorrectable_sectors: Optional[int]    # sektor tidak bisa diperbaiki
    power_on_hours: Optional[int]           # total jam disk menyala
    power_cycle_count: Optional[int]        # berapa kali disk dinyalakan/dimatikan
    rotation_rate: Optional[int]            # RPM (0 = SSD)
    is_ssd: bool = False                    # True jika SSD
    status: str = "UNKNOWN"
    message: str = ""


@dataclass
class BatteryData:
    percentage: float
    is_charging: bool
    time_remaining_minutes: Optional[float]
    # Wear level: kapasitas saat ini vs kapasitas pabrik (0–100%)
    # 100% = baru, 70% = sudah aus 30%
    design_capacity_mwh: Optional[int]       # kapasitas desain dari pabrik (mWh)
    full_charge_capacity_mwh: Optional[int]  # kapasitas charge penuh saat ini (mWh)
    wear_level_percent: Optional[float]      # (full_charge / design) * 100
    cycle_count: Optional[int]               # jumlah siklus charge (jika tersedia)
    status: str = "UNKNOWN"
    message: str = ""


@dataclass
class ProcessInfo:
    """Satu proses yang berjalan di sistem."""
    pid: int
    name: str
    cpu_percent: float
    memory_mb: float
    memory_percent: float
    status: str


@dataclass
class NetworkData:
    bytes_sent_mb: float
    bytes_recv_mb: float
    upload_speed_kbps: float
    download_speed_kbps: float
    status: str = "UNKNOWN"
    message: str = ""


@dataclass
class TopProcesses:
    """5 proses teratas berdasarkan CPU dan RAM."""
    by_cpu: list  # list of ProcessInfo
    by_memory: list  # list of ProcessInfo

@dataclass
class DeviceInfo:
    hostname: str
    os: str
    os_version: str
    manufacturer: str
    model: str
    cpu_name: str
    ram_total_gb: float
    disk_total_gb: float


@dataclass
class HardwareSnapshot:
    timestamp: str
    device: DeviceInfo
    cpu: CpuData
    gpu: Optional[GpuData]
    ram: RamData
    disk: DiskData
    smart: Optional['SmartDiskData']
    battery: Optional[BatteryData]
    network: NetworkData
    top_processes: Optional['TopProcesses'] = None
    health_score: int = 0

    def to_dict(self) -> dict:
        import dataclasses
        return dataclasses.asdict(self)
