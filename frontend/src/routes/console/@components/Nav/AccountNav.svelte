<script lang="ts">
	import { NavLink } from '@hyvor/design/components';
	import IconShieldLock from '@hyvor/icons/IconShieldLock';
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
