// Mirrors backend/src/Service/ApiKey/AllowedIp.php — keep in sync.
// Backend remains the source of truth and rejects anything that slips past.

import ipaddr from 'ipaddr.js';

const IPV4_MIN_PREFIX = 24;
const IPV6_MIN_PREFIX = 48;

export function cidrAddressCount(entry: string): number {
	if (!entry.includes('/')) return 1;
	try {
		const [ip, prefix] = ipaddr.parseCIDR(entry);
		const bits = (ip.kind() === 'ipv4' ? 32 : 128) - prefix;
		return bits <= 0 ? 1 : Math.pow(2, bits);
	} catch {
		return 1;
	}
}

export function validateAllowedIpEntry(entry: string): string | null {
	const trimmed = entry.trim();
	if (trimmed === '') return 'Allowed IP entry must not be empty.';

	if (!trimmed.includes('/')) {
		if (!ipaddr.isValid(trimmed)) {
			return `'${trimmed}' is not a valid IPv4 or IPv6 address.`;
		}
		return null;
	}

	try {
		const [ip, prefix] = ipaddr.parseCIDR(trimmed);
		if (ip.kind() === 'ipv4') {
			if (prefix < IPV4_MIN_PREFIX || prefix > 32) {
				return `IPv4 CIDR prefix must be between /${IPV4_MIN_PREFIX} and /32 (got /${prefix}).`;
			}
		} else {
			if (prefix < IPV6_MIN_PREFIX || prefix > 128) {
				return `IPv6 CIDR prefix must be between /${IPV6_MIN_PREFIX} and /128 (got /${prefix}).`;
			}
		}
	} catch {
		return `'${trimmed}' is not a valid IPv4 or IPv6 address.`;
	}

	return null;
}
