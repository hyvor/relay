// Mirrors backend/src/Service/ApiKey/AllowedIp.php — keep in sync.
// Backend remains the source of truth and rejects anything that slips past.

const IPV4_MIN_PREFIX = 24;
const IPV6_MIN_PREFIX = 48;

function ipv4ToBytes(ip: string): number[] | null {
	const parts = ip.split('.');
	if (parts.length !== 4) return null;
	const bytes: number[] = [];
	for (const part of parts) {
		if (!/^\d{1,3}$/.test(part)) return null;
		const n = Number(part);
		if (!Number.isInteger(n) || n < 0 || n > 255) return null;
		bytes.push(n);
	}
	return bytes;
}

function ipv6ToBytes(ip: string): number[] | null {
	// strip zone id
	const at = ip.indexOf('%');
	if (at >= 0) ip = ip.slice(0, at);

	const dcCount = (ip.match(/::/g) || []).length;
	if (dcCount > 1) return null;

	let head: string[] = [];
	let tail: string[] = [];
	if (dcCount === 1) {
		const [h, t] = ip.split('::');
		head = h === '' ? [] : h.split(':');
		tail = t === '' ? [] : t.split(':');
	} else {
		head = ip.split(':');
	}

	// last group may be embedded IPv4
	const expandLast = (groups: string[]): string[] | null => {
		if (groups.length === 0) return groups;
		const last = groups[groups.length - 1];
		if (last.includes('.')) {
			const v4 = ipv4ToBytes(last);
			if (!v4) return null;
			const hex = (a: number, b: number) =>
				((a << 8) | b).toString(16);
			return [...groups.slice(0, -1), hex(v4[0], v4[1]), hex(v4[2], v4[3])];
		}
		return groups;
	};
	const headExp = expandLast(head);
	const tailExp = expandLast(tail);
	if (headExp === null || tailExp === null) return null;

	const totalGroups = headExp.length + tailExp.length;
	if (totalGroups > 8) return null;
	if (dcCount === 0 && totalGroups !== 8) return null;
	if (dcCount === 1 && totalGroups >= 8) return null;

	const fill = dcCount === 1 ? new Array(8 - totalGroups).fill('0') : [];
	const all = [...headExp, ...fill, ...tailExp];
	if (all.length !== 8) return null;

	const bytes: number[] = [];
	for (const group of all) {
		if (!/^[0-9a-fA-F]{1,4}$/.test(group)) return null;
		const n = parseInt(group, 16);
		bytes.push((n >> 8) & 0xff, n & 0xff);
	}
	return bytes;
}

export function validateAllowedIpEntry(entry: string): string | null {
	const trimmed = entry.trim();
	if (trimmed === '') return 'Allowed IP entry must not be empty.';

	const slash = trimmed.indexOf('/');
	const ip = slash === -1 ? trimmed : trimmed.slice(0, slash);
	const prefixPart = slash === -1 ? null : trimmed.slice(slash + 1);

	let prefix: number | null = null;
	if (prefixPart !== null) {
		if (!/^\d+$/.test(prefixPart)) return `Invalid CIDR prefix in '${trimmed}'.`;
		prefix = Number(prefixPart);
	}

	const v4 = ipv4ToBytes(ip);
	const v6 = v4 ? null : ipv6ToBytes(ip);

	if (!v4 && !v6) return `'${trimmed}' is not a valid IPv4 or IPv6 address.`;

	if (v4) {
		const eff = prefix ?? 32;
		if (eff < IPV4_MIN_PREFIX || eff > 32) {
			return `IPv4 CIDR prefix must be between /${IPV4_MIN_PREFIX} and /32 (got '${trimmed}').`;
		}
	} else if (v6) {
		const eff = prefix ?? 128;
		if (eff < IPV6_MIN_PREFIX || eff > 128) {
			return `IPv6 CIDR prefix must be between /${IPV6_MIN_PREFIX} and /128 (got '${trimmed}').`;
		}
	}

	return null;
}
