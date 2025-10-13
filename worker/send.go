package main

import (
	"crypto/tls"
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"os"
	"time"

	smtp "github.com/hyvor/relay/worker/smtp"
)

var ErrSendEmailFailed = errors.New("failed to send email")

const MAX_SEND_TRIES = 7

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
	return json.Marshal(time.Duration(d).Milliseconds())
}

type SmtpStep struct {
	Name      SmtpStepName    `json:"name"`
	Duration  LatencyDuration `json:"duration_ms"`
	Command   string          `json:"command"`
	ReplyCode int             `json:"reply_code"`
	ReplyText string          `json:"reply_text"`
}

type SmtpConversation struct {
	StartTime    time.Time     `json:"start_time"`
	lastStepTime time.Time     `json:"-"`
	Duration     time.Duration `json:"duration_ms"`

	// network/transport error
	// nil if the message was sent successfully (regardless of SMTP status)
	NetworkError error `json:"network_error"`

	// if there was a SmtpError, it means all recipients were rejected
	// can be set in the middle of the conversation
	// e.g. non-250 on EHLO
	SmtpError *SmtpError `json:"smtp_error"`

	// There were no SMTP errors, but some recipients were rejected
	// indexed by recipient ID
	// only set when there are recipient-specific errors
	// e.g. some RCPT TO commands got 550, while others got 250
	RcptSmtpErrors map[int]*SmtpError `json:"rcpt_smtp_errors"`

	Steps []*SmtpStep `json:"steps"`
}

type SmtpError struct {
	// 0 = no SMTP error
	// > 0 = SMTP error code
	// can be set in the middle of the conversation
	// e.g. non-250 on EHLO
	Code    int    `json:"code"`
	Message string `json:"message"`
}

func NewSmtpErrorFromReply(reply *smtp.CommandReply) *SmtpError {
	if reply == nil {
		return nil
	}
	return &SmtpError{
		Code:    reply.Code,
		Message: reply.Message,
	}
}

// converts the Error field to a string for JSON marshalling
func (s SmtpConversation) MarshalJSON() ([]byte, error) {
	type SmtpConversationAlias SmtpConversation
	aux := struct {
		*SmtpConversationAlias
		NetworkError string `json:"network_error"`
	}{
		SmtpConversationAlias: (*SmtpConversationAlias)(&s),
	}

	if s.NetworkError != nil {
		aux.NetworkError = s.NetworkError.Error()
	}

	return json.Marshal(aux)
}

func NewSmtpConversation() *SmtpConversation {
	return &SmtpConversation{
		StartTime:    time.Now(),
		lastStepTime: time.Now(),
		NetworkError: nil,
		SmtpError:    nil,
		Steps:        make([]*SmtpStep, 0),
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
		Duration:  LatencyDuration(time.Since(c.lastStepTime)),
		Command:   command,
		ReplyCode: replyCode,
		ReplyText: replyText,
	}

	c.Steps = append(c.Steps, step)
	c.lastStepTime = time.Now()
	c.Duration = time.Since(c.StartTime)

}

