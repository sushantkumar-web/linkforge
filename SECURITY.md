# Security Policy

## Reporting a Vulnerability

Do not open a public issue. Email **security@your-domain** with a description,
reproduction steps, affected versions, and any proof of concept. You'll get a
response within 72 hours.

## Supported Versions

Only the latest release receives security updates. Upgrade promptly.

## Security Design

- API keys stored as SHA-256 hashes, never plaintext
- Passwords hashed with bcrypt
- Session tokens use the selector/validator pattern
- CSRF protection on all state-changing web routes
- SSRF protection blocks shortening internal URLs
- SQL injection prevented via prepared statements
- XSS mitigated by consistent output escaping
- Analytics never store raw IPs — visitor hashes use daily rotating salts

## Out of Scope

- Missing rate limits on non-critical endpoints
- Missing security headers on cached static assets
- Self-XSS without plausible attack vector
- SPF/DKIM/DMARC configuration for outbound email