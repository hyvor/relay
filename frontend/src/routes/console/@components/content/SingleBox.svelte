<div class="single-box">
	<div class="inner hds-box" {...$$restProps}>
		<slot />
	</div>
</div>

<style lang="scss">
	.single-box {
		height: 100%;
		flex: 1;
		display: flex;
		flex-direction: column;
		--padding-x: 25px;
	}
	.inner {
		display: flex;
		flex: 1;
		flex-direction: column;
		position: relative;
		min-height: 0;
	}
</style>
