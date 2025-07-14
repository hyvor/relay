<script>
	import SingleBox from '../SingleBox.svelte';
</script>

<SingleBox>
	<div class="health-checks">
		<div class="title">Health Checks</div>

		<div class="checks">
			<div class="check">
				<div class="dot"></div>
				<div>All queues have at least one IP address assigned.</div>
			</div>
			<div class="check">
				<div class="dot"></div>
				<div>All IP addresses have correct PTR records.</div>
			</div>
			<div class="check">
				<div class="dot"></div>
				<div>All PTR records resolve to the correct IP addresses.</div>
			</div>
			<div class="check">
				<div class="dot"></div>
				<div>All IP addresses are added in the SPF record.</div>
			</div>
			<div class="check">
				<div class="dot"></div>
				<div>None of the IP addresses are blacklisted on any major blacklist.</div>
			</div>
		</div>
	</div>
</SingleBox>

<style>
	.health-checks {
		padding: 30px;
		border-bottom: 1px solid var(--border);
	}

	.title {
		font-size: 18px;
		font-weight: bold;
		margin-bottom: 15px;
	}

	.checks {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}

	.check {
		display: flex;
		align-items: center;
	}

	.dot {
		width: 10px;
		height: 10px;
		border-radius: 50%;
		background-color: green;
		margin-right: 10px;
	}

	.check div {
		font-size: 14px;
		color: var(--text-light);
	}
</style>
