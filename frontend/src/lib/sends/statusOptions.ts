import type { StatusOption } from '../../routes/console/types';

/**
 * Shared send-recipient status filter options, used by both the console and
 * sudo sends listings so they stay in sync.
 */
export const SEND_STATUS_OPTIONS: StatusOption[] = [
	{ value: null, label: 'All' },
	{ value: 'queued', label: 'Queued' },
	{ value: 'accepted', label: 'Accepted' },
	{ value: 'deferred', label: 'Deferred' },
	{ value: 'bounced', label: 'Bounced' },
	{ value: 'complained', label: 'Complained' },
	{ value: 'suppressed', label: 'Suppressed' },
	{ value: 'failed', label: 'Failed' }
];
