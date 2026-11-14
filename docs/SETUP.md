# Nhanh Data 2-Way Syncer — Setup Guide

This document covers installing and configuring the plugin on macOS and Linux,
plus setting up WordPress cron so the Nhanh.vn → WooCommerce import runs
automatically.

## Contents

1. [Prerequisites](#prerequisites)
2. [Install the plugin](#install-the-plugin)
3. [Configure Nhanh API credentials](#configure-nhanh-api-credentials)
4. [Configure the WordPress cron job](#configure-the-wordpress-cron-job)
   - [macOS](#macos)
   - [Linux](#linux)
5. [Run the import manually](#run-the-import-manually)
6. [Troubleshooting](#troubleshooting)

## Prerequisites

- WordPress 6.0+ with WooCommerce 7.0+ active.
- PHP 7.4+ with the `guzzlehttp` and `illuminate/support` packages (bundled).
- A Nhanh.vn app (`appId`, `secretKey`) with the `pos` service enabled.
- Public HTTPS endpoint reachable from Nhanh.vn for the OAuth callback.

## Install the plugin

### From the WordPress admin (recommended for production)

1. Go to **Plugins → Add New**.
2. Search for `Nhanh Data 2-Way Syncer`.
3. Install and activate.

### From source (development)

```bash
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/puleeno/nhanh-data-2way-syncer.git
cd nhanh-data-2way-syncer
composer install
```

Then activate the plugin from the WordPress admin.

## Configure Nhanh API credentials

1. Go to **Nhanh Syncer → Cấu hình API**.
2. Fill in:
   - **App ID** and **Secret Key** (from your Nhanh app).
   - **Return Link** (must exactly match the one registered in the Nhanh app).
3. Click **Authorize** to start the OAuth flow.
4. After Nhanh redirects back, you'll get an `access_token` and `business_id`.
5. Enable **Auto Sync** and set the interval (e.g. 15 minutes).

## Configure the WordPress cron job

The plugin registers a WP cron event (`nhanh_syncer_run_import`) that runs the
Nhanh → WooCommerce import. WP cron needs a visitor request to fire, so on
production you should set up a real OS-level cron job that hits
`wp-cron.php` on a regular interval.

### macOS

Use `launchd` (preferred) or `crontab`.

#### Option A: launchd

Create a plist file at `~/Library/LaunchAgents/local.nhanh-syncer.cron.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>local.nhanh-syncer</string>
    <key>ProgramArguments</key>
    <array>
        <string>curl</string>
        <string>-s</string>
        <string>https://yoursite.com/wp-cron.php</string>
    </array>
    <key>StartInterval</key>
    <integer>900</integer>
    <key>RunAtLoad</key>
    <true/>
</dict>
</plist>
```

Load it:

```bash
launchctl load ~/Library/LaunchAgents/local.nhanh-syncer.cron.plist
```

To unload:

```bash
launchctl unload ~/Library/LaunchAgents/local.nhanh-syncer.cron.plist
```

#### Option B: crontab

```bash
crontab -e
```

Add:

```cron
*/15 * * * * curl -s https://yoursite.com/wp-cron.php > /dev/null 2>&1
```

### Linux

Use `crontab` or systemd.

#### Option A: crontab

```bash
crontab -e
```

Add:

```cron
*/15 * * * * curl -s https://yoursite.com/wp-cron.php > /dev/null 2>&1
```

#### Option B: systemd timer

Create `/etc/systemd/system/nhanh-syncer.service`:

```ini
[Unit]
Description=Nhanh Syncer cron
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/bin/curl -s https://yoursite.com/wp-cron.php
```

Create `/etc/systemd/system/nhanh-syncer.timer`:

```ini
[Unit]
Description=Nhanh Syncer timer
Requires=nhanh-syncer.service

[Timer]
OnCalendar=*:0/15

[Install]
WantedBy=timers.target
```

Enable:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nhanh-syncer.timer
```

## Run the import manually

From the WordPress admin:

1. Go to **Nhanh Syncer → Tổng quan**.
2. Click **Đồng bộ ngay** (or the equivalent button on the dashboard).

From WP-CLI:

```bash
wp eval 'Puleeno\NhanhVn\Syncer.Plugin::instance()->run_import_job();'
```

## Troubleshooting

- **Cron not firing**: check `wp_schedule_event` is registered and the OS cron job is hitting `wp-cron.php`.
- **Import fails**: check the **Nhật ký & Tra soát** page for error details.
- **API credentials invalid**: re-authorize via **Cấu hình API**.