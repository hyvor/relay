package main

import (
	"crypto/tls"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"time"

	smtp "github.com/hyvor/relay/worker/smtp"
)

var ErrSendEmailFailed = errors.New("failed to send email")

type SmtpStepName string

const (
	SmtpStepDial     SmtpStepName = "dial"
	SmtpStepHello    SmtpStepName = "hello"
	SmtpStepStartTLS SmtpStepName = "starttls"
	SmtpStepMail     SmtpStepName = "mail"
	SmtpStepRcpt     SmtpStepName = "rcpt"
	SmtpStepData     SmtpStepName = "data"
	SmtpStepQuit     SmtpStepName = "quit"
	SmtpStepClose    SmtpStepName = "close"
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

type SmtpStep struct {
	Name      SmtpStepName
	Duration  LatencyDuration
	ReplyCode int
	ReplyText string
}

type SmtpConversation struct {
	StartTime time.Time
	Steps     []*SmtpStep
}

func NewSmtpConversation() *SmtpConversation {
	return &SmtpConversation{
		StartTime: time.Now(),
		Steps:     make([]*SmtpStep, 0),
	}
}

func (c *SmtpConversation) AddStep(
	name SmtpStepName,
	replyCode int,
	replyText string,
) {

	step := &SmtpStep{
		Name:      name,
		Duration:  LatencyDuration(time.Since(c.StartTime)),
		ReplyCode: replyCode,
		ReplyText: replyText,
	}

	c.Steps = append(c.Steps, step)

}

type SendResult struct {
	ResolvedMxHosts   []string
	SentMxHost        string
	SmtpSteps         map[string][]*SmtpStep // host -> steps
	SmtpConversations map[string]*SmtpConversation
	Error             error
}

func sendEmail(
	message *DbSend,
	logger io.Writer,
) *SendResult {

	result := &SendResult{
		SmtpSteps: make(map[string][]*SmtpStep),
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

	result.ResolvedMxHosts = mxHosts

	// TODO: we should only try the next host if the previous one fails due to a network error, not a 4xx/5xx SMTP error
	for _, host := range mxHosts {
		fmt.Fprintf(logger, "INFO: Sending to host: %s\n", host)

		err = sendEmailToHost(message, host, logger, result)

		if err == nil {
			result.SentMxHost = host
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

var createSmtpClient = func(host string) (*smtp.Client, error) {

	conn, err := net.Dial("tcp", host+":25")

	if err != nil {
		return nil, fmt.Errorf("failed to connect to SMTP server %s: %w", host, err)
	}

	client, err := smtp.NewClient(conn, host)

	if err != nil {
		return nil, fmt.Errorf("failed to create SMTP client for %s: %w", host, err)
	}

	return client, nil
}

func sendEmailToHost(
	message *DbSend,
	host string,
	logger io.Writer,
) (*SmtpConversation, error) {

	conversation := NewSmtpConversation()

	// STEP 0: Connect to SMTP server
	// ==============================
	c, err := createSmtpClient(host)
	if err != nil {
		fmt.Fprintf(logger, "ERROR: %s\n", err)
		return conversation, err
	}
	defer c.Close()
	conversation.AddStep(SmtpStepDial, 0, "")

	// STEP 1: EHLO/HELO
	// =================
	helloErr := c.Hello("relay.hyvor.com")
	if helloErr != nil {
		fmt.Fprintf(logger, "ERROR: EHLO failed - %s\n", err)
		return conversation, err
	}
	conversation.AddStep(SmtpStepHello, 0, "")

	if ok, _ := c.Extension("STARTTLS"); ok {
		fmt.Fprintf(logger, "INFO: STARTTLS supported by %s\n", host)

		if err := c.StartTLS(&tls.Config{ServerName: host}); err != nil {
			fmt.Fprintf(logger, "ERROR: STARTTLS failed - %s\n", err)
			return err
		}
		fmt.Fprintf(logger, "INFO: STARTTLS succeeded\n")

		latency.RecordStep(SmtpStepStartTLS)
	} else {
		fmt.Fprintf(logger, "INFO: STARTTLS not supported by %s\n", host)
	}

	if err := c.Mail(message.From); err != nil {
		fmt.Fprintf(logger, "ERROR: MAIL FROM failed - %s\n", err)
		return err
	}

	latency.RecordStep(SmtpStepMail)

	if err := c.Rcpt(message.To); err != nil {
		fmt.Fprintf(logger, "ERROR: RCPT failed - %s\n", err)
		return err
	}

	latency.RecordStep(SmtpStepRcpt)

	w, err := c.Data()
	if err != nil {
		fmt.Fprintf(logger, "ERROR: DATA failed - %s\n", err)
		return err
	}

	_, err = w.Write([]byte(message.RawEmail))
	if err != nil {
		fmt.Fprintf(logger, "ERROR: Writing data failed - %s\n", err)
		return err
	}

	w.Close() // TODO: error handling

	latency.RecordStep(SmtpStepData)

	_ = c.Quit() // ignore QUIT error

	latency.RecordStep(SmtpStepQuit)

	return nil

}
