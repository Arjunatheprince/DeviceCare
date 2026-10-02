"""
DeviceCare Agent — HTTP sender.

Kirim snapshot hardware ke Laravel backend.
Handles registration, retry, dan error logging.
"""
import hashlib
import json
import logging
import socket
import time
from typing import Optional

try:
    import requests
except ImportError:
    requests = None  # type: ignore

from .models import HardwareSnapshot

logger = logging.getLogger(__name__)

# ── konfigurasi (bisa di-override via .env atau argumen CLI) ──────────────────
DEFAULT_BASE_URL = "http://localhost:8000"
TIMEOUT_SECONDS  = 5
MAX_RETRIES      = 3
RETRY_DELAY      = 2   # detik


class DeviceCareClient:
    """
    Client HTTP untuk berkomunikasi dengan Laravel backend.

    Usage:
        client = DeviceCareClient("http://localhost:8000")
        client.register(snapshot)     # panggil sekali saat startup
        client.send(snapshot)         # panggil setiap interval
    """

    def __init__(self, base_url: str = DEFAULT_BASE_URL):
        if requests is None:
            raise ImportError("requests tidak terinstall. Jalankan: pip install requests")

        self.base_url  = base_url.rstrip("/")
        self.device_id: Optional[int] = None
        self._session  = requests.Session()
        self._session.headers.update({
            "Content-Type": "application/json",
            "Accept":       "application/json",
            "X-Agent":      "DeviceCare-Agent/1.0",
        })

    # ── helpers ───────────────────────────────────────────────────────────────

    def _device_key(self, snapshot: HardwareSnapshot) -> str:
        """
        Kunci unik per mesin: hash dari hostname + CPU name.
        Tidak menggunakan MAC address agar tidak ada masalah permission.
        """
        raw = f"{snapshot.device.hostname}:{snapshot.device.cpu_name}"
        return hashlib.sha256(raw.encode()).hexdigest()[:32]

    def _get(self, path: str) -> Optional[dict]:
        url = f"{self.base_url}/api/{path.lstrip('/')}"
        try:
            resp = self._session.get(url, timeout=TIMEOUT_SECONDS)
            resp.raise_for_status()
            return resp.json()
        except Exception as e:
            logger.debug(f"[sender] GET gagal {url}: {e}")
            return None

    def _post(self, path: str, payload: dict) -> Optional[dict]:
        """POST dengan retry sederhana."""
        url = f"{self.base_url}/api/{path.lstrip('/')}"
        for attempt in range(1, MAX_RETRIES + 1):
            try:
                resp = self._session.post(url, json=payload, timeout=TIMEOUT_SECONDS)
                resp.raise_for_status()
                return resp.json()
            except requests.exceptions.ConnectionError:
                logger.warning(f"[sender] Koneksi gagal ke {url} (attempt {attempt}/{MAX_RETRIES})")
            except requests.exceptions.Timeout:
                logger.warning(f"[sender] Timeout ke {url} (attempt {attempt}/{MAX_RETRIES})")
            except requests.exceptions.HTTPError as e:
                logger.error(f"[sender] HTTP error {e.response.status_code}: {e.response.text[:200]}")
                return None  # jangan retry untuk 4xx
            except Exception as e:
                logger.error(f"[sender] Error tak terduga: {e}")
                return None

            if attempt < MAX_RETRIES:
                time.sleep(RETRY_DELAY)

        logger.error(f"[sender] Semua {MAX_RETRIES} percobaan gagal untuk {url}")
        return None

    # ── public API ────────────────────────────────────────────────────────────

    def register(self, snapshot: HardwareSnapshot) -> bool:
        """
        Register atau update device info ke backend.
        Simpan device_id yang dikembalikan untuk request berikutnya.
        Panggil sekali saat agent startup.
        """
        payload = {
            "device_key":       self._device_key(snapshot),
            "device_name":      snapshot.device.hostname,
            "manufacturer":     snapshot.device.manufacturer,
            "model":            snapshot.device.model,
            "cpu":              snapshot.device.cpu_name,
            "ram_total_gb":     snapshot.device.ram_total_gb,
            "disk_total_gb":    snapshot.device.disk_total_gb,
            "operating_system": snapshot.device.os,
            "os_version":       snapshot.device.os_version,
            "agent_version":    "1.0.0",
        }

        result = self._post("devices/register", payload)
        if result and result.get("success"):
            self.device_id = result["device_id"]
            logger.info(f"[sender] Device registered — ID: {self.device_id}")
            return True

        logger.error("[sender] Registrasi device gagal.")
        return False

    def send(self, snapshot: HardwareSnapshot) -> bool:
        """
        Kirim satu snapshot sensor ke backend.
        Harus panggil register() terlebih dahulu.
        """
        if self.device_id is None:
            logger.warning("[sender] device_id belum ada — jalankan register() dulu.")
            return False

        def _data(obj, field, default=None):
            return getattr(obj, field, default) if obj else default

        def _parse_msg(msg: str) -> tuple:
            """Pisahkan pesan dan saran dari format '\n\nSaran:\n- x'."""
            if not msg:
                return "", []
            parts = msg.split("\n\nSaran:\n")
            body = parts[0].strip()
            sugg = [ln.lstrip("- ").strip() for ln in (parts[1].splitlines() if len(parts) > 1 else []) if ln.strip().startswith("-")]
            return body, sugg

        cpu_msg, cpu_sugg   = _parse_msg(snapshot.cpu.message)
        gpu_msg, gpu_sugg   = _parse_msg(_data(snapshot.gpu, "message") or "")
        ram_msg, ram_sugg   = _parse_msg(snapshot.ram.message)
        disk_msg, disk_sugg = _parse_msg(snapshot.disk.message)
        smart_msg, smart_sugg = _parse_msg(_data(snapshot.smart, "message") or "")
        batt_msg, batt_sugg = _parse_msg(_data(snapshot.battery, "message") or "")

        payload = {
            "timestamp":    snapshot.timestamp,
            "health_score": snapshot.health_score,

            "cpu": {
                "usage_percent":       snapshot.cpu.usage_percent,
                "temperature_celsius": snapshot.cpu.temperature_celsius,
                "frequency_mhz":       snapshot.cpu.frequency_mhz,
                "status":              snapshot.cpu.status,
                "message":             cpu_msg,
                "suggestions":         cpu_sugg,
            },
            "gpu": {
                "usage_percent":       _data(snapshot.gpu, "usage_percent"),
                "temperature_celsius": _data(snapshot.gpu, "temperature_celsius"),
                "memory_used_mb":      _data(snapshot.gpu, "memory_used_mb"),
                "status":              _data(snapshot.gpu, "status"),
                "message":             gpu_msg,
                "suggestions":         gpu_sugg,
            } if snapshot.gpu else None,

            "ram": {
                "usage_percent": snapshot.ram.usage_percent,
                "used_gb":       snapshot.ram.used_gb,
                "available_gb":  snapshot.ram.available_gb,
                "status":        snapshot.ram.status,
                "message":       ram_msg,
                "suggestions":   ram_sugg,
            },
            "disk": {
                "usage_percent":   snapshot.disk.usage_percent,
                "free_gb":         snapshot.disk.free_gb,
                "read_speed_mbps": snapshot.disk.read_speed_mbps,
                "write_speed_mbps":snapshot.disk.write_speed_mbps,
                "status":          snapshot.disk.status,
                "message":         disk_msg,
                "suggestions":     disk_sugg,
            },
            "smart": {
                "model":                  _data(snapshot.smart, "model"),
                "health_status":          _data(snapshot.smart, "health_status"),
                "temperature_celsius":    _data(snapshot.smart, "temperature_celsius"),
                "reallocated_sectors":    _data(snapshot.smart, "reallocated_sectors"),
                "pending_sectors":        _data(snapshot.smart, "pending_sectors"),
                "uncorrectable_sectors":  _data(snapshot.smart, "uncorrectable_sectors"),
                "power_on_hours":         _data(snapshot.smart, "power_on_hours"),
                "power_cycle_count":      _data(snapshot.smart, "power_cycle_count"),
                "is_ssd":                 _data(snapshot.smart, "is_ssd"),
                "status":                 _data(snapshot.smart, "status"),
                "message":                smart_msg,
                "suggestions":            smart_sugg,
            } if snapshot.smart else None,
            "battery": {
                "percentage":             _data(snapshot.battery, "percentage"),
                "is_charging":            _data(snapshot.battery, "is_charging"),
                "time_remaining_minutes": _data(snapshot.battery, "time_remaining_minutes"),
                "design_capacity_mwh":    _data(snapshot.battery, "design_capacity_mwh"),
                "full_charge_capacity_mwh": _data(snapshot.battery, "full_charge_capacity_mwh"),
                "wear_level_percent":     _data(snapshot.battery, "wear_level_percent"),
                "cycle_count":            _data(snapshot.battery, "cycle_count"),
                "status":                 _data(snapshot.battery, "status"),
                "message":                batt_msg,
                "suggestions":            batt_sugg,
            } if snapshot.battery else None,

            "network": {
                "upload_speed_kbps":   snapshot.network.upload_speed_kbps,
                "download_speed_kbps": snapshot.network.download_speed_kbps,
            },
            "top_processes": {
                "by_cpu": [
                    {"pid": p.pid, "name": p.name, "cpu_percent": p.cpu_percent,
                     "memory_mb": p.memory_mb, "memory_percent": p.memory_percent}
                    for p in (snapshot.top_processes.by_cpu if snapshot.top_processes else [])
                ],
                "by_memory": [
                    {"pid": p.pid, "name": p.name, "cpu_percent": p.cpu_percent,
                     "memory_mb": p.memory_mb, "memory_percent": p.memory_percent}
                    for p in (snapshot.top_processes.by_memory if snapshot.top_processes else [])
                ],
            } if snapshot.top_processes else None,
        }

        result = self._post(f"devices/{self.device_id}/readings", payload)
        if result and result.get("success"):
            logger.debug(f"[sender] Reading #{result.get('reading_id')} tersimpan.")
            # Kirim top processes ke endpoint terpisah (non-blocking)
            self._send_processes(snapshot)
            self.poll_commands(snapshot)
            return True

        return False

    def _send_processes(self, snapshot: HardwareSnapshot) -> None:
        """Kirim top process data ke endpoint terpisah. Gagal diam-diam."""
        if not snapshot.top_processes:
            return
        try:
            proc_payload = {
                "timestamp": snapshot.timestamp,
                "top_processes": {
                    "by_cpu": [
                        {"pid": p.pid, "name": p.name, "cpu_percent": p.cpu_percent,
                         "memory_mb": p.memory_mb, "memory_percent": p.memory_percent}
                        for p in snapshot.top_processes.by_cpu
                    ],
                    "by_memory": [
                        {"pid": p.pid, "name": p.name, "cpu_percent": p.cpu_percent,
                         "memory_mb": p.memory_mb, "memory_percent": p.memory_percent}
                        for p in snapshot.top_processes.by_memory
                    ],
                },
            }
            self._post(f"devices/{self.device_id}/processes", proc_payload)
        except Exception as e:
            logger.debug(f"[sender] Process data gagal dikirim: {e}")

    def poll_commands(self, snapshot: HardwareSnapshot) -> None:
        if self.device_id is None:
            return
        data = self._get(f"devices/{self.device_id}/commands/pending")
        command = (data or {}).get("data")
        if not command:
            return
        command_id = command.get("id")
        name = command.get("command")
        try:
            result = self._execute_command(name, snapshot)
            status = "completed" if result.get("ok") else "rejected"
        except Exception as e:
            status = "failed"
            result = {"ok": False, "error": str(e)}
        self._post(f"devices/{self.device_id}/commands/{command_id}/complete", {"status": status, "result": result})

    def _execute_command(self, name: str, snapshot: HardwareSnapshot) -> dict:
        if name == "collect_now":
            return {"ok": True, "message": "Agent aktif dan reading terbaru sudah dikirim."}
        if name == "list_processes":
            return {"ok": True, "top_processes": {"by_cpu": [p.__dict__ for p in snapshot.top_processes.by_cpu], "by_memory": [p.__dict__ for p in snapshot.top_processes.by_memory]} if snapshot.top_processes else None}
        if name == "cleanup_temp_dry_run":
            total = 0
            count = 0
            temp_dir = tempfile.gettempdir()
            for root, _, files in os.walk(temp_dir):
                for file in files:
                    try:
                        path = os.path.join(root, file)
                        total += os.path.getsize(path)
                        count += 1
                    except Exception:
                        pass
                    if count >= 5000:
                        break
                if count >= 5000:
                    break
            return {"ok": True, "temp_dir": temp_dir, "estimated_files": count, "estimated_mb": round(total / (1024 * 1024), 1), "dry_run": True}
        return {"ok": False, "error": "Command tidak diizinkan agent."}

    def close(self) -> None:
        self._session.close()
