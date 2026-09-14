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
