<script lang="ts">
	import { NavLink } from '@hyvor/design/components';
	import IconHouse from '@hyvor/icons/IconHouse';
	import IconShieldLock from '@hyvor/icons/IconShieldLock';
	import { page } from '$app/state';
	import { getAppConfig } from '../../lib/stores/consoleStore';

	let isSudo = $derived(getAppConfig().user.is_sudo);
</script>

{#if isSudo}
	<div class="wrap hds-box">
		<div class="nav-links">
			<NavLink href="/console" active={page.url.pathname.startsWith('/console')}>
				{#snippet start()}
					<IconHouse />
				{/snippet}
				Console
			</NavLink>
			<NavLink href="/sudo" active={page.url.pathname.startsWith('/sudo')}>
				{#snippet start()}
					<IconShieldLock />
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
