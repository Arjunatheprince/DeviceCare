<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_page_can_be_opened_for_a_registered_device(): void
    {
        $device = Device::create([
            'device_key' => 'test-device-key',
            'device_name' => 'Test Laptop',
            'manufacturer' => 'DeviceCare',
            'model' => 'Demo',
            'cpu' => 'Example CPU',
            'ram_total_gb' => 16,
            'disk_total_gb' => 512,
            'operating_system' => 'Windows',
            'os_version' => '11',
            'agent_version' => '1.0.0',
            'is_active' => true,
        ]);

        $this->get(route('dashboard.report', $device))
            ->assertOk()
            ->assertSee('DeviceCare Laptop Health Report')
            ->assertSee('Test Laptop');
    }
    public function test_monitor_page_shows_quick_health_check(): void
    {
        $device = Device::create([
            'device_key' => 'monitor-device-key',
            'device_name' => 'Monitor Laptop',
            'manufacturer' => 'DeviceCare',
            'model' => 'Demo',
            'cpu' => 'Example CPU',
            'ram_total_gb' => 16,
            'disk_total_gb' => 512,
            'operating_system' => 'Windows',
            'os_version' => '11',
            'agent_version' => '1.0.0',
            'is_active' => true,
        ]);

        $this->get(route('dashboard.show', $device))
            ->assertOk()
            ->assertSee('Quick Health Check')
            ->assertSee('Cetak report PDF');
    }
}
