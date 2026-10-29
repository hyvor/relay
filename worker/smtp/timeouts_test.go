package smtp

import (
	"bufio"
	"errors"
	"io"
	"net"
	"net/textproto"
	"strings"
	"testing"
	"time"
)

func uniformTimeouts(d time.Duration) Timeouts {
	return Timeouts{
		Greeting:  d,
		Command:   d,
		DataInit:  d,
		DataBlock: d,
		DataTerm:  d,
		Quit:      d,
	}
}

// pipeClient connects a client and a scripted server over net.Pipe (which supports deadlines).
// serve runs in a goroutine; it should write the greeting itself.
func pipeClient(t *testing.T, timeouts Timeouts, serve func(srv *textproto.Conn)) (*Client, error) {
	t.Helper()
	clientConn, serverConn := net.Pipe()
	t.Cleanup(func() {
		clientConn.Close()
		serverConn.Close()
	})
	go serve(textproto.NewConn(serverConn))
	return NewClient(clientConn, "fake.host", timeouts)
}

func assertTimeout(t *testing.T, err error, phase TimeoutPhase) {
	t.Helper()
	if err == nil {
		t.Fatalf("expected %s timeout, got nil", phase)
	}
	var timeoutErr *TimeoutError
	if !errors.As(err, &timeoutErr) {
		t.Fatalf("expected *TimeoutError, got %T: %v", err, err)
	}
	if timeoutErr.Phase != phase {
		t.Fatalf("expected phase %s, got %s", phase, timeoutErr.Phase)
	}
	var netErr net.Error
	if !errors.As(err, &netErr) || !netErr.Timeout() {
		t.Fatalf("expected a net.Error with Timeout() == true, got %v", err)
	}
}

func TestTimeout_Greeting(t *testing.T) {
	_, err := pipeClient(t, uniformTimeouts(50*time.Millisecond), func(srv *textproto.Conn) {
		// never send the greeting
		io.Copy(io.Discard, srv.R)
	})
	assertTimeout(t, err, TimeoutPhaseGreeting)
}

// scripted server that replies normally until it reaches stallAt
func stallingServer(stallAt string) func(srv *textproto.Conn) {
	return func(srv *textproto.Conn) {
		srv.PrintfLine("220 hello")
		for {
			line, err := srv.ReadLine()
			if err != nil {
				return
			}
			verb := strings.ToUpper(strings.SplitN(line, " ", 2)[0])

			if verb == stallAt {
				// read but never reply
				io.Copy(io.Discard, srv.R)
				return
			}

			switch verb {
			case "EHLO":
				srv.PrintfLine("250 hello")
			case "MAIL", "RCPT":
				srv.PrintfLine("250 ok")
			case "DATA":
				srv.PrintfLine("354 go ahead")
				if stallAt == "BODY" {
					// stop reading; the client's body writes block
					select {}
				}
				if _, err := io.ReadAll(srv.DotReader()); err != nil {
					return
				}
				if stallAt == "DOT" {
					io.Copy(io.Discard, srv.R)
					return
				}
				srv.PrintfLine("250 queued")
			case "QUIT":
				srv.PrintfLine("221 bye")
				return
			}
		}
	}
}

func TestTimeout_Phases(t *testing.T) {
	cases := []struct {
		stallAt string
		phase   TimeoutPhase
	}{
		{"EHLO", TimeoutPhaseCommand},
		{"MAIL", TimeoutPhaseCommand},
		{"RCPT", TimeoutPhaseCommand},
		{"DATA", TimeoutPhaseDataInit},
		{"BODY", TimeoutPhaseDataBlock},
		{"DOT", TimeoutPhaseDataTerm},
		{"QUIT", TimeoutPhaseQuit},
	}

	// larger than the DotWriter buffer, so body writes reach the connection
	body := strings.Repeat("x", 100*1024)

	for _, tc := range cases {
		t.Run(tc.stallAt, func(t *testing.T) {
			c, err := pipeClient(t, uniformTimeouts(50*time.Millisecond), stallingServer(tc.stallAt))
			if err != nil {
				t.Fatalf("NewClient: %v", err)
			}

			err = func() error {
				if r := c.Hello("localhost"); r.Err != nil {
					return r.Err
				}
				if r := c.Mail("a@example.com"); r.Err != nil {
					return r.Err
				}
				if r := c.Rcpt("b@example.com"); r.Err != nil {
					return r.Err
				}
				w, r := c.Data()
				if r.Err != nil {
					return r.Err
				}
				if _, err := w.Write([]byte(body)); err != nil {
					return err
				}
				if r := w.Close(); r.Err != nil {
					return r.Err
				}
				return c.Quit().Err
			}()

			assertTimeout(t, err, tc.phase)
		})
	}
}

