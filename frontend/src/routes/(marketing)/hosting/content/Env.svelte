<script>
    import {CodeBlock} from '@hyvor/design/components';
</script>

<h1>Environment Variables</h1>

<p>These are the environment variables that you can set for Hyvor Relay.</p>

<CodeBlock
        code={`
# Environment: prod, dev, or test
# you probably want to use prod for a deployment
APP_ENV=prod

# The secret key (32 bytes) used to encrypt sensitive data.
# Generate one using: "openssl rand -base64 32"
APP_SECRET=

# The PostgreSQL database URL.
# Use the format: "postgresql://user:pass@host:5432/database_name?serverVersion=16&charset=utf8"
DATABASE_URL=

# Web URL.
# The public URL where Hyvor Relay and its API will be accessible.
# Example: https://relay.yourdomain.com
# If https is used, Caddy will automatically provision SSL certificates.
# If behind a reverse proxy that handles SSL, use http:// URL.
# See https://relay.hyvor.com/hosting/setup#web-domain
WEB_URL=

# Instance domain.
# The domain name used for the incoming mail server, EHLO identification, and PTR records.
# Example: mail.relay.yourdomain.com
# See https://relay.hyvor.com/hosting/setup#instance-domain
INSTANCE_DOMAIN=

# OpenID Connect (OIDC) configuration
OIDC_ISSUER_URL=
OIDC_CLIENT_ID=
OIDC_CLIENT_SECRET=

# Trusted proxy IP addresses or CIDR ranges.
# This is used to determine the real client IP address and HTTPS status.
# By default, all private IP ranges are trusted.
TRUSTED_PROXIES=

# Logging level
# One of: debug, info, notice, warning, error, critical, alert, emergency
# Default: info
LOG_LEVEL=info

# Telemetry collection (see /hosting/telemetry)
# Set to "false" to disable anonymous telemetry data collection
# Default: true
TELEMETRY=true

# NAT (1:1) Configuration
# See: https://relay.hyvor.com/hosting/setup#nat

# Private network CIDR range(s) to identify this server's private IP addresses.
# Use this when the server is behind 1:1 NAT (e.g., cloud VPCs where the server has a private IP
# and the cloud provider maps it to a public IP).
# Example (single range): PRIVATE_NETWORK=10.0.0.0/8
# Example (multiple, comma-separated): PRIVATE_NETWORK=10.0.0.0/8,172.16.0.0/12
PRIVATE_NETWORK=

# Manual mapping of private IP to public IP for NAT.
# If not set, the public IP is resolved automatically by binding to each private IP
# and calling ifconfig.me / icanhazip.com.
# Format: private_ip:public_ip (comma-separated for multiple)
# Example: NAT_MAP=10.0.1.5:203.0.113.10,10.0.1.6:203.0.113.11
NAT_MAP=
`}
        language="yaml"
/>
