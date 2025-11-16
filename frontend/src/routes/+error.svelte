<script>
	import { Button } from '@hyvor/design/components';
	import { page } from '$app/state';
	import Header from './(marketing)/Header.svelte';
	import Footer from './(marketing)/Footer.svelte';
</script>

<Header />
<div class="error-page">
	<h1 class="heading">404 - Page Not Found</h1>
	<p class="description">
		Sorry, the page you are looking for might have been removed, had its name changed, or is
		temporarily unavailable.
	</p>
	<Button as="a" href="/">Go to Homepage</Button>
</div>
<Footer />

<style>
	.error-page {
		padding: 20px;
		text-align: center;
		height: calc(100vh - 100px);
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
	}
	.heading {
		font-size: 38px;
		margin: 0;
	}
	.description {
		/*reduce the width of the text to fit the screen*/
		max-width: 100%;
		width: 450px;
	}
</style>
