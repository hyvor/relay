package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"time"

	smtp "github.com/emersion/go-smtp"
)

var ErrSendEmailFailed = errors.New("failed to send email")

type SmtpStepName string

const (
	SmtpStepDial      SmtpStepName = "dial"
	SmtpStepHello     SmtpStepName = "hello"
	SmtpStepMail      SmtpStepName = "mail"
	SmtpStepRcpt      SmtpStepName = "rcpt"
	SmtpStepData      SmtpStepName = "data"
	SmtpStepDataWrite SmtpStepName = "write"
	SmtpStepQuit      SmtpStepName = "quit"
)

type LatencyDuration time.Duration

func (d LatencyDuration) MarshalJSON() ([]byte, error) {
	str := fmt.Sprintf("%dms", time.Duration(d).Milliseconds())
	return json.Marshal(str)
}

type SmtpLatency struct {
	start time.Time
	last  time.Time
	Steps map[SmtpStepName]LatencyDuration // ns durations
	Total LatencyDuration
}

func (s *SmtpLatency) RecordStep(step SmtpStepName) {
	stepTime := time.Since(s.start)
	s.Steps[step] = LatencyDuration(stepTime)
	s.last = time.Now()
	s.Total += LatencyDuration(stepTime)
}

func NewSmtpLatency() *SmtpLatency {
	return &SmtpLatency{
		start: time.Now(),
		last:  time.Now(),
		Steps: make(map[SmtpStepName]LatencyDuration),
	}
}

type SendResult struct {
	MxHosts     []string
	SentHost    string
	SmtpLatency map[string]*SmtpLatency // host -> latency
	Error       error
}

func sendEmail(
	message *EmailSendMessage,
	logger io.Writer,
) *SendResult {

	result := &SendResult{
		SmtpLatency: make(map[string]*SmtpLatency),
	}

	fmt.Fprintf(logger, "\n== New email ==\n")
	fmt.Fprintf(logger, "From: %s\n", message.From)
	fmt.Fprintf(logger, "To: %s\n", message.To)

	mxHosts, err := getMxHostsFromEmail(message.To)

	if err != nil {
		fmt.Fprintf(logger, "ERROR: %s\n", err)
		result.Error = err
		return result
	}

	fmt.Fprintf(logger, "INFO: MX records found: %v\n", mxHosts)

	result.MxHosts = mxHosts

	for _, host := range mxHosts {
		fmt.Fprintf(logger, "INFO: Sending to host: %s\n", host)

		err = sendEmailToHost(message, host, logger, result)

		if err == nil {
			result.SentHost = host
			fmt.Fprintf(logger, "INFO: Email successfully sent\n")

			resultsJson, _ := json.MarshalIndent(result, "", "  ")
			fmt.Fprintf(logger, "Result: %+v\n", string(resultsJson))
			return result
		} else {
			fmt.Fprintf(logger, "ERROR: Failed to send email to %s: %s\n", host, err)
		}
	}

	fmt.Fprintf(logger, "ERROR: All attempts to send email failed for %s", message.To)

	result.Error = ErrSendEmailFailed
	return result
}

func sendEmailToHost(
	message *EmailSendMessage,
	host string,
	logger io.Writer,
	result *SendResult,
) error {

	latency := NewSmtpLatency()
	result.SmtpLatency[host] = latency

	c, err := smtp.Dial(host + ":25")
	c.DebugWriter = logger
	if err != nil {
		fmt.Fprintf(logger, "ERROR: %s\n", err)
		return err
	}
	defer c.Close()

	latency.RecordStep(SmtpStepDial)

	if err := c.Hello("relay.hyvor.com"); err != nil {
		fmt.Fprintf(logger, "ERROR: EHLO failed - %s\n", err)
		return err
	}

	latency.RecordStep(SmtpStepHello)

	if err := c.Mail(message.From, nil); err != nil {
		fmt.Fprintf(logger, "ERROR: MAIL FROM failed - %s\n", err)
		return err
	}

	latency.RecordStep(SmtpStepMail)

	if err := c.Rcpt(message.To, nil); err != nil {
		fmt.Fprintf(logger, "ERROR: RCPT failed - %s\n", err)
		return err
	}

	latency.RecordStep(SmtpStepRcpt)

	w, err := c.Data()
	if err != nil {
		fmt.Fprintf(logger, "ERROR: DATA failed - %s\n", err)
		return err
	}

	latency.RecordStep(SmtpStepData)

	_, err = w.Write([]byte(message.RawEmail))
	if err != nil {
		fmt.Fprintf(logger, "ERROR: Writing data failed - %s\n", err)
		return err
	}

	latency.RecordStep(SmtpStepDataWrite)

	_ = c.Quit() // ignore QUIT error

	latency.RecordStep(SmtpStepQuit)

	return nil

}
