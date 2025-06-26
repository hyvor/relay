<script>
	import { Button } from '@hyvor/design/components';
	import Letter from './Letter.svelte';
</script>

<svelte:head>
	<title>Hyvor Relay</title>
	<meta name="description" content="Alternative to AWS SES & Mailgun" />
	<link rel="canonical" href="https://post.hyvor.com" />
</svelte:head>

<section class="container" id="hero">
	<h1>Hyvor Relay - Self-hosted email sending API</h1>

	<div class="button-wrap">
		<Button as="a" href="/relay" size="large">Open</Button>
	</div>
</section>

<div class="hds-container">
	<Letter />
</div>

<style>
	#hero {
		text-align: center;
		padding: 40px 0 100px;
	}
</style>
