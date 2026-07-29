package main

import (
	"fmt"
	"strings"
)

/**
 * Most of this is based on https://smtpfieldmanual.com/
 * See /hosting/providers
 */

type SmtpResponseParser struct {
	Code         int
	EnhancedCode [3]int
	Message      string
}

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

/**
 * This checks if the SMTP response indicates a recipient bounce.
 * ex: the bounce was due to an issue with the recipient address.
 * This is important for suppressions. We only want to suppress on recipient bounces.
 */
func (p *SmtpResponseParser) IsRecipientBounce() bool {
	// must be a bounce first
	if !p.IsBounce() {
		return false
	}

	/**
	 * Most modern SMTP servers provide an enhanced status code for bounces.
	 * If it is not present, it is likely an older server.
	 * In that case, we assume there is no need for suppressions.
	 * Not developed enough to support enhanced codes, not developed enough to ban based on repeated bounces.
	 * (Note: this is an assumption that we may want to revisit in the future.)
	 */
	if p.EnhancedCode == [3]int{0, 0, 0} {
		return false
	}
	key := p.enhancedCodeString()
	return recipientEnhancedCodes[key]
}

/**
 * Checks if the error is due to infrastructure issues (e.g., spam filtering, policy restrictions).
 * These must be recorded
 */
func (p *SmtpResponseParser) IsInfrastructureError() bool {
	// must have an enhanced code
	if p.EnhancedCode == [3]int{0, 0, 0} {
		return false
	}
	ec := p.enhancedCodeString()
	return len(ec) >= 3 && (ec[:3] == "5.7" || ec[:3] == "4.7")
}

func (p *SmtpResponseParser) GetFullMessage() string {
	parts := []string{}
	if p.Code != 0 {
		parts = append(parts, fmt.Sprintf("%d", p.Code))
	}
	if p.EnhancedCode != [3]int{0, 0, 0} {
		parts = append(parts, p.enhancedCodeString())
	}
	if p.Message != "" {
		msg := p.Message
		if len(msg) > 255 {
			msg = msg[:255]
		}
		parts = append(parts, msg)
	}
	return strings.Join(parts, " ")
}

func (p *SmtpResponseParser) enhancedCodeString() string {
	return fmt.Sprintf("%d.%d.%d", p.EnhancedCode[0], p.EnhancedCode[1], p.EnhancedCode[2])
}

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
