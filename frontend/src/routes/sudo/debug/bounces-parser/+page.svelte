<script lang="ts">
	import {
		Button,
		CodeBlock,
		IconMessage,
		Loader,
		Radio,
		Textarea,
		toast
	} from '@hyvor/design/components';
	import { debugParseBounceFBL } from '../../sudoActions';

	let emailContent = $state('');
	let type: 'bounce' | 'fbl' = $state('bounce');
	let parsing = $state(false);
	let error = $state('');
	let result = $state('Parser at your service...');

	function handleParse() {
		error = '';
		result = '';

		if (!emailContent.trim()) {
			toast.error('Please paste the raw bounce/FBL email.');
			return;
		}

		parsing = true;

		debugParseBounceFBL(emailContent, type)
			.then((response) => {
				result = JSON.stringify(response.parsed, null, 4);
			})
			.catch((e) => {
				error = e.message;
			})
			.finally(() => {
				parsing = false;
			});
	}
</script>

<div class="wrap">
	<div class="input">
		<textarea
			placeholder="Paste the raw bounce/FBL email here..."
			rows="10"
			class="hds-textarea"
			bind:value={emailContent}
		></textarea>

		<div class="action">
			<Radio bind:group={type} name="fbl-bounce" value="bounce">Bounce</Radio>
			<Radio bind:group={type} name="fbl-bounce" value="fbl">FBL</Radio>
			<Button on:click={handleParse}>Parse Email</Button>
		</div>
	</div>

	<div class="output">
		{#if parsing}
			<Loader full />
		{:else if error}
			<IconMessage error>{error}</IconMessage>
		{:else}
			<CodeBlock code={result} language="json" />
		{/if}
	</div>
</div>

<style>
	.wrap {
		height: 100%;
		display: flex;
		flex-direction: column;
	}

	.input {
		flex: 1;
		padding: 20px;
		display: flex;
		flex-direction: column;
	}

	textarea {
		background-color: var(--input);
		border-radius: 20px;
		padding: 20px;
		flex: 1;
		border: none;
		resize: none;
		width: 100%;
	}

	.action {
		display: flex;
		gap: 10px;
		margin-top: 15px;
		align-items: center;
	}

	.output {
		flex: 1;
		border-top: 1px solid var(--border);
		overflow-y: auto;
		padding: 20px;
	}
</style>
