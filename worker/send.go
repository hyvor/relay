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

	// network/transport error
	// nil if the message was sent successfully (regardless of SMTP status)
	Error error

	// 0 = no SMTP error
	// > 0 = SMTP error code
	// can be set in the middle of the conversation
	// e.g. non-250 on EHLO
	SmtpErrorStatus int

	Steps []*SmtpStep
}

func (s SmtpConversation) MarshalJSON() ([]byte, error) {
	type SmtpConversationAlias SmtpConversation
	aux := struct {
		*SmtpConversationAlias
		Error string
	}{
		SmtpConversationAlias: (*SmtpConversationAlias)(&s),
	}

	if s.Error != nil {
		aux.Error = s.Error.Error()
	}

	return json.Marshal(aux)
}

func NewSmtpConversation() *SmtpConversation {
	return &SmtpConversation{
		StartTime:       time.Now(),
		Error:           nil,
		SmtpErrorStatus: 0,
		Steps:           make([]*SmtpStep, 0),
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
	SentFromIpId      int
	SentFromIp        string
	ResolvedMxHosts   []string
	SentMxHost        string
	SmtpConversations map[string]*SmtpConversation
	QueueName         string
	Error             error
	ShouldRequeue     bool
	Duration          time.Duration
}

func (r *SendResult) ToStatus() string {
	status := "accepted"
	if r.Error != nil {
		status = "bounced"
	} else if r.ShouldRequeue {
		status = "deferred"
	}
	return status
}

func sendEmail(
	send *DbSend,
	instanceDomain string,
	ipId int,
	ip string,
	ptr string,
	logger io.Writer,
) *SendResult {

	startTime := time.Now()

	result := &SendResult{
		SentFromIpId:      ipId,
		SentFromIp:        ip,
		ResolvedMxHosts:   make([]string, 0),
		SmtpConversations: make(map[string]*SmtpConversation),
		QueueName:         send.QueueName,
	}

	defer func() {
		duration := time.Since(startTime)
		result.Duration = duration
	}()

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
		fmt.Fprintf(logger, "INFO: Sending to host %s from IP %s\n", host, ip)

		conversation := sendEmailToHost(send, host, instanceDomain, ip, ptr, logger)
		result.SmtpConversations[host] = conversation

		if conversation.Error != nil {
			// a connection-level error happened
			// continue the loop to try the next host
			fmt.Fprintf(logger, "ERROR: Failed to send email to %s: %s\n", host, conversation.Error)
		} else if conversation.SmtpErrorStatus > 0 {

			// an SMTP error happened (4xx/5xx)
			fmt.Fprintf(logger, "ERROR: SMTP error %d from %s: %s\n", conversation.SmtpErrorStatus, host, conversation.Steps[len(conversation.Steps)-1].ReplyText)

			if conversation.SmtpErrorStatus >= 400 && conversation.SmtpErrorStatus < 500 {

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

			} else if conversation.SmtpErrorStatus >= 500 {

				// 5xx errors are permanent, do not requeue
				result.Error = fmt.Errorf(
					"SMTP error %d from %s: %s",
					conversation.SmtpErrorStatus,
					host,
					conversation.Steps[len(conversation.Steps)-1].ReplyText,
				)
				fmt.Fprintf(logger, "ERROR: %s\n", result.Error)

				return result
			}

			// for any other error, we can continue to the next host
			fmt.Fprintf(logger, "INFO: Continuing to next host due to SMTP error %d\n", conversation.SmtpErrorStatus)
			continue

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

var createSmtpClient = func(host string, localIp string) (*smtp.Client, error) {

	// TODO: comment from ResolveTCPAddr
	// "The address parameter can use a host name, but this is not
	// recommended, because it will return at most one of the host name's
	// IP addresses."
	// So, we might need to resolve A records manually first.

	remoteAddr, err := net.ResolveTCPAddr("tcp", host+":25")
	if err != nil {
		return nil, fmt.Errorf("failed to resolve remote address %s: %w", host, err)
	}

	localAddr := &net.TCPAddr{
		IP:   net.ParseIP(localIp),
		Port: 0, // Let the OS choose an available local port
	}

	dialer := &net.Dialer{
		LocalAddr: localAddr,
		Timeout:   30 * time.Second,
		KeepAlive: 30 * time.Second,
	}

	conn, err := dialer.Dial("tcp", remoteAddr.String())

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
	send *DbSend,
	host string,
	instanceDomain string,
	ip string,
	ptr string,
	logger io.Writer,
) *SmtpConversation {

	conversation := NewSmtpConversation()

	// STEP 0: Connect to SMTP server
	// ==============================
	c, err := createSmtpClient(host, ip)
	if err != nil {
		fmt.Fprintf(logger, "ERROR: %s\n", err)
		conversation.Error = err
		return conversation
	}
	defer c.Close()
	conversation.AddStep(SmtpStepDial, "", 0, "")

	// STEP 1: EHLO/HELO
	// =================
	helloResult := c.Hello(ptr)

	if helloResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: EHLO failed - %s\n", helloResult.Err)
		conversation.Error = helloResult.Err
		return conversation
	}
	conversation.AddStep(SmtpStepHello, helloResult.Command, helloResult.Reply.Code, helloResult.Reply.Message)
	if !helloResult.CodeValid(250) {
		conversation.SmtpErrorStatus = helloResult.Reply.Code
		return conversation
	}

	// STEP 2: STARTTLS
	// ============
	if ok, _ := c.Extension("STARTTLS"); ok {
		fmt.Fprintf(logger, "INFO: STARTTLS supported by %s\n", host)

		startTlsResult, ehloResult := c.StartTLS(&tls.Config{ServerName: host})

		if startTlsResult.Err != nil {
			fmt.Fprintf(logger, "ERROR: STARTTLS failed - %s\n", startTlsResult.Err)
			conversation.Error = startTlsResult.Err
			return conversation
		}

		if ehloResult.Err != nil {
			fmt.Fprintf(logger, "ERROR: EHLO after STARTTLS failed - %s\n", ehloResult.Err)
			conversation.Error = ehloResult.Err
			return conversation
		}

		conversation.AddStep(SmtpStepStartTLS, startTlsResult.Command, startTlsResult.Reply.Code, startTlsResult.Reply.Message)
		conversation.AddStep(SmtpStepHello, ehloResult.Command, ehloResult.Reply.Code, ehloResult.Reply.Message)

		if !startTlsResult.CodeValid(220) {
			fmt.Fprintf(logger, "ERROR: STARTTLS failed with code %d: %s\n", startTlsResult.Reply.Code, startTlsResult.Reply.Message)
			conversation.SmtpErrorStatus = startTlsResult.Reply.Code
			return conversation
		}

		if !ehloResult.CodeValid(250) {
			fmt.Fprintf(logger, "ERROR: EHLO after STARTTLS failed with code %d: %s\n", ehloResult.Reply.Code, ehloResult.Reply.Message)
			conversation.SmtpErrorStatus = ehloResult.Reply.Code
			return conversation
		}

		fmt.Fprintf(logger, "INFO: STARTTLS succeeded\n")
	} else {
		fmt.Fprintf(logger, "INFO: STARTTLS not supported by %s\n", host)
	}

	// STEP 3: MAIL FROM
	// ================
	mailResult := c.Mail(getReturnPath(send, instanceDomain))
	if mailResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: MAIL FROM failed - %s\n", mailResult.Err)
		conversation.Error = mailResult.Err
		return conversation
	}
	conversation.AddStep(SmtpStepMail, mailResult.Command, mailResult.Reply.Code, mailResult.Reply.Message)
	if !mailResult.CodeValid(250) {
		fmt.Fprintf(logger, "ERROR: MAIL FROM failed with code %d: %s\n", mailResult.Reply.Code, mailResult.Reply.Message)
		conversation.SmtpErrorStatus = mailResult.Reply.Code
		return conversation
	}

	// STEP 4: RCPT TO
	// ===============
	rcptResult := c.Rcpt(send.To)
	if rcptResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: RCPT failed - %s\n", rcptResult.Err)
		conversation.Error = rcptResult.Err
		return conversation
	}
	conversation.AddStep(SmtpStepRcpt, rcptResult.Command, rcptResult.Reply.Code, rcptResult.Reply.Message)
	if !rcptResult.CodeValid(25) {
		fmt.Fprintf(logger, "ERROR: RCPT TO failed with code %d: %s\n", rcptResult.Reply.Code, rcptResult.Reply.Message)
		conversation.SmtpErrorStatus = rcptResult.Reply.Code
		return conversation
	}

	// STEP 5: DATA
	// ============
	w, dataResult := c.Data()
	if dataResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: DATA failed - %s\n", dataResult.Err)
		conversation.Error = dataResult.Err
		return conversation
	}
	conversation.AddStep(SmtpStepData, dataResult.Command, dataResult.Reply.Code, dataResult.Reply.Message)
	if !dataResult.CodeValid(354) {
		fmt.Fprintf(logger, "ERROR: DATA failed with code %d: %s\n", dataResult.Reply.Code, dataResult.Reply.Message)
		conversation.SmtpErrorStatus = dataResult.Reply.Code
		return conversation
	}
	_, err = w.Write([]byte(send.RawEmail))
	if err != nil {
		fmt.Fprintf(logger, "ERROR: Writing data failed - %s\n", err)
		conversation.Error = err
		return conversation
	}

	// STEP 5.1: Close DATA
	// ============
	closeResult := w.Close()
	if closeResult.Err != nil {
		fmt.Fprintf(logger, "ERROR: Closing data failed - %s\n", err)
		conversation.Error = closeResult.Err
		return conversation
	}
	conversation.AddStep(SmtpStepDataClose, closeResult.Command, closeResult.Reply.Code, closeResult.Reply.Message)
	if !closeResult.CodeValid(250) {
		fmt.Fprintf(logger, "ERROR: Closing data failed with code %d: %s\n", closeResult.Reply.Code, closeResult.Reply.Message)
		conversation.SmtpErrorStatus = closeResult.Reply.Code
		return conversation
	}

	// STEP 6: QUIT
	// ============
	quitResult := c.Quit() // ignore QUIT error
	conversation.AddStep(SmtpStepQuit, quitResult.Command, quitResult.Reply.Code, quitResult.Reply.Message)

	return conversation

}

func getReturnPath(
	send *DbSend,
	instanceDomain string,
) string {
	return fmt.Sprintf("bounce+%s@%s", send.Uuid, instanceDomain)
}
