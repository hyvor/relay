export function flagByCountryCode(countryCode: string) {
	const codePoints = countryCode
		.toUpperCase()
		.split('')
		.map((char) => 127397 + char.charCodeAt(0));

	return String.fromCodePoint(...codePoints);
}

export function getCountryName(countryCode: string, locale: string | null = null) {
	locale = locale || navigator.language || 'en-US';
	return new Intl.DisplayNames([locale], { type: 'region' }).of(countryCode);
}
