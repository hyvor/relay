<script lang="ts">
	import {
		FormControl,
		Modal,
		Radio,
		SplitControl,
		Textarea,
		TextInput,
		toast
	} from '@hyvor/design/components';
	import type { DnsRecord, DnsRecordType } from '../../sudoTypes';
	import { getHost } from './dns';
	import { createDnsRecord } from '../../sudoActions';
	import { dnsRecordsStore } from '../../sudoStore';

	interface Props {
		record?: DnsRecord | null;
		show: boolean;
	}

	let { record = null, show = $bindable(false) }: Props = $props();

	let type: DnsRecordType = $state(record ? record.type : 'A');
	let subdomain = $state(record ? record.subdomain : '');
	let content = $state(record ? record.content : '');
	let ttl = $state(record ? record.ttl : 300);
	let priority = $state(record ? record.priority : 10);

	let loading = $state(false);

	function handleConfirm() {
		if (content.trim() === '') {
			toast.error('Value cannot be empty');
			return;
		}

		if (record) {
		} else {
			loading = true;

			createDnsRecord({
				type,
				subdomain: subdomain.trim(),
				content: content.trim(),
				ttl,
				priority
			})
				.then((res) => {
					toast.success('DNS record created successfully');
					dnsRecordsStore.update((records) => [res, ...records]);
					show = false;
				})
				.catch((error) => {
					toast.error('Failed to create DNS record:');
				})
				.finally(() => {
					loading = false;
				});
		}
	}
</script>

<Modal
	bind:show
	title={record ? 'Update DNS Record' : 'Create DNS Record'}
	footer={{ confirm: { text: record ? 'Update' : 'Create' } }}
	on:confirm={handleConfirm}
	{loading}
>
	<SplitControl label="Type">
		<FormControl>
			<Radio value="A" bind:group={type}>A</Radio>
			<Radio value="AAAA" bind:group={type}>AAAA</Radio>
			<Radio value="CNAME" bind:group={type}>CNAME</Radio>
			<Radio value="MX" bind:group={type}>MX</Radio>
			<Radio value="TXT" bind:group={type}>TXT</Radio>
		</FormControl>
	</SplitControl>
	<SplitControl label="Subdomain" caption="Leave empty for the instance domain.">
		<TextInput bind:value={subdomain} block />
		<div class="host">
			Host: <span class="hostname">{getHost(subdomain)}</span>
		</div>
	</SplitControl>
	<SplitControl label="Value">
		{#if type === 'TXT'}
			<Textarea bind:value={content} block maxlength={255} />
		{:else}
			<TextInput bind:value={content} block maxlength={255} />
		{/if}
	</SplitControl>
	<SplitControl label="TTL">
		<TextInput bind:value={ttl} block type="number" />
	</SplitControl>
	{#if type === 'MX'}
		<SplitControl label="Priority">
			<TextInput bind:value={priority} block type="number" />
		</SplitControl>
	{/if}
</Modal>

<style>
	.host {
		margin-top: 10px;
		font-size: 14px;
	}
	.hostname {
		font-weight: 600;
	}
</style>
