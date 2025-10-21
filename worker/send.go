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

type RcptResult struct {
	RecipientId int
	Code       int
	EnhancedCode [3]int
	Message   string
}

func (r RcptResult) MarshalJSON() ([]byte, error) {

	type RcptResultJson struct {
		RecipientId int    `json:"recipient_id"`
		Code        int    `json:"code"`
		EnhancedCode string `json:"enhanced_code"`
		Message     string `json:"message"`
		Status      string `json:"status"`
	}

	var jsonObj = RcptResultJson{
		RecipientId: r.RecipientId,
		Code:        r.Code,
		EnhancedCode: fmt.Sprintf("%d.%d.%d", r.EnhancedCode[0], r.EnhancedCode[1], r.EnhancedCode[2]),
		Message:     r.Message,
		Status:      r.ToRecipientStatus().ToString(),
	}

	return json.Marshal(jsonObj)
}

func (r RcptResult) ToRecipientStatus() RecipientStatusCode {
	if r.Code >= 200 && r.Code < 300 {
		return RecipientStatusAccepted
	} else if r.Code >= 400 && r.Code < 500 {
		return RecipientStatusDeferred
	} else if r.Code >= 500 {
		return RecipientStatusBounced
	}
	return RecipientStatusFailed
}

func (r *RcptResult) SetFailed(message string) {
	r.Code = 0
	r.EnhancedCode = [3]int{0, 0, 0}
	r.Message = message
}



type SmtpConversation struct {
	StartTime    time.Time     `json:"start_time"`
	lastStepTime time.Time     `json:"-"`
	Duration     time.Duration `json:"duration_ms"`

	// network/transport error
	// nil if the message was sent successfully (regardless of SMTP status)
	NetworkError error `json:"network_error"`

	// results for each recipient, indexed by recipient ID
	RcptResults []*RcptResult `json:"-"`

	// simply record all steps for debugging
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
		Steps:        make([]*SmtpStep, 0),
		RcptResults:  make([]*RcptResult, 0),
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

// use on failure
func (c *SmtpConversation) SetRcptResults(recipients []*RecipientRow, result *smtp.CommandResult) {
	for _, rcpt := range recipients {
		c.SetRcptResult(rcpt.Id, result)
	}
}

// for each RCPT TO command
func (c *SmtpConversation) SetRcptResult(rcptId int, result *smtp.CommandResult) {
	c.RcptResults = append(c.RcptResults, &RcptResult{
		RecipientId: rcptId,
		Code:        result.Reply.Code,
		EnhancedCode: result.Reply.EnhancedCode,
		Message:     result.Reply.Message,
	})
}

type RecipientStatusCode int

const (
	// a SMTP server (one of the MX hosts) accepted the email
	RecipientStatusAccepted RecipientStatusCode = iota

	// the email was deferred (e.g. 4xx SMTP error)
	RecipientStatusDeferred

	// the email was bounced (e.g. 5xx SMTP error)
	RecipientStatusBounced

	// network error or tries exhausted
	RecipientStatusFailed
)

func (c RecipientStatusCode) ToString() string {
	if c == RecipientStatusAccepted {
		return "accepted"
	}
	if c == RecipientStatusDeferred {
		return "deferred"
	}
	if c == RecipientStatusBounced {
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
	// RecipientResultCodes map[int]RecipientStatusCode
	// only when failed, a simple error message
	// Error error
	
	// copied from the responded conversation if available
	// otherwise manually set
	RcptResults []*RcptResult
	
	// when the email was deferred
	NewTryCount int
}

func (r *SendResult) SetAllRcptResultsFailed(recipients []*RecipientRow, message string) {
	r.SetAllRcptResults(recipients, 0, [3]int{0,0,0}, message)
}

func (r *SendResult) SetAllRcptResults(recipients []*RecipientRow, code int, enhancedCode [3]int, message string) {
	r.RcptResults = make([]*RcptResult, 0)
	for _, rcpt := range recipients {
		r.RcptResults = append(r.RcptResults, &RcptResult{
			RecipientId: rcpt.Id,
			Code:        code,
			EnhancedCode: enhancedCode,
			Message:     message,
		})
	}
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
		RcptResults: 	make([]*RcptResult, 0),
		SmtpConversations: make(map[string]*SmtpConversation),
		NewTryCount:       tryCount + 1,
	}

	defer func() {
		duration := time.Since(startTime)
		result.Duration = duration
	}()

	mxHosts, err := getMxHostsFromDomain(rcptDomain)

	if err != nil {
		result.SetAllRcptResultsFailed(recipients, err.Error())
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

		// a connection-level error happened
		// continue the loop to try the next host
		if conversation.NetworkError != nil {
			lastError = conversation.NetworkError
			continue
		} 

		// the conversation was concluded now
		// either successfully or with an SMTP error
		
		// we mark the responded host and set results
		result.RespondedMxHost = host

		for _, rcptResult := range conversation.RcptResults {
			
			// if max tries reached, set to failed
			if rcptResult.ToRecipientStatus() == RecipientStatusDeferred && result.NewTryCount >= MAX_SEND_TRIES {
				rcptResult.SetFailed("maximum send attempts reached")
			}

		}

		result.RcptResults = conversation.RcptResults

		return result;
	}


	// if we reach here, all hosts have failed due to non-smtp errors (e.g. network errors)
	if result.NewTryCount == 1 {
		// give it one more try later (15mins) if this was the first try
		result.SetAllRcptResults(recipients, 400, [3]int{4,2,1}, lastError.Error())
	} else {
		result.SetAllRcptResultsFailed(recipients, lastError.Error())
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
		conversation.SetRcptResults(recipients, &helloResult)
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
			conversation.SetRcptResults(recipients, &startTlsResult)
			return conversation
		}

		if !ehloResult.CodeValid(250) {
			conversation.SetRcptResults(recipients, &ehloResult)
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
		conversation.SetRcptResults(recipients, &mailResult)
		return conversation
	}

	// STEP 4: RCPT TO
	// We continue to the next step if at least one recipient is accepted
	// ===============
	var rcptAcceptedAny bool // if at least one RCPT TO was accepted, so we can continue to DATA

	for _, rcpt := range recipients {
		rcptResult := c.Rcpt(rcpt.Address)

		if rcptResult.Err != nil {
			conversation.NetworkError = rcptResult.Err
			return conversation
		}

		conversation.AddStepFromResult(SmtpStepRcpt, &rcptResult)
		conversation.SetRcptResult(rcpt.Id, &rcptResult)

		if rcptResult.Reply.Code == 250 {
			rcptAcceptedAny = true
		}
	}

	// if no recipients were accepted, we consider it a failure and stop here
	if !rcptAcceptedAny {
		return conversation
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
		conversation.SetRcptResults(recipients, &dataResult)
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
		conversation.SetRcptResults(recipients, &closeResult)
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
