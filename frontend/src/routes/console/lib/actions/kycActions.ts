import type { Kyc, KycBusinessType } from '../../types';
import consoleApi from '../consoleApi.svelte';

export interface KycSubmitData {
	full_name: string;
	business_type: KycBusinessType;
	business_name?: string;
	country: string;
	address: string;
	phone: string;
	website: string;
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
