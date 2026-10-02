<?php

return [
    /*
     * Email tujuan untuk alert notifikasi.
     * Set DEVICECARE_ALERT_EMAIL di .env untuk mengaktifkan email alert.
     * Kosongkan untuk nonaktifkan.
     */
    'alert_email' => env('DEVICECARE_ALERT_EMAIL', ''),

    /*
     * Cooldown per severity (menit).
     * Mencegah spam notifikasi untuk kondisi yang sama.
     */
    'alert_cooldown' => [
        'CRITICAL' => (int) env('ALERT_COOLDOWN_CRITICAL', 3),
        'WARNING'  => (int) env('ALERT_COOLDOWN_WARNING', 15),
    ],
];
