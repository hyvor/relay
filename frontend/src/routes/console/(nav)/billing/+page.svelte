<script lang="ts">
	import BillingBox from './BillingBox.svelte';
	import Plans from './Plans.svelte';
	import Usage from './Usage/Usage.svelte';
</script>

<div class="view">
	<div class="column">
		<BillingBox padding={0}>
			<Plans />
		</BillingBox>
	</div>

	<div class="column">
		<BillingBox title="Usage">
			<Usage />
		</BillingBox>
	</div>
</div>

<style>
	.view {
		height: 100%;
		display: flex;
	}

	.column {
		display: flex;
		flex: 1;
		flex-direction: column;
	}

	@media (max-width: 992px) {
		.view {
			flex-direction: column;
		}
	}
</style>
