<script lang="ts">
	import { Callout } from '@hyvor/design/components';
	import Nav from '../@components/Nav/Nav.svelte';
	import ProjectSelector from '../@components/Nav/ProjectSelector.svelte';
	import { getAppConfig } from '../lib/stores/consoleStore';

    interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();

</script>

<div class="main-inner">
    <Nav />
    <ProjectSelector />

    <div class="content">
        {#if getAppConfig().app.send_restricted}
            <div class="send-restricted">
                <Callout type="warning">
                    Until your <a href="/console/kyc">KYC</a> is approved and your subscription is active,
                    you can only send emails to members of your organization or to the simulator
                    (@simulator.relay.hyvor.com).
                </Callout>
            </div>
        {/if}
        {@render children?.()}
    </div>
</div>

<style>
	.main-inner {
		display: flex;
		flex: 1;
		width: 100%;
		height: calc(100vh - var(--hyvor-bar-height));
		min-height: 0;
	}

	.content {
		display: flex;
		flex-direction: column;
		padding: 15px;
		flex: 1;
		width: 100%;
		height: 100%;
		min-width: 0;
	}

	.send-restricted {
		margin-bottom: 15px;
	}

	.send-restricted a {
		text-decoration: underline;
	}

	@media (max-width: 992px) {
		.main-inner {
			display: block;
		}
		.content {
			padding-bottom: 150px;
			height: initial;
			min-height: calc(100vh - 50px);
		}
	}
</style>
