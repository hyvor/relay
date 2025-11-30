<h3 id="app-secret">App Secret</h3>

<p>
	The <code>APP_SECRET</code> variable is a 32-bytes key used to encrypt sensitive data (e.g., API
	keys, tokens) in the application. You should not change this value after the initial setup, as
	it will invalidate existing encrypted data. Key rotation is not supported yet, but
	<a href="https://github.com/hyvor/internal/issues/55" target="_blank">planned</a>.
</p>
