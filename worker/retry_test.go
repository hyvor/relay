package main

import (
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
