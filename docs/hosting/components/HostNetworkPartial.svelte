<h3 id="host-network">Host Network</h3>

<p>
	The application uses the <a
		href="https://docs.docker.com/engine/network/drivers/host/"
		target="_blank">host network mode</a
	> to bind to the server's IP addresses directly. This allows Hyvor Relay to control the IP addresses
	used for sending emails. Other network modes (e.g., bridge, overlay) are not supported.
</p>
