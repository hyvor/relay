<!-- <h1>Welcome to SvelteKit</h1>
<p>Visit <a href="https://svelte.dev/docs/kit">svelte.dev/docs/kit</a> to read the documentation</p> -->
<script>
	import { Button } from "@hyvor/design/components";
	import Letter from "./Letter.svelte";
	// import Faq from "./Faq.svelte";
</script>

<svelte:head>
	<title>Hyvor Relay</title>
	<meta name="description" content="Alternative to AWS SES & Mailgun" />
	<link rel="canonical" href="https://post.hyvor.com" />
</svelte:head>

<section class="container" id="hero">

	<h1>
		Hyvor Relay - Self-hosted email sending API
	</h1>

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
