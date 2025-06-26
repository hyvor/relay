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

const MAX_SEND_TRIES = 8

type SmtpStepName string

const (
	SmtpStepDial      SmtpStepName = "dial"
	SmtpStepHello     SmtpStepName = "hello"
	SmtpStepStartTLS  SmtpStepName = "starttls"
	SmtpStepMail      SmtpStepName = "mail"
	SmtpStepRcpt      SmtpStepName = "rcpt"
	SmtpStepData      SmtpStepName = "data"
	SmtpStepDataClose SmtpStepName = "data_close"
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

type SmtpStep struct {
	Name      SmtpStepName
	Duration  LatencyDuration
	Command   string
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
	command string,
	replyCode int,
	replyText string,
) {

	step := &SmtpStep{
		Name:      name,
		Duration:  LatencyDuration(time.Since(c.StartTime)),
		Command:   command,
		ReplyCode: replyCode,
		ReplyText: replyText,
	}

	c.Steps = append(c.Steps, step)

}

type SendResult struct {
	ResolvedMxHosts   []string
	SentMxHost        string
	SmtpConversations map[string]*SmtpConversation
	Error             error
	ShouldRequeue     bool
}

func sendEmail(
	send *DbSend,
	logger io.Writer,
) *SendResult {

	result := &SendResult{
		ResolvedMxHosts:   make([]string, 0),
		SmtpConversations: make(map[string]*SmtpConversation),
	}

	fmt.Fprintf(logger, "\n== New email ==\n")
	fmt.Fprintf(logger, "From: %s\n", send.From)
	fmt.Fprintf(logger, "To: %s\n", send.To)

	mxHosts, err := getMxHostsFromEmail(send.To)

	if err != nil {
		fmt.Fprintf(logger, "ERROR: %s\n", err)
		result.Error = err
		return result
	}

	fmt.Fprintf(logger, "INFO: MX records found: %v\n", mxHosts)

	result.ResolvedMxHosts = mxHosts

	for _, host := range mxHosts {
		fmt.Fprintf(logger, "INFO: Sending to host: %s\n", host)

		conversation, err, errStatus := sendEmailToHost(send, host, logger)
		result.SmtpConversations[host] = conversation

		if err != nil {
			// a connection-level error happened
			// continue the loop to try the next host
			fmt.Fprintf(logger, "ERROR: Failed to send email to %s: %s\n", host, err)
		} else if errStatus > 0 {
			// an SMTP error happened (4xx/5xx)
			fmt.Fprintf(logger, "ERROR: SMTP error %d from %s: %s\n", errStatus, host, conversation.Steps[len(conversation.Steps)-1].ReplyText)

			if errStatus >= 400 && errStatus < 500 {

				// 4xx errors are usually temporary, requeue the email if we haven't reached the max tries

				if send.TryCount >= MAX_SEND_TRIES {
					result.Error = errors.New("maximum send attempts reached")
					fmt.Fprintf(logger, "ERROR: Maximum send attempts reached for %s\n", send.To)
					return result
				} else {
					fmt.Fprintf(logger, "INFO: Requeuing email due to 4xx error\n")
					result.ShouldRequeue = true
					return result
				}

			} else {
				result.Error = fmt.Errorf("SMTP error %d from %s: %s", errStatus, host, conversation.Steps[len(conversation.Steps)-1].ReplyText)
				fmt.Fprintf(logger, "ERROR: %s\n", result.Error)
				return result
			}

		} else {
			result.SentMxHost = host
			fmt.Fprintf(logger, "INFO: Email successfully sent\n")

			resultsJson, _ := json.MarshalIndent(result, "", "  ")
			fmt.Fprintf(logger, "Result: %+v\n", string(resultsJson))
			return result
		}
	}

	fmt.Fprintf(logger, "ERROR: All attempts to send email failed for %s", send.To)

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

// returns: conversation, error (network), error (smtp code)
func sendEmailToHost(
	message *DbSend,
	host string,
	logger io.Writer,
) (*SmtpConversation, error, int) {

	conversation := NewSmtpConversation()

	// STEP 0: Connect to SMTP server
	// ==============================
	c, err := createSmtpClient(host)
	if err != nil {
		fmt.Fprintf(logger, "ERROR: %s\n", err)
		return conversation, err, 0
	}
	defer c.Close()
	conversation.AddStep(SmtpStepDial, "", 0, "")

	// STEP 1: EHLO/HELO
	// =================
	helloResult := c.Hello("relay.hyvor.com")

	if helloResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: EHLO failed - %s\n", helloResult.Err)
		return conversation, helloResult.Err, 0
	}
	conversation.AddStep(SmtpStepHello, helloResult.Command, helloResult.Reply.Code, helloResult.Reply.Message)
	if !helloResult.CodeValid(250) {
		return conversation, nil, helloResult.Reply.Code
	}

	// STEP 2: STARTTLS
	// ============
	if ok, _ := c.Extension("STARTTLS"); ok {
		fmt.Fprintf(logger, "INFO: STARTTLS supported by %s\n", host)

		startTlsResult, ehloResult := c.StartTLS(&tls.Config{ServerName: host})

		if startTlsResult.Err != nil {
			fmt.Fprintf(logger, "ERROR: STARTTLS failed - %s\n", startTlsResult.Err)
			return conversation, startTlsResult.Err, 0
		}

		if ehloResult.Err != nil {
			fmt.Fprintf(logger, "ERROR: EHLO after STARTTLS failed - %s\n", ehloResult.Err)
			return conversation, ehloResult.Err, 0
		}

		conversation.AddStep(SmtpStepStartTLS, startTlsResult.Command, startTlsResult.Reply.Code, startTlsResult.Reply.Message)
		conversation.AddStep(SmtpStepHello, ehloResult.Command, ehloResult.Reply.Code, ehloResult.Reply.Message)

		if !startTlsResult.CodeValid(220) {
			fmt.Fprintf(logger, "ERROR: STARTTLS failed with code %d: %s\n", startTlsResult.Reply.Code, startTlsResult.Reply.Message)
			return conversation, nil, startTlsResult.Reply.Code
		}

		if !ehloResult.CodeValid(250) {
			fmt.Fprintf(logger, "ERROR: EHLO after STARTTLS failed with code %d: %s\n", ehloResult.Reply.Code, ehloResult.Reply.Message)
			return conversation, nil, ehloResult.Reply.Code
		}

		fmt.Fprintf(logger, "INFO: STARTTLS succeeded\n")
	} else {
		fmt.Fprintf(logger, "INFO: STARTTLS not supported by %s\n", host)
	}

	// STEP 3: MAIL FROM
	// ================
	mailResult := c.Mail(message.From)
	if mailResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: MAIL FROM failed - %s\n", mailResult.Err)
		return conversation, mailResult.Err, 0
	}
	conversation.AddStep(SmtpStepMail, mailResult.Command, mailResult.Reply.Code, mailResult.Reply.Message)
	if !mailResult.CodeValid(250) {
		fmt.Fprintf(logger, "ERROR: MAIL FROM failed with code %d: %s\n", mailResult.Reply.Code, mailResult.Reply.Message)
		return conversation, nil, mailResult.Reply.Code
	}

	// STEP 4: RCPT TO
	// ===============
	rcptResult := c.Rcpt(message.To)
	if rcptResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: RCPT failed - %s\n", rcptResult.Err)
		return conversation, rcptResult.Err, 0
	}
	conversation.AddStep(SmtpStepRcpt, rcptResult.Command, rcptResult.Reply.Code, rcptResult.Reply.Message)
	if !rcptResult.CodeValid(25) {
		fmt.Fprintf(logger, "ERROR: RCPT TO failed with code %d: %s\n", rcptResult.Reply.Code, rcptResult.Reply.Message)
		return conversation, nil, rcptResult.Reply.Code
	}

	// STEP 5: DATA
	// ============
	w, dataResult := c.Data()
	if dataResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: DATA failed - %s\n", dataResult.Err)
		return conversation, dataResult.Err, 0
	}
	conversation.AddStep(SmtpStepData, dataResult.Command, dataResult.Reply.Code, dataResult.Reply.Message)
	if !dataResult.CodeValid(354) {
		fmt.Fprintf(logger, "ERROR: DATA failed with code %d: %s\n", dataResult.Reply.Code, dataResult.Reply.Message)
		return conversation, nil, dataResult.Reply.Code
	}
	_, err = w.Write([]byte(message.RawEmail))
	if err != nil {
		fmt.Fprintf(logger, "ERROR: Writing data failed - %s\n", err)
		return conversation, err, 0
	}

	// STEP 5.1: Close DATA
	// ============
	closeResult := w.Close()
	if closeResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: Closing data failed - %s\n", err)
		return conversation, err, 0
	}
	conversation.AddStep(SmtpStepDataClose, closeResult.Command, closeResult.Reply.Code, closeResult.Reply.Message)
	if !closeResult.CodeValid(250) {
		fmt.Fprintf(logger, "ERROR: Closing data failed with code %d: %s\n", closeResult.Reply.Code, closeResult.Reply.Message)
		return conversation, nil, closeResult.Reply.Code
	}

	// STEP 6: QUIT
	// ============
	quitResult := c.Quit() // ignore QUIT error
	conversation.AddStep(SmtpStepQuit, quitResult.Command, quitResult.Reply.Code, quitResult.Reply.Message)

	return conversation, nil, 0

}
