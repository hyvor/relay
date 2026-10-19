<script lang="ts">
	import { Button } from '@hyvor/design/components';

	interface Props {
		name: string;
		price: string;
		priceSubtext?: string;
		divider?: boolean;
		features: string[];
		ctaText: string;
		ctaLink: string;
	}

	let { name, price, priceSubtext, divider, features, ctaText, ctaLink }: Props = $props();
</script>

<div class="plan" class:divider>
	<div class="name">{name}</div>
	<div class="price">{price}</div>
	<div class="price-subtext">{priceSubtext}</div>

	<div class="features">
		{#each features as feature}
			<div class="feature">
				<span class="mdash">&mdash;</span>{feature}
			</div>
		{/each}
	</div>

	<div class="cta">
		<Button as="a" variant="outline" href={ctaLink} size="large">{ctaText}</Button>
	</div>
</div>

<style>
	.plan {
		flex: 1;
		padding: 30px 20px;
		position: relative;
	}

	.name {
		font-size: 18px;
		font-weight: 600;
	}

	.price {
		font-size: 24px;
		font-weight: 600;
		margin-top: 8px;
	}

	.price-subtext {
		font-size: 14px;
		color: var(--text-light);
		margin-top: 4px;
	}

	.plan.divider :before {
		content: '';
		background-color: var(--border);
		position: absolute;
		width: 2px;
		height: 70%;
		right: 0;
		top: 15%;
	}

	.features {
		margin-top: 20px;
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	.mdash {
		margin-right: 8px;
		color: var(--border);
		user-select: none;
	}

	.cta {
		margin-top: 25px;
		text-align: center;
	}
</style>