// EHLO timing out must not fall back to HELO (which would wait for another full timeout)
func TestTimeout_EhloDoesNotFallBackToHelo(t *testing.T) {
	lines := make(chan string, 10)
	c, err := pipeClient(t, uniformTimeouts(50*time.Millisecond), func(srv *textproto.Conn) {
		srv.PrintfLine("220 hello")
		for {
			line, err := srv.ReadLine()
			if err != nil {
				return
			}
			lines <- line
		}
	})
	if err != nil {
		t.Fatalf("NewClient: %v", err)
	}

	assertTimeout(t, c.Hello("localhost").Err, TimeoutPhaseCommand)

	time.Sleep(20 * time.Millisecond)
	close(lines)
	for line := range lines {
		if strings.HasPrefix(line, "HELO") {
			t.Fatalf("unexpected HELO after EHLO timeout")
		}
	}
}

// a server that answers slowly, but within the timeouts, succeeds
func TestTimeout_SlowServerWithinTimeout(t *testing.T) {
	c, err := pipeClient(t, uniformTimeouts(500*time.Millisecond), func(srv *textproto.Conn) {
		reply := func(line string) {
			time.Sleep(50 * time.Millisecond)
			srv.PrintfLine("%s", line)
		}
		reply("220 hello")
		for {
			line, err := srv.ReadLine()
			if err != nil {
				return
			}
			switch strings.ToUpper(strings.SplitN(line, " ", 2)[0]) {
			case "EHLO":
				reply("250 hello")
			case "MAIL", "RCPT":
				reply("250 ok")
			case "DATA":
				reply("354 go ahead")
				io.ReadAll(srv.DotReader())
				reply("250 queued")
			case "QUIT":
				reply("221 bye")
				return
			}
		}
	})
	if err != nil {
		t.Fatalf("NewClient: %v", err)
	}

	if r := c.Hello("localhost"); r.Err != nil {
		t.Fatalf("EHLO: %v", r.Err)
	}
	if r := c.Mail("a@example.com"); r.Err != nil {
		t.Fatalf("MAIL: %v", r.Err)
	}
	if r := c.Rcpt("b@example.com"); r.Err != nil {
		t.Fatalf("RCPT: %v", r.Err)
	}
	w, r := c.Data()
	if r.Err != nil {
		t.Fatalf("DATA: %v", r.Err)
	}
	if _, err := w.Write([]byte("Subject: hi\r\n\r\nbody")); err != nil {
		t.Fatalf("write: %v", err)
	}
	if r := w.Close(); r.Err != nil || !r.CodeValid(250) {
		t.Fatalf("data close: %v", r.Err)
	}
	if r := c.Quit(); r.Err != nil {
		t.Fatalf("QUIT: %v", r.Err)
	}
}

type slowReader struct {
	r     io.Reader
	delay time.Duration
	max   int
}

func (s *slowReader) Read(p []byte) (int, error) {
	time.Sleep(s.delay)
	if len(p) > s.max {
		p = p[:s.max]
	}
	return s.r.Read(p)
}

