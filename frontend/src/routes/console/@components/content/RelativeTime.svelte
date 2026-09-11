<script lang="ts">
	import { onMount } from 'svelte';

	export let unix: number;


	$: val = getRelativeTime(unix);

	function getRelativeTime(unix: number) {
		const rtf = new Intl.RelativeTimeFormat('en', {
			numeric: 'auto',
			style: 'narrow'
		});

		const now = Date.now();
		const diff = now - unix * 1000;

		const seconds = Math.floor(diff / 1000);
		const minutes = Math.floor(seconds / 60);
		const hours = Math.floor(minutes / 60);
		const days = Math.floor(hours / 24);
		const months = Math.floor(days / 30);
		const years = Math.floor(months / 12);

		if (years > 0) {
			return rtf.format(-years, 'year');
		}

		if (months > 0) {
			return rtf.format(-months, 'month');
		}

		if (days > 0) {
			return rtf.format(-days, 'day');
		}

		if (hours > 0) {
			return rtf.format(-hours, 'hour');
		}

		if (minutes > 0) {
			return rtf.format(-minutes, 'minute');
		}

		return rtf.format(-seconds, 'second');
	}


	onMount(() => {
		// update every minute
		setTimeout(() => {
			val = getRelativeTime(unix);
		}, 60000);
	});
</script>

{val}
