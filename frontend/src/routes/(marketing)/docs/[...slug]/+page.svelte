<script lang="ts">
	import {
		Docs,
		DocsNav as Nav,
		DocsNavCategory as NavCategory,
		DocsNavItem as NavItem,
		DocsContent as Content
	} from '@hyvor/design/marketing';
	import { categories } from '../docs';

	let { data } = $props();
</script>

<svelte:head>
	<title>
		{data.name} | Hyvor Relay
	</title>
	<link rel="canonical" href="https://relay.hyvor.com/docs{data.slug ? '/' + data.slug : ''}" />
</svelte:head>

<div class="docs-wrap">
	<Docs>
		{#snippet nav()}
			<Nav>
				{#each categories as category}
					<NavCategory name={category.name}>
						{#each category.pages as page}
							<div class="nav-item-wrap" class:has-parent={page.parent !== undefined}>
								<NavItem href={page.slug === '' ? '/docs' : `/docs/${page.slug}`}>
									{page.name}
								</NavItem>
							</div>
						{/each}
					</NavCategory>
				{/each}
			</Nav>
		{/snippet}
		{#snippet content()}
			<Content>
				{@const Component = data.component}
				<Component />
			</Content>
		{/snippet}
	</Docs>
</div>

<style>
	.docs-wrap :global(.nav-items a.active) {
		background-color: var(--accent-lightest) !important;
	}
	.nav-item-wrap.has-parent {
		padding-left: 15px;
	}
</style>
