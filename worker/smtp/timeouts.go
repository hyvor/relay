package smtp

import (
	"errors"
	"net"
	"time"
)

// Timeouts are applied per phase of the SMTP conversation (not to the whole session).
// See RFC 5321 §4.5.3.2 for the minimum values.
type Timeouts struct {
	Greeting  time.Duration // 220 greeting (§4.5.3.2.1)
	Command   time.Duration // EHLO/HELO, STARTTLS (incl. TLS handshake), MAIL (§4.5.3.2.2), RCPT (§4.5.3.2.3), RSET, NOOP
	DataInit  time.Duration // 354 reply to DATA (§4.5.3.2.4)
	DataBlock time.Duration // each body block write (§4.5.3.2.5)
	DataTerm  time.Duration // reply to the final "." (§4.5.3.2.6)
	Quit      time.Duration // RFC is silent; the QUIT result is ignored anyway
}

// DefaultTimeouts are the RFC 5321 §4.5.3.2 minimums.
var DefaultTimeouts = Timeouts{
	Greeting:  5 * time.Minute,
	Command:   5 * time.Minute,
	DataInit:  2 * time.Minute,
	DataBlock: 3 * time.Minute,
	DataTerm:  10 * time.Minute,
	Quit:      30 * time.Second,
}

type TimeoutPhase string

const (
	TimeoutPhaseGreeting  TimeoutPhase = "greeting"
	TimeoutPhaseCommand   TimeoutPhase = "command"
	TimeoutPhaseDataInit  TimeoutPhase = "data_init"
	TimeoutPhaseDataBlock TimeoutPhase = "data_block"
	TimeoutPhaseDataTerm  TimeoutPhase = "data_term"
	TimeoutPhaseQuit      TimeoutPhase = "quit"
)

// TimeoutError is returned when a phase deadline is exceeded.
// It implements net.Error, so callers that only check for network errors keep working.
type TimeoutError struct {
	Phase TimeoutPhase
	Err   error
}

func (e *TimeoutError) Error() string {
	return "smtp: " + string(e.Phase) + " timeout: " + e.Err.Error()
}

func (e *TimeoutError) Unwrap() error   { return e.Err }
func (e *TimeoutError) Timeout() bool   { return true }
func (e *TimeoutError) Temporary() bool { return true }

// wraps err in a TimeoutError if it is a network timeout
func wrapTimeout(phase TimeoutPhase, err error) error {
	var netErr net.Error
	if err != nil && errors.As(err, &netErr) && netErr.Timeout() {
		return &TimeoutError{Phase: phase, Err: err}
	}
	return err
}