func (c *SmtpConversation) AddStepFromResult(name SmtpStepName, result *smtp.CommandResult) {
	c.AddStep(name, result.Command, result.Reply.Code, result.Reply.Message)
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

func (c SendResultCode) Status() string {
	if c == SendResultAccepted {
		return "accepted"
	}
	if c == SendResultDeferred {
		return "deferred"
	}
	if c == SendResultBounced {
		return "bounced"
	}
	return "failed"
}

type SendResult struct {
	// always set
	SentFromIpId int
	SentFromIp   string
	Domain       string
	QueueName    string
	Duration     time.Duration // set when the function ends

	// if the MX resolving was successful
	ResolvedMxHosts []string

	// when there are SMTP messages
	SmtpConversations map[string]*SmtpConversation

	// saves the MX host that responded (accepted, deferred, or bounced the email)
	RespondedMxHost string
	// results for each recipient, indexed by recipient ID
	RecipientResults map[int]SendResultCode
	// only when failed, a simple error message
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

var sendEmail = sendEmailHandler

func sendEmailHandler(
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
		Domain:       rcptDomain,
		QueueName:    send.QueueName,

		ResolvedMxHosts:   make([]string, 0),
		SmtpConversations: make(map[string]*SmtpConversation),
		NewTryCount:       tryCount + 1,
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

		if conversation.NetworkError != nil {
			// a connection-level error happened
			// continue the loop to try the next host
			lastError = conversation.NetworkError
			continue
		} else if conversation.SmtpError != nil {

			if conversation.SmtpError.Code >= 400 && conversation.SmtpError.Code < 500 {
				// 4xx errors are transient, requeue the email if we haven't reached the max tries
				result.RespondedMxHost = host

				if result.NewTryCount >= MAX_SEND_TRIES {
					result.Code = SendResultFailed
					result.Error = errors.New("maximum send attempts reached")
					return result
				} else {
					result.Code = SendResultDeferred
					result.Error = fmt.Errorf("transient SMTP error: %d %s", conversation.SmtpError.Code, conversation.SmtpError.Message)
					return result
				}

			} else if conversation.SmtpError.Code >= 500 {

				// 5xx errors are permanent, do not requeue
				result.RespondedMxHost = host
				result.Code = SendResultBounced
				result.Error = fmt.Errorf("permanent SMTP error: %d %s", conversation.SmtpError.Code, conversation.SmtpError.Message)

				return result
			}

			// for any other error, we can continue to the next host
			lastError = fmt.Errorf("unexpected SMTP error: %d %s", conversation.SmtpError.Code, conversation.SmtpError.Message)
			continue

		} else {
			result.RespondedMxHost = host
			result.Code = SendResultAccepted
			return result
		}
	}

	result.Error = lastError

	// if we reach here, all hosts have failed due to non-smtp errors (e.g. network errors)
	if result.NewTryCount == 1 {
		// give it one more try later (15mins) if this was the first try
		result.Code = SendResultDeferred
	} else {
		result.Code = SendResultFailed
	}

	return result
}

var netResolveTCPAddr = net.ResolveTCPAddr

const defaultSmtpPort = ":25"

func getOutgoingPort() string {
	port := os.Getenv("OUTGOING_SMTP_PORT")
	if port == "" {
		return defaultSmtpPort
	}
	return port
}

const smtpClientConnectionTimeout = 8 * time.Second
const smtpClientKeepAlive = 8 * time.Second

var createSmtpClient = func(host string, localIp string) (*smtp.Client, error) {

	// TODO: comment from ResolveTCPAddr
	// "The address parameter can use a host name, but this is not
	// recommended, because it will return at most one of the host name's
	// IP addresses."
	// So, we might need to resolve A records manually first.

	remoteAddr, err := netResolveTCPAddr("tcp", host+getOutgoingPort())
	if err != nil {
		return nil, fmt.Errorf("failed to resolve remote address %s: %w", host, err)
	}

	localAddr := &net.TCPAddr{
		IP:   net.ParseIP(localIp),
		Port: 0, // Let the OS choose an available local port
	}

	dialer := &net.Dialer{
		LocalAddr: localAddr,
		Timeout:   smtpClientConnectionTimeout,
		KeepAlive: smtpClientKeepAlive,
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

var sendEmailToHost = sendEmailToHostHandler

func sendEmailToHostHandler(
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
		conversation.NetworkError = err
		return conversation
	}
	defer c.Close()
	conversation.AddStep(SmtpStepDial, "", 0, "")

	// STEP 1: EHLO/HELO
	// =================
	helloResult := c.Hello(ptr)

	if helloResult.Err != nil {
		conversation.NetworkError = helloResult.Err
		return conversation
	}
	conversation.AddStepFromResult(SmtpStepHello, &helloResult)
	if !helloResult.CodeValid(250) {
		conversation.SmtpError = &SmtpError{
			Code:    helloResult.Reply.Code,
			Message: helloResult.Reply.Message,
		}
		return conversation
	}

	// STEP 2: STARTTLS
	// ============
	if ok, _ := c.Extension("STARTTLS"); ok {

		startTlsResult, ehloResult := c.StartTLS(&tls.Config{ServerName: host})

		if startTlsResult.Err != nil {
			conversation.NetworkError = startTlsResult.Err
			return conversation
		}

		if ehloResult.Err != nil {
			conversation.NetworkError = ehloResult.Err
			return conversation
		}

		conversation.AddStepFromResult(SmtpStepStartTLS, &startTlsResult)
		conversation.AddStepFromResult(SmtpStepHello, &ehloResult)

		if !startTlsResult.CodeValid(220) {
			conversation.SmtpError = &SmtpError{
				Code:    startTlsResult.Reply.Code,
				Message: startTlsResult.Reply.Message,
			}
			return conversation
		}

		if !ehloResult.CodeValid(250) {
			conversation.SmtpError = &SmtpError{
				Code:    ehloResult.Reply.Code,
				Message: ehloResult.Reply.Message,
			}
			return conversation
		}

	}

	// STEP 3: MAIL FROM
	// ================
	mailResult := c.Mail(getReturnPath(send, instanceDomain))
	if mailResult.Err != nil {
		conversation.NetworkError = mailResult.Err
		return conversation
	}
	conversation.AddStepFromResult(SmtpStepMail, &mailResult)
	if !mailResult.CodeValid(250) {
		conversation.SmtpError = &SmtpError{
			Code:    mailResult.Reply.Code,
			Message: mailResult.Reply.Message,
		}
		return conversation
	}

	// STEP 4: RCPT TO
	// We continue to the next step if at least one recipient is accepted
	// ===============
	var rcptAcceptedAny bool // if at least one RCPT TO was accepted
	rcptSmtpErrors := make(map[int]*SmtpError)

	for _, rcpt := range recipients {
		rcptResult := c.Rcpt(rcpt.Address)

		if rcptResult.Err != nil {
			conversation.NetworkError = rcptResult.Err
			return conversation
		}

		conversation.AddStepFromResult(SmtpStepRcpt, &rcptResult)

		if rcptResult.CodeValid(250) {
			rcptAcceptedAny = true
		} else {
			rcptSmtpErrors[rcpt.Id] = NewSmtpErrorFromReply(rcptResult.Reply)
		}
	}

	// if no recipients were accepted, we consider it a failure
	// and set the SmtpError to the first recipient error
	if !rcptAcceptedAny {
		for _, err := range rcptSmtpErrors {
			conversation.SmtpError = err
			return conversation
		}
	} else if len(rcptSmtpErrors) > 0 {
		// some recipients were rejected, but at least one was accepted
		conversation.RcptSmtpErrors = rcptSmtpErrors
	}

	// STEP 5: DATA
	// ============
	w, dataResult := c.Data()
	if dataResult.Err != nil {
		conversation.NetworkError = dataResult.Err
		return conversation
	}
	conversation.AddStepFromResult(SmtpStepData, &dataResult)
	if !dataResult.CodeValid(354) {
		conversation.SmtpError = &SmtpError{
			Code:    dataResult.Reply.Code,
			Message: dataResult.Reply.Message,
		}
		return conversation
	}
	_, err = w.Write([]byte(send.RawEmail))
	if err != nil {
		conversation.NetworkError = err
		return conversation
	}

	// STEP 5.1: Close DATA
	// ============
	closeResult := w.Close()
	if closeResult.Err != nil {
		conversation.NetworkError = closeResult.Err
		return conversation
	}
	conversation.AddStepFromResult(SmtpStepDataClose, &closeResult)
	if !closeResult.CodeValid(250) {
		conversation.SmtpError = &SmtpError{
			Code:    closeResult.Reply.Code,
			Message: closeResult.Reply.Message,
		}
		return conversation
	}

	// STEP 6: QUIT
	// ============
	quitResult := c.Quit() // QUIT error won't be considered a failure
	if quitResult.Err == nil {
		conversation.AddStepFromResult(SmtpStepQuit, &quitResult)
	}

	return conversation

}

func getReturnPath(
	send *SendRow,
	instanceDomain string,
) string {
	return fmt.Sprintf("bounce+%s@%s", send.Uuid, instanceDomain)
}

// tryCount is
func getSendAfterInterval(currentAttempt int) string {

	if currentAttempt == 1 {
		return "15 minutes"
	}
	if currentAttempt == 2 {
		return "1 hour"
	}
	if currentAttempt == 3 {
		return "2 hours"
	}
	if currentAttempt == 4 {
		return "4 hours"
	}
	if currentAttempt == 5 {
		return "8 hours"
	}
	if currentAttempt == 6 {
		return "16 hours"
	}

	return "1 day"
}
