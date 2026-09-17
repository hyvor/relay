import type { Kyc, KycAccountType, KycContentOwnership, KycSendingType } from '../../types';
import consoleApi from '../consoleApi.svelte';

export interface KycSubmitData {
	account_type: KycAccountType;
	name: string;
	country: string;
	address: string;
	website: string;
	content_ownership: KycContentOwnership;
	sending_type: KycSendingType[];
	use_case: string;
}

export function getKyc() {
	return consoleApi.get<Kyc | null>({
		endpoint: 'kyc',
		userApi: true
	});
}

export function submitKyc(data: KycSubmitData) {
	return consoleApi.post<Kyc>({
		endpoint: 'kyc',
		userApi: true,
		data
	});
}
