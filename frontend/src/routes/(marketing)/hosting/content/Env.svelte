<script>
	import { CodeBlock } from '@hyvor/design/components';
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
# Example: relay-instance.yourdomain.com
# See https://relay.hyvor.com/hosting/setup#instance-domain
INSTANCE_DOMAIN=

# The PostgreSQL database URL.
# Use the format: "postgresql://user:pass@host:5432/database_name?serverVersion=16&charset=utf8"
DATABASE_URL=

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
`}
	language="yaml"
/>
