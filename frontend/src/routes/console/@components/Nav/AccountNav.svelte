<script lang="ts">
	import { NavLink, NavLinkGroup } from '@hyvor/design/components';
	import IconShieldLock from '@hyvor/icons/IconShieldLock';
	import IconPersonBadge from '@hyvor/icons/IconPersonBadge';
	import IconArrowRightShort from '@hyvor/icons/IconArrowRightShort';
	import { getAppConfig, kycStatusStore } from '../../lib/stores/consoleStore';
	import IconCreditCard from '@hyvor/icons/IconCreditCard';
	import { page } from '$app/state';
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
			<NavLinkGroup activeBackground="var(--accent-light)">
			<NavLink href="/console/kyc" active={page.url.pathname.startsWith('/console/kyc')}>
				{#snippet start()}
					<IconPersonBadge />
				{/snippet}
				KYC
			</NavLink>
			{#if $kycStatusStore === 'approved'}
				<NavLink href="/console/billing" active={page.url.pathname.startsWith('/console/billing')}>
					{#snippet start()}
						<IconCreditCard />
					{/snippet}
					Billing
				</NavLink>
			{/if}
			</NavLinkGroup>
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
