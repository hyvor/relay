<script lang="ts">
	import { page } from '$app/state';
	import SingleBox from '../../../@components/content/SingleBox.svelte';
	import { consoleUrlProject } from '../../../lib/consoleUrl';
	import { getEmailByUuid, retrySend } from '../../../lib/actions/emailActions';
	import SendDetail from '$lib/sends/SendDetail.svelte';
</script>

<SingleBox>
	<SendDetail
		fetchSend={() => getEmailByUuid(page.params.uuid ?? '')}
		backHref={consoleUrlProject('sends')}
		onRetry={retrySend}
	/>
</SingleBox>
