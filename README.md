# LinkForge

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/Release-v1.0.2-emerald.svg)](https://github.com/sushantkumar-web/linkforge/releases)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D_8.2-777BB4.svg)](https://php.net)
[![Core Latency](https://img.shields.io/badge/Core_TTFB-<1ms-violet.svg)](#performance-architecture)

High-performance, privacy-conscious link management and analytics engine built for native LAMP environments.

LinkForge provides the feature set of modern edge shorteners on ordinary shared hosting and entry-level virtual private servers. It requires zero Docker containers, zero Redis instances, and zero Node.js build pipelines.

---

## Architectural Highlights

* **Sub-Millisecond Execution:** Uses PHP OPcache file-backed memory compilation to serve hot redirects in under 1ms without querying MySQL during the lookup path.
* **Non-Blocking Background Telemetry:** Dispatches HTTP 302 response headers immediately, severing the client connection via `fastcgi_finish_request()` before executing click increment and analytic logging queries.
* **cPanel & Shared Hosting Native:** Operates within standard Apache/LiteSpeed environments with standard file permissions and a web-based GUI installer.
* **Zero-Dependency Asset Pipeline:** Design system implemented with CSS custom properties, system font stacks, and Cloudflare-delivered vector iconography.
* **Self-Contained Release Engine:** Built-in self-updater connects to the GitHub Releases API, pulls signed distribution packages, verifies schema versions, and executes incremental database migrations with zero downtime.

---

## Performance Architecture

Traditional PHP shorteners query MySQL synchronously on every hit, resulting in database queue saturation and connection exhaustion during traffic spikes:

```text
Traditional Shortener Request Flow:
Client Request -> Parse PHP -> MySQL SELECT (5-15ms) -> MySQL UPDATE (10-20ms) -> MySQL INSERT (10-20ms) -> HTTP 302
Total Perceived Latency: 25ms - 60ms+ (Idle) / 1000ms+ (Under Load)

```

LinkForge decouples the read cache and the write path into two isolated stages:

```text
LinkForge Request Flow:
Client Request
  │
  ├── 1. OPcache Memory Check (storage/cache/links/{hash}.php)
  │       └── Hit: Array loaded directly from RAM (0.1ms - 0.3ms)
  │
  ├── 2. HTTP 302 Flush
  │       └── Headers dispatched immediately to client
  │
  ├── 3. fastcgi_finish_request()
  │       └── Connection terminated; client redirected instantly
  │
  └── 4. Background Worker (Client is already gone)
          ├── UPDATE links SET clicks = clicks + 1
          └── INSERT INTO click_logs (device, browser, referrer, hash)

```

### Verified Benchmark

Inspection of the W3C `Server-Timing` header reveals the internal execution overhead of the LinkForge routing and caching layer:

```http
HTTP/2 302
server-timing: app;desc="LinkForge Core";dur=0.75
location: [https://destination.com](https://destination.com)
cache-control: private, no-store, no-cache, must-revalidate, max-age=0

```

To verify latency against your own deployment:

```bash
curl -s -o /dev/null -w "\
  TCP Handshake:    %{time_connect}s\n\
  SSL Handshake:    %{time_appconnect}s\n\
  Time to 302 TTFB: %{time_starttransfer}s\n\
  Total Latency:    %{time_total}s\n\
  HTTP Status:      %{http_code}\n" \
  [https://yourdomain.com/your-slug](https://yourdomain.com/your-slug)

```

---

## Features

### Link Routing & Access Control

* **Custom & Randomized Slugs:** Specify custom keywords or generate high-entropy alphanumeric slugs.
* **Password Protection:** Cryptographically secure access barriers powered by `bcrypt` hashing with dedicated unlocking screens.
* **Time-Bound Expirations:** Native support for timestamp expiries with integrated date-time presets (+24 Hours, +7 Days, +30 Days) that automatically return HTTP 410 Gone when reached.
* **SSRF & Loop Protection:** Automatic validation against loop redirects and reserved internal IP address ranges.
* **Instant Invalidation:** Dynamic cache purging across memory and filesystem on every link update, toggle, or deletion.

### Privacy-Preserving Analytics

* **Zero Persistent IP Logging:** Client IPs are combined with daily rolling salts and user-agent strings to compute temporary hashes for unique visitor tracking. Raw IP addresses are discarded immediately.
* **Environment Extraction:** User-Agent parsing classifies referrers, device types (Desktop, Mobile, Tablet), operating systems, and browser families.
* **Direct Export:** Single-click CSV export utility for all individual link traffic data.

### Developer & Workspace Utilities

* **REST API:** Token-authenticated endpoints supporting link creation, metadata retrieval, status toggling, and analytics export.
* **Dynamic QR Codes:** On-demand QR matrix generation delivered directly via SVG/PNG without third-party tracking APIs.
* **UTM Campaign Builder:** Automatic assembly of Google Analytics parameters (`utm_source`, `utm_medium`, `utm_campaign`) during creation.
* **Rate Limiting:** Built-in sliding-window rate limiters across link creation endpoints to defend against automated exhaustion attacks.

---

## System Requirements

* **PHP:** Version 8.2 or higher
* Required extensions: `pdo_mysql`, `opcache`, `mbstring`, `curl`, `json`


* **Database:** MySQL 5.7+ or MariaDB 10.3+
* **Web Server:** Apache (with `mod_rewrite` enabled), LiteSpeed, or Nginx running PHP-FPM

---

## Installation

### Method A: cPanel / Shared Hosting (Recommended)

1. Download the latest `linkforge-vX.X.X.zip` package from the [Releases](https://github.com/sushantkumar-web/linkforge/releases) page.
2. Upload and extract the archive into your target directory (e.g., `public_html/` or a subdomain folder).
3. In cPanel, navigate to **Domains** and set the **Document Root** to the extracted `public/` directory.
4. Open your browser and navigate to `https://yourdomain.com/install`.
5. Enter your database credentials and configure your administrator account.
6. The installer verifies permissions, imports the initial database schema, writes your production `config.php`, and locks the setup wizard.

### Method B: Manual / Linux CLI

```bash
# Clone the repository
git clone [https://github.com/sushantkumar-web/linkforge.git](https://github.com/sushantkumar-web/linkforge.git) /var/www/linkforge
cd /var/www/linkforge

# Configure storage permissions
chmod -R 755 storage
chmod -R 777 storage/cache storage/logs

# Point your web server DocumentRoot to /var/www/linkforge/public

```

Configure your Apache virtual host to direct all traffic through `public/index.php`:

```apache
<VirtualHost *:80>
    ServerName links.yourdomain.com
    DocumentRoot /var/www/linkforge/public

    <Directory /var/www/linkforge/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

```

---

## REST API Overview

All API requests require a Bearer token generated from the **API Keys** section of the dashboard.

### Create Short Link

```bash
curl -X POST [https://yourdomain.com/api/v1/links](https://yourdomain.com/api/v1/links) \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "[https://example.com/very-long-url-path](https://example.com/very-long-url-path)",
    "title": "Production Deployment",
    "slug": "v1-launch",
    "password": "OptionalPassword",
    "tags": "api, deployment"
  }'

```

#### Response:

```json
{
  "status": "success",
  "data": {
    "id": 12,
    "short_code": "v1-launch",
    "short_url": "[https://yourdomain.com/v1-launch](https://yourdomain.com/v1-launch)",
    "destination_url": "[https://example.com/very-long-url-path](https://example.com/very-long-url-path)",
    "created_at": "2026-09-14 21:00:00"
  }
}

```

---

## Updating

LinkForge features an integrated self-updater. When a new tag is pushed to the GitHub repository:

1. A notification banner appears in the **Settings** view of your dashboard.
2. Click **Apply Update**.
3. LinkForge downloads the distribution package, replaces modified core files, preserves your `config.php` and storage logs, runs outstanding migration scripts sequentially, and updates the active runtime version.

---

## Security

* **CSRF Mitigation:** Synchronizer token validation across all state-altering web forms.
* **SQL Injection Defenses:** 100% prepared PDO statements with parameterized variable bindings.
* **SSRF Hardening:** Automated host resolution checks prevent abuse of localhost (`127.0.0.1`, `::1`) and internal network subnets.
* **Timing Attack Hardening:** Token evaluations implement `hash_equals` to ensure constant-time string comparisons.

---

## License

LinkForge is free and open-source software licensed under the terms of the [GNU Affero General Public License v3.0 (AGPLv3)](https://www.google.com/search?q=LICENSE).

Copyright (c) 2026 Sushant.
