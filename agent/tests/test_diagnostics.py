import unittest

from agent.diagnostics import (
    STATUS_CRITICAL,
    STATUS_NORMAL,
    STATUS_WARNING,
    calculate_health_score,
    diagnose_battery,
    diagnose_cpu,
    diagnose_smart,
)
from agent.models import (
    BatteryData,
    CpuData,
    DeviceInfo,
    DiskData,
    HardwareSnapshot,
    NetworkData,
    RamData,
    SmartDiskData,
)


class DiagnosticEngineTest(unittest.TestCase):
    def test_cpu_hot_under_low_load_warns_about_cooling(self):
        cpu = CpuData(
            usage_percent=24.0,
            temperature_celsius=87.0,
            frequency_mhz=3100.0,
            core_count=4,
            thread_count=8,
        )

        result = diagnose_cpu(cpu)

        self.assertEqual(result.status, STATUS_WARNING)
        self.assertIn("beban CPU rendah", result.message)
        self.assertIn("sistem pendinginan", result.message)

    def test_cpu_critical_temperature_overrides_context(self):
        cpu = CpuData(
            usage_percent=15.0,
            temperature_celsius=96.0,
            frequency_mhz=3100.0,
            core_count=4,
            thread_count=8,
        )

        result = diagnose_cpu(cpu)

        self.assertEqual(result.status, STATUS_CRITICAL)
        self.assertIn("sangat tinggi", result.message)

    def test_battery_wear_level_is_diagnosed_even_when_charge_is_ok(self):
        battery = BatteryData(
            percentage=82.0,
            is_charging=False,
            time_remaining_minutes=180.0,
            design_capacity_mwh=50000,
            full_charge_capacity_mwh=32000,
            wear_level_percent=64.0,
            cycle_count=540,
        )

        result = diagnose_battery(battery)

        self.assertEqual(result.status, STATUS_WARNING)
        self.assertIn("Kapasitas baterai menurun", result.message)
        self.assertIn("Siklus charge", result.message)

    def test_smart_bad_sectors_mark_disk_critical(self):
        smart = SmartDiskData(
            model="Example SSD",
            serial="ABC123",
            health_status="Healthy",
            temperature_celsius=42.0,
            reallocated_sectors=80,
            pending_sectors=0,
            uncorrectable_sectors=0,
            power_on_hours=12000,
            power_cycle_count=350,
            rotation_rate=0,
            is_ssd=True,
        )

        result = diagnose_smart(smart)

        self.assertEqual(result.status, STATUS_CRITICAL)
        self.assertIn("sektor rusak", result.message)
        self.assertIn("Backup data", result.message)

    def test_health_score_combines_component_statuses(self):
        snapshot = HardwareSnapshot(
            timestamp="2026-09-28T00:00:00+00:00",
            device=DeviceInfo(
                hostname="test-machine",
                os="Windows",
                os_version="11",
                manufacturer="Example",
                model="Laptop",
                cpu_name="Example CPU",
                ram_total_gb=16.0,
                disk_total_gb=512.0,
            ),
            cpu=CpuData(20.0, 50.0, 3000.0, 4, 8, STATUS_WARNING),
            gpu=None,
            ram=RamData(16.0, 8.0, 8.0, 50.0, STATUS_NORMAL),
            disk=DiskData(512.0, 200.0, 312.0, 39.0, None, None, STATUS_NORMAL),
            smart=None,
            battery=BatteryData(90.0, False, None, None, None, None, None, STATUS_CRITICAL),
            network=NetworkData(1.0, 1.0, 0.0, 0.0, STATUS_NORMAL),
        )

        self.assertEqual(calculate_health_score(snapshot), 76)


if __name__ == "__main__":
    unittest.main()