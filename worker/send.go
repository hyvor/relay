package main

import (
	"crypto/tls"
	"encoding/json"
	"errors"
	"fmt"
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

type SendResultCode int

const (
	// a SMTP server (one of the MX hosts) accepted the email
	SendResultAccepted SendResultCode = iota

	// the email was deferred (e.g. 4xx SMTP error)
	SendResultDeferred

	// the email was bounced (e.g. 5xx SMTP error)
	SendResultBounced

	// network error or tries exhausted
	SendResultFailed
)

type SendResult struct {
	// always set
	SentFromIpId int
	SentFromIp   string
	QueueName    string
	Duration     time.Duration // set when the function ends

	// if the MX resolving was successful
	ResolvedMxHosts []string

	// when there are SMTP messages
	SmtpConversations map[string]*SmtpConversation

	// when the send is done
	Code SendResultCode
	// saves the MX host that accepted, deferred, or bounced the email
	RespondedMxHost string
	// only when failed
	Error error
	// when the email was deferred
	NewTryCount int
}

func (r *SendResult) ToStatus() string {
	if r.Code == SendResultAccepted {
		return "accepted"
	} else if r.Code == SendResultDeferred {
		return "deferred"
	} else if r.Code == SendResultBounced {
		return "bounced"
	}
	return "failed"
}

func sendEmail(
	send *SendRow,
	recipients []*RecipientRow,
	rcptDomain string,
	instanceDomain string,
	ipId int,
	ip string,
	ptr string,
) *SendResult {

	startTime := time.Now()
	tryCount := recipients[0].TryCount // all recipients of this domain should have the same try count

	result := &SendResult{
		SentFromIpId: ipId,
		SentFromIp:   ip,
		QueueName:    send.QueueName,

		ResolvedMxHosts:   make([]string, 0),
		SmtpConversations: make(map[string]*SmtpConversation),
	}

	defer func() {
		duration := time.Since(startTime)
		result.Duration = duration
	}()

	mxHosts, err := getMxHostsFromDomain(rcptDomain)

	if err != nil {
		result.Code = SendResultFailed
		result.Error = err
		return result
	}

	result.ResolvedMxHosts = mxHosts

	var lastError error

	for _, host := range mxHosts {

		conversation := sendEmailToHost(
			send,
			recipients,
			host,
			instanceDomain,
			ip,
			ptr,
		)

		result.SmtpConversations[host] = conversation

		if conversation.Error != nil {
			// a connection-level error happened
			// continue the loop to try the next host
			lastError = conversation.Error
			continue
		} else if conversation.SmtpErrorStatus > 0 {

			if conversation.SmtpErrorStatus >= 400 && conversation.SmtpErrorStatus < 500 {
				// 4xx errors are transient, requeue the email if we haven't reached the max tries
				result.RespondedMxHost = host

				if tryCount >= MAX_SEND_TRIES {
					result.Code = SendResultFailed
					result.Error = errors.New("Maximum send attempts reached")
					return result
				} else {
					result.Code = SendResultDeferred
					result.NewTryCount = tryCount + 1
					return result
				}

			} else if conversation.SmtpErrorStatus >= 500 {

				// 5xx errors are permanent, do not requeue
				result.RespondedMxHost = host
				result.Code = SendResultBounced

				return result
			}

			// for any other error, we can continue to the next host
			continue

		} else {
			result.RespondedMxHost = host
			result.Code = SendResultAccepted
			return result
		}
	}

	// TODO: implement retrying here for network errors

	result.Code = SendResultFailed
	result.Error = lastError
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
	send *SendRow,
	recipients []*RecipientRow,
	host string,
	instanceDomain string,
	ip string,
	ptr string,
) *SmtpConversation {

	conversation := NewSmtpConversation()

	// STEP 0: Connect to SMTP server
	// ==============================
	c, err := createSmtpClient(host, ip)
	if err != nil {
		conversation.Error = err
		return conversation
	}
	defer c.Close()
	conversation.AddStep(SmtpStepDial, "", 0, "")

	// STEP 1: EHLO/HELO
	// =================
	helloResult := c.Hello(ptr)

	if helloResult.Err != nil {
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

		startTlsResult, ehloResult := c.StartTLS(&tls.Config{ServerName: host})

		if startTlsResult.Err != nil {
			conversation.Error = startTlsResult.Err
			return conversation
		}

		if ehloResult.Err != nil {
			conversation.Error = ehloResult.Err
			return conversation
		}

		conversation.AddStep(SmtpStepStartTLS, startTlsResult.Command, startTlsResult.Reply.Code, startTlsResult.Reply.Message)
		conversation.AddStep(SmtpStepHello, ehloResult.Command, ehloResult.Reply.Code, ehloResult.Reply.Message)

		if !startTlsResult.CodeValid(220) {
			conversation.SmtpErrorStatus = startTlsResult.Reply.Code
			return conversation
		}

		if !ehloResult.CodeValid(250) {
			conversation.SmtpErrorStatus = ehloResult.Reply.Code
			return conversation
		}

	}

	// STEP 3: MAIL FROM
	// ================
	mailResult := c.Mail(getReturnPath(send, instanceDomain))
	if mailResult.Err != nil {
		conversation.Error = mailResult.Err
		return conversation
	}
	conversation.AddStep(SmtpStepMail, mailResult.Command, mailResult.Reply.Code, mailResult.Reply.Message)
	if !mailResult.CodeValid(250) {
		conversation.SmtpErrorStatus = mailResult.Reply.Code
		return conversation
	}

	// STEP 4: RCPT TO
	// ===============
	for _, rcpt := range recipients {
		rcptResult := c.Rcpt(rcpt.Address)

		if rcptResult.Err != nil {
			conversation.Error = rcptResult.Err
			return conversation
		}

		conversation.AddStep(SmtpStepRcpt, rcptResult.Command, rcptResult.Reply.Code, rcptResult.Reply.Message)
		if !rcptResult.CodeValid(25) {
			conversation.SmtpErrorStatus = rcptResult.Reply.Code
			return conversation
		}
	}

	// STEP 5: DATA
	// ============
	w, dataResult := c.Data()
	if dataResult.Err != nil {
		conversation.Error = dataResult.Err
		return conversation
	}
	conversation.AddStep(SmtpStepData, dataResult.Command, dataResult.Reply.Code, dataResult.Reply.Message)
	if !dataResult.CodeValid(354) {
		conversation.SmtpErrorStatus = dataResult.Reply.Code
		return conversation
	}
	_, err = w.Write([]byte(send.RawEmail))
	if err != nil {
		conversation.Error = err
		return conversation
	}

	// STEP 5.1: Close DATA
	// ============
	closeResult := w.Close()
	if closeResult.Err != nil {
		conversation.Error = closeResult.Err
		return conversation
	}
	conversation.AddStep(SmtpStepDataClose, closeResult.Command, closeResult.Reply.Code, closeResult.Reply.Message)
	if !closeResult.CodeValid(250) {
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
	send *SendRow,
	instanceDomain string,
) string {
	return fmt.Sprintf("bounce+%s@%s", send.Uuid, instanceDomain)
}
