package main

/*
import (
	"bytes"
	"errors"
	"net"
	"testing"

	smtp "github.com/emersion/go-smtp"
	"github.com/stretchr/testify/assert"
)

type MockSmtpClient struct {
	host  string
	steps []SmtpStepName
	from  string
	to    string
}

func (m *MockSmtpClient) Dial(addr string) error {
	m.steps = append(m.steps, SmtpStepDial)
	return nil
}

func (m *MockSmtpClient) Hello(localName string) error {
	m.steps = append(m.steps, SmtpStepHello)
	return nil
}

func (m *MockSmtpClient) Mail(from string, opts *smtp.MailOptions) error {
	m.steps = append(m.steps, SmtpStepMail)
	m.from = from
	return nil
}

func (m *MockSmtpClient) Rcpt(to string, opts *smtp.RcptOptions) error {
	m.steps = append(m.steps, SmtpStepRcpt)
	m.to = to
	return nil
}

func (m *MockSmtpClient) Data() (*smtp.DataCommand, error) {
	m.steps = append(m.steps, SmtpStepData)
	return &smtp.DataCommand{}, nil
}

func (m *MockSmtpClient) Quit() error {
	m.steps = append(m.steps, SmtpStepQuit)
	return nil
}

func (m *MockSmtpClient) Close() error {
	m.steps = append(m.steps, SmtpStepClose)
	return nil
}

func startMockSmtpClient(host string) {
	client := &MockSmtpClient{
		host: host,
	}
	createSmtpClient = func(_ string) (SmtpClient, error) {
		return client, nil
	}
}

func TestFailsOnMxError(t *testing.T) {
	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return nil, errors.New("no network")
	}

	message := &EmailSendMessage{}
	log := &bytes.Buffer{}
	result := sendEmail(message, log)

	assert.True(t, errors.Is(result.Error, ErrSmtpMxLookupFailed))
	assert.Contains(t, log.String(), "ERROR: MX lookup failed: no network\n")
}

func TestSendEmailSuccess(t *testing.T) {

	smtpHost := "mx1.hyvor.com"
	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return []*net.MX{{Host: smtpHost}}, nil
	}
	startMockSmtpClient(smtpHost)

	message := &EmailSendMessage{}
	log := &bytes.Buffer{}
	result := sendEmail(message, log)

	assert.Nil(t, result.Error)

}
*/
