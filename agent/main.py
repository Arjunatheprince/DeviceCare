"""
DeviceCare Agent — entry point.

Usage:
    python main.py                          # print sekali, pretty format
    python main.py --json                   # print sekali, raw JSON
    python main.py --loop                   # loop tiap 5 detik, pretty
    python main.py --loop --json            # loop tiap 5 detik, JSON
    python main.py --loop --send            # loop + kirim ke Laravel
    python main.py --loop --send --url http://localhost:8000
    python main.py --interval 1 --loop --send
"""
import argparse
import json
import logging
import sys
import time

from agent.collector import collect_snapshot
from agent.diagnostics import run_diagnostics

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
)
logger = logging.getLogger(__name__)

STATUS_COLORS = {
    "NORMAL":   "\033[92m",
    "WARNING":  "\033[93m",
    "CRITICAL": "\033[91m",
}
RESET = "\033[0m"
BOLD  = "\033[1m"


def colorize(status: str) -> str:
    color = STATUS_COLORS.get(status, "")
    return f"{color}{BOLD}{status}{RESET}"


def pretty_print(snapshot) -> None:
    d = snapshot

    print(f"\n{'═'*54}")
    print(f"  {BOLD}DEVICECARE — Hardware Health Monitor{RESET}")
    print(f"  {d.timestamp}")
    print(f"  {d.device.hostname}  |  {d.device.manufacturer} {d.device.model}")
    print(f"{'═'*54}")

    # CPU
    cpu = d.cpu
    temp_str = f"{cpu.temperature_celsius}°C" if cpu.temperature_celsius is not None else "N/A"
    print(f"\n  {BOLD}CPU{RESET}  {d.device.cpu_name}")
    print(f"  Usage       {cpu.usage_percent:.1f}%")
    print(f"  Temperature {temp_str}")
    print(f"  Clock       {cpu.frequency_mhz:.0f} MHz   Cores {cpu.core_count}C/{cpu.thread_count}T")
    print(f"  Status      {colorize(cpu.status)}")
    if cpu.message:
        first_line = cpu.message.splitlines()[0]
        print(f"              {first_line}")

    # GPU
    if d.gpu:
        gpu = d.gpu
        t = f"{gpu.temperature_celsius}°C" if gpu.temperature_celsius is not None else "N/A"
        u = f"{gpu.usage_percent:.1f}%" if gpu.usage_percent is not None else "N/A"
        print(f"\n  {BOLD}GPU{RESET}  {gpu.name}")
        print(f"  Usage {u}   Temperature {t}")
        print(f"  Status      {colorize(gpu.status)}")

    # RAM
    ram = d.ram
    print(f"\n  {BOLD}RAM{RESET}")
    print(f"  {ram.usage_percent:.1f}%  ({ram.used_gb}/{ram.total_gb} GB)   Available {ram.available_gb} GB")
    print(f"  Status      {colorize(ram.status)}")

    # Disk
    disk = d.disk
    print(f"\n  {BOLD}Storage{RESET}")
    print(f"  {disk.usage_percent:.1f}%  Free {disk.free_gb} GB / {disk.total_gb} GB")
    if disk.read_speed_mbps is not None:
        print(f"  R {disk.read_speed_mbps} MB/s   W {disk.write_speed_mbps} MB/s")
    print(f"  Status      {colorize(disk.status)}")

    # Battery
    if d.battery:
        batt = d.battery
        charging = "🔌 Charging" if batt.is_charging else "🔋 On battery"
        time_str = ""
        if batt.time_remaining_minutes:
            h = int(batt.time_remaining_minutes // 60)
            m = int(batt.time_remaining_minutes % 60)
            time_str = f"   Remaining {h}h {m}m"
        print(f"\n  {BOLD}Battery{RESET}")
        print(f"  {batt.percentage:.0f}%   {charging}{time_str}")
        print(f"  Status      {colorize(batt.status)}")

    # Network
    net = d.network
    print(f"\n  {BOLD}Network{RESET}")
    print(f"  ↓ {net.download_speed_kbps:.1f} KB/s   ↑ {net.upload_speed_kbps:.1f} KB/s")

    # Health score
    score = d.health_score
    bar_filled = int(score / 5)
    bar = "█" * bar_filled + "░" * (20 - bar_filled)
    if score >= 80:
        score_color = "\033[92m"
    elif score >= 60:
        score_color = "\033[93m"
    else:
        score_color = "\033[91m"

    print(f"\n{'─'*54}")
    print(f"  {BOLD}Health Score{RESET}   {score_color}{BOLD}{score} / 100{RESET}")
    print(f"  [{bar}]")
    print(f"{'═'*54}")


def run_once(as_json: bool, client=None) -> None:
    snapshot = collect_snapshot()
    run_diagnostics(snapshot)

    if as_json:
        print(json.dumps(snapshot.to_dict(), indent=2, ensure_ascii=False))
    else:
        pretty_print(snapshot)

    if client:
        if client.device_id is None and not client.register(snapshot):
            logger.warning("Gagal registrasi device ke backend. Data belum dikirim.")
            return

        ok = client.send(snapshot)
        if not ok:
            logger.warning("Gagal mengirim data ke backend.")


def main() -> None:
    parser = argparse.ArgumentParser(description="DeviceCare Hardware Monitor Agent")
    parser.add_argument("--json",     action="store_true", help="Output sebagai JSON")
    parser.add_argument("--loop",     action="store_true", help="Jalankan dalam loop")
    parser.add_argument("--send",     action="store_true", help="Kirim data ke Laravel backend")
    parser.add_argument("--url",      default="http://localhost:8000", help="URL backend (default: http://localhost:8000)")
    parser.add_argument("--interval", type=int, default=1, help="Interval loop dalam detik (default: 1)")
    args = parser.parse_args()

    client = None
    if args.send:
        from agent.sender import DeviceCareClient
        client = DeviceCareClient(base_url=args.url)
        logger.info(f"Menghubungkan ke backend: {args.url}")

        # Register device sekali di awal
        from agent.collector import collect_snapshot as _snap
        from agent.diagnostics import run_diagnostics as _diag
        init_snap = _snap()
        _diag(init_snap)

        if not client.register(init_snap):
            logger.error("Registrasi device gagal. Pastikan backend berjalan.")
            if not args.loop:
                sys.exit(1)
        else:
            logger.info(f"Device terdaftar dengan ID: {client.device_id}")

    if args.loop:
        logger.info(f"Agent berjalan — interval {args.interval}s. Tekan Ctrl+C untuk berhenti.")
        try:
            while True:
                run_once(args.json, client)
                time.sleep(args.interval)
        except KeyboardInterrupt:
            print("\nAgent dihentikan.")
            if client:
                client.close()
            sys.exit(0)
    else:
        run_once(args.json, client)
        if client:
            client.close()


if __name__ == "__main__":
    main()
