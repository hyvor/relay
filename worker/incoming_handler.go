package main

import (
	"log/slog"
	"strings"

	"github.com/hyvor/relay/worker/bounceparse"
)

type IncomingMail struct {
	MailFrom       string
	RcptTo         string
	Data           string
	InstanceDomain string

	logger *slog.Logger
}

// call after the data is read
func (m *IncomingMail) Handle() {

	isBounce, uuid := checkBounceEmail(m.RcptTo, m.InstanceDomain)

	if isBounce {
		m.handleBounce(uuid)
		return
	}

	//

}

func (m *IncomingMail) handleBounce(uuid string) {

	_, err := bounceparse.ParseDsn([]byte(m.Data))

	if err != nil {
		m.logger.Error("Error parsing bounce email", "error", err)
		return
	}

	//

}

// Bounce email format: bounce+<uuid>@<instance_domain>
func checkBounceEmail(rcptTo string, instanceDomain string) (bool, string) {

	if rcptTo == "" {
		return false, ""
	}

	at := strings.Index(rcptTo, "@")

	if at < 0 {
		return false, ""
	}

	if !strings.HasPrefix(rcptTo[:at], "bounce+") {
		return false, ""
	}

	if !strings.HasSuffix(rcptTo[at:], instanceDomain) {
		return false, ""
	}

	return true, rcptTo[7:at]

}

// fbl email is: fbl@<instance_domain>
func checkFbl(rcptTo string, instanceDomain string) bool {

	if rcptTo == "" {
		return false
	}

	at := strings.Index(rcptTo, "@")

	if at < 0 {
		return false
	}

	if !strings.HasPrefix(rcptTo[:at], "fbl") {
		return false
	}

	if !strings.HasSuffix(rcptTo[at:], instanceDomain) {
		return false
	}

	return true

}
