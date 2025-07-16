package main

import "strings"

type BounceMail struct {
	MailFrom       string
	RcptTo         string
	Data           string
	InstanceDomain string
}

// call after the data is read
func (m *BounceMail) Handle() {

	isBounce, uuid := checkBounceEmail(m.RcptTo, m.InstanceDomain)

	if isBounce {
		m.handleBounce(uuid)
		return
	}

	//

}

func (m *BounceMail) handleBounce(uuid string) {
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
