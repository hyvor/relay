package main

import (
	"fmt"
	"math/rand"
	"time"
)

// retryJitterFactor is the maximum fraction a retry delay is allowed to move
// away from its base value, in either direction. 0.15 means a "1 hour" retry
// actually fires somewhere in [51m, 69m].
//
// Without jitter, every send (or webhook delivery) that fails within the same
// tick is rescheduled to the exact same instant, so the whole batch stampedes
// the remote server again on every retry round, and keeps doing so for as long
// as the ladder lasts. Spreading the wake-ups breaks that synchronization.
const retryJitterFactor = 0.15

// jitterSource returns a value in [0, 1). It is a variable so tests can make
// the jitter deterministic.
var jitterSource = rand.Float64

// jitterDuration spreads d by up to +/- retryJitterFactor.
//
// Non-positive durations are returned untouched: a zero delay means "retry
// immediately" and jittering it into a negative value would be meaningless.
func jitterDuration(d time.Duration) time.Duration {
	if d <= 0 {
		return d
	}

	// jitterSource() is [0, 1), so offset is [-factor, +factor)
	offset := (jitterSource()*2 - 1) * retryJitterFactor
	jittered := time.Duration(float64(d) * (1 + offset))

	// a positive delay must stay positive, however small it started
	if jittered < 1 {
		return 1
	}

	return jittered
}

// sqlInterval formats d as a Postgres interval literal.
//
// The result is concatenated into SQL by callers, so it deliberately formats
// from an int and never interpolates caller-supplied text.
func sqlInterval(d time.Duration) string {
	seconds := int64(d.Round(time.Second) / time.Second)
	if seconds < 1 {
		seconds = 1
	}
	return fmt.Sprintf("%d seconds", seconds)
}

// jitteredSqlInterval is the combination used by every retry ladder: take a
// base delay, spread it, and render it as an interval literal.
func jitteredSqlInterval(d time.Duration) string {
	return sqlInterval(jitterDuration(d))
}
