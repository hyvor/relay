package main

import (
	"fmt"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

// withJitterSource swaps the jitter source for the duration of the test.
func withJitterSource(t *testing.T, values ...float64) {
	t.Helper()

	original := jitterSource
	i := 0
	jitterSource = func() float64 {
		v := values[i%len(values)]
		i++
		return v
	}

	t.Cleanup(func() { jitterSource = original })
}

func TestJitterDurationStaysWithinFactor(t *testing.T) {

	base := time.Hour
	min := time.Duration(float64(base) * (1 - retryJitterFactor))
	max := time.Duration(float64(base) * (1 + retryJitterFactor))

	for i := 0; i < 1000; i++ {
		jittered := jitterDuration(base)

		assert.GreaterOrEqual(t, jittered, min)
		assert.LessOrEqual(t, jittered, max)
	}

}

func TestJitterDurationEdges(t *testing.T) {

	base := time.Hour
	min := time.Duration(float64(base) * (1 - retryJitterFactor))
	max := time.Duration(float64(base) * (1 + retryJitterFactor))

	// 0.0 -> lower edge, 0.5 -> no change, ~1.0 -> upper edge
	withJitterSource(t, 0, 0.5, 0.999999)

	assert.Equal(t, min, jitterDuration(base))
	assert.Equal(t, base, jitterDuration(base))
	assert.InDelta(t, float64(max), float64(jitterDuration(base)), float64(time.Second))

}

func TestJitterDurationSpreadsValues(t *testing.T) {

	// the point of jitter is that two calls do not land on the same instant
	seen := make(map[time.Duration]bool)

	for i := 0; i < 50; i++ {
		seen[jitterDuration(time.Hour)] = true
	}

	assert.Greater(t, len(seen), 1, "jitter should produce more than one distinct delay")

}

func TestJitterDurationIgnoresNonPositive(t *testing.T) {

	assert.Equal(t, time.Duration(0), jitterDuration(0))
	assert.Equal(t, -time.Second, jitterDuration(-time.Second))

}

func TestJitterDurationKeepsSubSecondDelaysSubSecond(t *testing.T) {

	// the reconnect loop starts at 100ms, so jitter must not inflate short
	// delays; only sqlInterval() has a one-second floor, because that is a
	// Postgres interval concern
	withJitterSource(t, 0)
	assert.Equal(t, 85*time.Millisecond, jitterDuration(100*time.Millisecond))

	withJitterSource(t, 0.5)
	assert.Equal(t, 100*time.Millisecond, jitterDuration(100*time.Millisecond))

}

func TestJitterDurationStaysPositive(t *testing.T) {

	withJitterSource(t, 0)

	assert.Positive(t, jitterDuration(1))

}

func TestSqlInterval(t *testing.T) {

	assert.Equal(t, "3600 seconds", sqlInterval(time.Hour))
	assert.Equal(t, "900 seconds", sqlInterval(15*time.Minute))
	assert.Equal(t, "86400 seconds", sqlInterval(24*time.Hour))

	// rounds to the nearest second
	assert.Equal(t, "2 seconds", sqlInterval(1500*time.Millisecond))

	// never emits a zero or negative interval, which Postgres would accept
	// but which would mean "retry immediately, forever"
	assert.Equal(t, "1 seconds", sqlInterval(0))
	assert.Equal(t, "1 seconds", sqlInterval(-time.Hour))

}

func TestJitteredSqlInterval(t *testing.T) {

	withJitterSource(t, 0.5)
	assert.Equal(t, "3600 seconds", jitteredSqlInterval(time.Hour))

	withJitterSource(t, 0)
	assert.Equal(t, "3060 seconds", jitteredSqlInterval(time.Hour))

}

func TestWebhookRetryDelay(t *testing.T) {

	// coupling for safety
	delays := map[int]time.Duration{
		0: 1 * time.Minute,
		1: 5 * time.Minute,
		2: 15 * time.Minute,
		3: 1 * time.Hour,
		4: 4 * time.Hour,
		5: 24 * time.Hour,
	}

	for try, expected := range delays {
		assert.Equal(t, expected, getWebhookRetryDelay(try))
	}

	assert.Equal(t, 24*time.Hour, getWebhookRetryDelay(99))

}

func TestWebhookRetryIntervalKeepsSendAfterWhenDone(t *testing.T) {

	// delivered: nothing left to schedule
	assert.Equal(t, "send_after", getWebhookRetryInterval(0, true))

	// out of retries: nothing left to schedule
	assert.Equal(t, "send_after", getWebhookRetryInterval(WEBHOOKS_MAX_RETRIES, false))
	assert.Equal(t, "send_after", getWebhookRetryInterval(WEBHOOKS_MAX_RETRIES+1, false))

}

func TestWebhookRetryIntervalIsJittered(t *testing.T) {

	withJitterSource(t, 0.5)
	assert.Equal(t, "NOW() + INTERVAL '60 seconds'", getWebhookRetryInterval(0, false))
	assert.Equal(t, "NOW() + INTERVAL '3600 seconds'", getWebhookRetryInterval(3, false))

	for try := 0; try < WEBHOOKS_MAX_RETRIES; try++ {
		base := getWebhookRetryDelay(try)
		min := time.Duration(float64(base) * (1 - retryJitterFactor))
		max := time.Duration(float64(base) * (1 + retryJitterFactor))

		for i := 0; i < 100; i++ {
			interval := getWebhookRetryInterval(try, false)

			var seconds int64
			_, err := fmt.Sscanf(interval, "NOW() + INTERVAL '%d seconds'", &seconds)
			assert.NoError(t, err)

			assert.GreaterOrEqual(t, time.Duration(seconds)*time.Second, min)
			assert.LessOrEqual(t, time.Duration(seconds)*time.Second, max)
		}
	}

}
