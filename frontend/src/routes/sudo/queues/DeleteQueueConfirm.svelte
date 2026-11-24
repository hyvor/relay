<script lang="ts">
	interface Props {
		name: string;
	}

	let { name }: Props = $props();
</script>

Are you sure you want to delete the queue <strong>{name}</strong>? Projects routed to this queue
will be re-routed on their next send.
