<script lang="ts">
	import { NavLink } from '@hyvor/design/components';
	import IconShieldLock from '@hyvor/icons/IconShieldLock';
	import IconPersonBadge from '@hyvor/icons/IconPersonBadge';
	import IconArrowRightShort from '@hyvor/icons/IconArrowRightShort';
	import { getAppConfig } from '../../lib/stores/consoleStore';
</script>

<!-- 
{#if isSudo}
	<div class="wrap hds-box">
		<div class="nav-links">
			<NavLink href="/sudo">
				{#snippet start()}
					<IconShieldLock />
				{/snippet}
				{#snippet end()}
					<IconArrowRightShort />
				{/snippet}
				Sudo
			</NavLink>
		</div>
	</div>
{/if} -->


{#if getAppConfig().deployment === 'cloud'}
	<div class="wrap hds-box">
		<div class="nav-links">
			<NavLink href="/console/kyc">
				{#snippet start()}
					<IconPersonBadge />
				{/snippet}
				KYC
			</NavLink>
		</div>
	</div>
{/if}

<style>
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
