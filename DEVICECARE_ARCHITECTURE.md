# DeviceCare Architecture

## Product Direction

DeviceCare is a personal laptop health monitor with a fleet-ready foundation.

The current product focus is one user monitoring one or more personal devices. The architecture keeps device identity, time-series readings, alerts, and diagnostics separated so the app can grow into multi-device usage later without turning the product into an enterprise fleet tool too early.

This direction is intentional:

- Useful for personal daily use.
- Strong enough for a technical portfolio.
- Structured enough to become a product after real user validation.
- Avoids premature B2B, role-based access, enterprise fleet management, and ML-heavy predictive maintenance.

## Source Of Truth

- Agent: `workspace/devicecare-agent`
- Backend and dashboard: `workspace/devicecare-backend-fresh`

Older partial backend/dashboard folders were removed so development has one clear path.

## Runtime Flow

1. The Python agent collects local laptop metrics.
2. The diagnostic engine classifies each component as `NORMAL`, `WARNING`, or `CRITICAL`.
3. The agent registers the device through `POST /api/devices/register`.
4. The agent sends readings through `POST /api/devices/{device}/readings`.
5. Laravel stores time-series sensor readings and diagnostic results.
6. Laravel updates baseline, anomaly, trend, process, and alert data.
7. The dashboard displays device status, history, diagnosis, and alerts.

## Core Metrics

The monitoring foundation covers:

- CPU usage, temperature, clock, core/thread count.
- GPU usage, temperature, memory usage when available.
- RAM usage, used memory, available memory.
- Storage usage, free space, read/write speed.
- Battery percentage, charging state, remaining time, wear level, cycle count.
- Network upload/download speed.
- SMART storage health, bad sectors, pending sectors, disk age, disk temperature.
- Top processes by CPU and memory.

## Diagnostic Engine

The agent owns first-pass diagnostic rules because it has immediate access to raw local readings.

Important rule design principles:

- Diagnosis must use combined signals, not only single thresholds.
- Example: CPU at 87 C under low load is more suspicious than CPU at 87 C under full load.
- Battery wear and SMART health are first-class diagnostic inputs, not nice-to-have metadata.
- Health score is weighted by component impact.

Current score weights:

- CPU: 30 percent
- RAM: 15 percent
- Storage usage: 15 percent
- SMART health: 15 percent
- Battery: 15 percent
- GPU: 10 percent

## Backend Responsibilities

The backend stores and enriches the stream of readings:

- Device registration and last-seen status.
- Latest reading and historical readings.
- Diagnostic result records per component.
- Alert generation for critical/warning status.
- Historical trend endpoints.
- Baseline and anomaly detection.
- Process snapshot storage.

The backend is fleet-ready at the data model level because each reading belongs to a device. Product focus should still remain personal until there is real evidence that fleet users need it.

## Non-Negotiable Feature Checklist

- Core monitoring: implemented in the Python agent.
- Combination-based diagnostics: implemented in `agent/diagnostics.py`.
- Battery wear level: implemented in collector, sender, backend validation, and storage.
- SMART storage data: implemented in collector, sender, backend validation, and storage.
- Historical trends: implemented through reading history and trend services.
- Critical notifications: implemented through backend alert services.

## High Priority Next Features

- Stabilize top process reporting end to end.
- Add report export for portfolio and service use cases.
- Keep expanding unit tests around diagnostic rules.
- Add demo assets: screenshots, GIF, or short video.

## Explicitly Deferred

Do not prioritize these until there is user evidence:

- Enterprise fleet dashboard.
- Role-based access control.
- Predictive maintenance with ML.
- Crowdsourced benchmarks.
- AI assistant as a headline feature.

The AI assistant can remain secondary. It should answer from real collected data, diagnostics, and history, not act as a generic wrapper.

## Verification Targets

Minimum checks before considering a change healthy:

- `python -m py_compile main.py agent\models.py agent\collector.py agent\diagnostics.py agent\sender.py`
- `python -m unittest discover -s tests`
- `php artisan test`
- Manual end-to-end smoke test: run Laravel, run the agent with `--send`, confirm dashboard updates.