// each body block gets a fresh deadline, so a slow but progressing server
// is not cut off even if the whole body takes longer than DataBlock
func TestTimeout_BodyBlocksGetFreshDeadline(t *testing.T) {
	const dataBlock = 300 * time.Millisecond
	timeouts := uniformTimeouts(time.Second)
	timeouts.DataBlock = dataBlock

	// the client flushes in 4 KiB writes; reading 4 KiB per 5ms gives
	// ~80ms per 64 KiB block (within DataBlock) and ~480ms in total (over DataBlock)
	body := strings.Repeat("x", 6*dataBlockSize)

	start := time.Now()

	c, err := pipeClient(t, timeouts, func(srv *textproto.Conn) {
		srv.PrintfLine("220 hello")
		srv.ReadLine() // MAIL
		srv.PrintfLine("250 ok")
		srv.ReadLine() // RCPT
		srv.PrintfLine("250 ok")
		srv.ReadLine() // DATA
		srv.PrintfLine("354 go ahead")

		slow := textproto.NewReader(bufio.NewReaderSize(&slowReader{r: srv.R, delay: 5 * time.Millisecond, max: 4 * 1024}, 4*1024))
		io.ReadAll(slow.DotReader())
		srv.PrintfLine("250 queued")
	})
	if err != nil {
		t.Fatalf("NewClient: %v", err)
	}
	c.didHello = true

	if r := c.Mail("a@example.com"); r.Err != nil {
		t.Fatalf("MAIL: %v", r.Err)
	}
	if r := c.Rcpt("b@example.com"); r.Err != nil {
		t.Fatalf("RCPT: %v", r.Err)
	}
	w, r := c.Data()
	if r.Err != nil {
		t.Fatalf("DATA: %v", r.Err)
	}
	if _, err := w.Write([]byte(body)); err != nil {
		t.Fatalf("write: %v", err)
	}
	if r := w.Close(); r.Err != nil || !r.CodeValid(250) {
		t.Fatalf("data close: %v", r.Err)
	}

	if elapsed := time.Since(start); elapsed <= dataBlock {
		t.Fatalf("test is not meaningful: the body took %v, which is within a single DataBlock (%v)", elapsed, dataBlock)
	}
}

// each phase sets the expected deadline on the connection
func TestTimeout_DeadlinesPerPhase(t *testing.T) {
	timeouts := Timeouts{
		Greeting:  1 * time.Hour,
		Command:   2 * time.Hour,
		DataInit:  3 * time.Hour,
		DataBlock: 4 * time.Hour,
		DataTerm:  5 * time.Hour,
		Quit:      6 * time.Hour,
	}

	server := strings.Join(strings.Split(`220 hello
250 hello
250 ok
250 ok
354 go ahead
250 queued
221 bye
`, "\n"), "\r\n")

	var deadline time.Time
	var cmdbuf strings.Builder
	fake := faker{
		ReadWriter: bufio.NewReadWriter(bufio.NewReader(strings.NewReader(server)), bufio.NewWriter(&cmdbuf)),
		deadline:   &deadline,
	}

	assertDeadline := func(step string, expected time.Duration) {
		t.Helper()
		diff := time.Until(deadline) - expected
		if diff > time.Minute || diff < -time.Minute {
			t.Fatalf("%s: expected deadline ~%v from now, got %v", step, expected, time.Until(deadline))
		}
	}

	c, err := NewClient(fake, "fake.host", timeouts)
	if err != nil {
		t.Fatalf("NewClient: %v", err)
	}
	assertDeadline("greeting", timeouts.Greeting)

	c.Hello("localhost")
	assertDeadline("EHLO", timeouts.Command)

	c.Mail("a@example.com")
	assertDeadline("MAIL", timeouts.Command)

	c.Rcpt("b@example.com")
	assertDeadline("RCPT", timeouts.Command)

	w, _ := c.Data()
	assertDeadline("DATA", timeouts.DataInit)

	w.Write([]byte("body"))
	assertDeadline("body", timeouts.DataBlock)

	w.Close()
	assertDeadline("data close", timeouts.DataTerm)

	c.Quit()
	assertDeadline("QUIT", timeouts.Quit)
}
