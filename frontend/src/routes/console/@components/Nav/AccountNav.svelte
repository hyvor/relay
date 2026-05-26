<script lang="ts">
	import { NavLink } from '@hyvor/design/components';
	import IconArrowRight from '@hyvor/icons/IconArrowRight';
	import { getAppConfig } from '../../lib/stores/consoleStore';

	let isSudo = $derived(getAppConfig().user.is_sudo);
</script>

{#if isSudo}
	<div class="wrap hds-box">
		<div class="nav-links">
			<NavLink href="/sudo">
				{#snippet end()}
					<IconArrowRight />
				{/snippet}
				Sudo
			</NavLink>
		</div>
	</div>
{/if}

<style lang="scss">
	.wrap {
		padding: 10px 0;
		margin-bottom: 15px;
		overflow: hidden;
	}

	@media (max-width: 992px) {
		.wrap {
			display: none;
		}
	}
</style>
