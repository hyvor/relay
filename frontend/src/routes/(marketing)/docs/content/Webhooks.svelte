<script>
	import { CodeBlock } from '@hyvor/design/components';
</script>

<h1>Webhooks</h1>

<h2 id="events">Events</h2>

<p>
	<!--  -->
</p>

<CodeBlock
	code={`
"send.accepted"
"send.requeued"
"send.failed"
"send.bounced"
"send.complained"

"suppression.created"
"suppression.deleted"

"domain.created"
"domain.verified"
"domain.deleted"
`}
	language="ts"
/>
