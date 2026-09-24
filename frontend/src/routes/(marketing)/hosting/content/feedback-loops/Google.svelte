<h1>Google</h1>

<p>
	Gmail does not send per-email complaint reports. Instead, it provides aggregated data, such as
	spam rate and domain reputation, through
	<a href="https://postmaster.google.com" target="_blank">Google Postmaster Tools</a>. Hyvor Relay
	periodically fetches this data using the
	<a
		href="https://developers.google.com/workspace/gmail/postmaster/reference/rest/v2"
		target="_blank">Postmaster Tools API</a
	>.
</p>

<h2 id="setup">Setup</h2>

<ol>
	<li>
		Sign in to <a href="https://postmaster.google.com" target="_blank"
			>Google Postmaster Tools</a
		>
		and add your <a href="/hosting/setup#instance-domain">instance domain</a> (e.g.
		<code>mail.relay.yourdomain.com</code>). Verify it by adding the <code>TXT</code> record Google
		shows to the instance domain's DNS.
	</li>
	<li>
		In the <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a>,
		create a project and enable the <strong>Gmail Postmaster Tools API</strong>.
	</li>
	<li>
		Configure the OAuth consent screen and publish the app. Refresh tokens of apps in
		<em>Testing</em> status expire after 7 days.
	</li>
	<li>
		Create an OAuth client ID of type <strong>Web application</strong>, and add
		<code>https://developers.google.com/oauthplayground</code> as an authorized redirect URI.
	</li>
	<li>
		Open the <a href="https://developers.google.com/oauthplayground" target="_blank"
			>OAuth 2.0 Playground</a
		>, enable <strong>Use your own OAuth credentials</strong> in the settings, and enter the
		client ID and secret. Authorize the
		<code>https://www.googleapis.com/auth/postmaster.readonly</code> scope with the Google account
		used in step 1, then exchange the authorization code for tokens.
	</li>
	<li>
		Set the following <a href="/hosting/env">environment variables</a> and restart Hyvor Relay:
		<ul>
			<li><code>GOOGLE_POSTMASTER_CLIENT_ID</code>: the OAuth client ID</li>
			<li><code>GOOGLE_POSTMASTER_CLIENT_SECRET</code>: the OAuth client secret</li>
			<li>
				<code>GOOGLE_POSTMASTER_REFRESH_TOKEN</code>: the refresh token from the Playground
			</li>
		</ul>
	</li>
</ol>

<p>
	Data becomes available only after Gmail receives a reasonable daily volume of email from your
	domain.
</p>
