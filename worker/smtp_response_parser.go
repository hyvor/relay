package main

import (
	"fmt"
)

// SmtpResponseParser classifies SMTP bounce responses into recipient or infrastructure reasons.
// Based on https://smtpfieldmanual.com/
type SmtpResponseParser struct {
	Code         int
	EnhancedCode [3]int
	Message      string
}

// Recipient enhanced status codes that indicate a user/recipient error
var recipientEnhancedCodes = map[string]bool{
	"5.1.1": true, // Bad destination mailbox address
	"5.1.2": true, // Bad destination system address
	"5.1.3": true, // Bad destination mailbox address syntax
	"5.5.0": true, // Other or undefined mailbox status
}

func NewSmtpResponseParser(code int, enhancedCode [3]int, message string) *SmtpResponseParser {
	return &SmtpResponseParser{
		Code:         code,
		EnhancedCode: enhancedCode,
		Message:      message,
	}
}

func (p *SmtpResponseParser) IsBounce() bool {
	if p.Code != 0 {
		return p.Code >= 500 && p.Code < 600
	}
	if p.EnhancedCode != [3]int{0, 0, 0} {
		return p.EnhancedCode[0] == 5
	}
	return false
}

// IsRecipientBounce checks if the SMTP response indicates a recipient bounce.
// This is a user error (like email not existing) and is bad for the sender.
func (p *SmtpResponseParser) IsRecipientBounce() bool {
	if !p.IsBounce() {
		return false
	}
	if p.EnhancedCode == [3]int{0, 0, 0} {
		return false
	}
	key := p.enhancedCodeString()
	return recipientEnhancedCodes[key]
}

// IsInfrastructureError checks if the error is due to infrastructure issues
// (e.g., spam filtering, IP reputation, IP blocked). This is bad for the platform (Relay).
func (p *SmtpResponseParser) IsInfrastructureError() bool {
	if p.EnhancedCode == [3]int{0, 0, 0} {
		return false
	}
	ec := p.enhancedCodeString()
	return len(ec) >= 3 && (ec[:3] == "5.7" || ec[:3] == "4.7")
}

func (p *SmtpResponseParser) GetFullMessage() string {
	code := ""
	if p.Code != 0 {
		code = fmt.Sprintf("%d", p.Code)
	}
	enhancedCode := ""
	if p.EnhancedCode != [3]int{0, 0, 0} {
		enhancedCode = fmt.Sprintf(" %s", p.enhancedCodeString())
	}
	message := ""
	if p.Message != "" {
		msg := p.Message
		if len(msg) > 255 {
			msg = msg[:255]
		}
		message = fmt.Sprintf(" %s", msg)
	}
	return fmt.Sprintf("%s%s%s", code, enhancedCode, message)
}

func (p *SmtpResponseParser) enhancedCodeString() string {
	return fmt.Sprintf("%d.%d.%d", p.EnhancedCode[0], p.EnhancedCode[1], p.EnhancedCode[2])
}

// BounceReason returns the bounce reason for a recipient result.
// Returns empty string if not a bounce.
func (p *SmtpResponseParser) BounceReason() BounceReason {
	if !p.IsBounce() {
		return ""
	}
	if p.IsRecipientBounce() {
		return BounceReasonRecipient
	}
	if p.IsInfrastructureError() {
		return BounceReasonInfrastructure
	}
	return ""
}

type BounceReason string

const (
	BounceReasonRecipient      BounceReason = "recipient"
	BounceReasonInfrastructure BounceReason = "infrastructure"
)
