<h1>Scaling</h1>

<p>
	This document helps you think about scaling Hyvor Relay. It might oversimplify certain aspects,
	but it should give you a good starting point.
</p>

<ul>
	<li>
		<a href="postgres-connections">Postgres Max Connections</a>
	</li>
	<li>
		<a href="scaling-examples">Scaling Examples</a>
		<ul style="margin-top:10px">
			<li>
				<a href="onemil-per-day">1 Million Emails Per Day</a>
			</li>
		</ul>
	</li>
</ul>

<h2 id="postgres-connections">Postgres Max Connections</h2>

<p>
	Hyvor Relay heavily relies on Postgres connections. Email, webhook, and API workers all create
	Postgres connections. You need to ensure that your Postgres instance can handle the total number
	of connections created by all workers:
</p>

<ul>
	<li>
		<strong>Email workers</strong>: 1 connection per worker
	</li>
	<li>
		<strong>Webhook workers</strong>: 1 connection per worker
	</li>
	<li>
		<strong>API workers</strong>: 1 connection per worker when handling a request
	</li>
</ul>

<h3 id="increase-max-connections">Increasing Max Connections</h3>

<p>
	The default max connection limit is
	<strong>100</strong>. As a first step, you can increase it based on available RAM on your
	Postgres server. A common recommendation is to allocate
	<strong>1 connection per 10MB of RAM</strong>. For example, if your Postgres server has
	<strong>32GB RAM</strong>, you can set max connections to <strong>320</strong>. Note that this
	is a general recommendation, and you might also need to consider other system resources.
</p>

<h2 id="scaling-examples">Scaling Examples</h2>

<p>
	Here are some examples of scaling Hyvor Relay for different email sending volumes. We assume the
	following in all examples:
</p>

<ul>
	<li>
		A SMTP delivery takes around <strong>200ms</strong> on average, but we will assume
		<strong>1s</strong> to be conservative.
	</li>
	<li>
		We will only consider email workers for simplicity, but in a real-world scenario, you should
		also consider API (required to receive email sending requests) and webhook workers
		(optionally, if used).
	</li>
	<li>
		We will assume that email requests are evenly distributed throughout the day, for example
		when sending transactional emails. If you are sending bulk emails, you need to consider peak
		loads.
	</li>
	<li>We will assume that each email is a simple email without large attachments.</li>
</ul>

<h3 id="onemil-per-day">1 Million Emails Per Day</h3>

<p>
	Sending 1 million emails per day is easily achievable with Hyvor Relay with just one app server
	and Postgres default settings. Here's a breakdown of the requirements:
</p>

<ul>
	<li>1 million emails per day = ~11.57 emails per second</li>
	<li>
		You can achieve this with 12 email workers on a single app server (4GB, 2 vCPU) and default
		Postgres settings (max connections = 100)
	</li>
</ul>

<h3 id="tenmil-per-day">10 Million Emails Per Day</h3>

<p>
	Sending 10 million emails per day requires more resources. Here's a breakdown of the
	requirements:
</p>

<ul>
	<li>10 million emails per day = ~115.74 emails per second</li>
	<li>You need about 120 email workers to handle this volume.</li>
	<li>
		Assuming each send log (content, headers, etc.) takes about 10KB of storage, you will need
		about 1.2TB of storage per day for send logs alone.
	</li>
</ul>
