package bounceparse

import (
	"bytes"
	"errors"
	"io"
	"mime"
	"mime/multipart"
	"net/mail"
)

// ARF (Abuse Reporting Format) is RFC5965
// https://datatracker.ietf.org/doc/html/rfc5965

type Arf struct {
	ReadableText     string // Human-readable description of the report
	FeedbackType     string // The type of feedback (e.g., abuse, spam, etc.)
	UserAgent        string // The user agent of the sender
	OriginalMailFrom string // The original MAIL FROM (Return-Path) address, this can be empty since the RFC does not require it
	MessageId        string // The Message-ID of the original message
}

var ErrNotArfReport = errors.New("not an ARF report")
var ErrInvalidMimeType = errors.New("invalid ARF MIME type")

// ParseFbl
func ParseArf(input []byte) (*Arf, error) {

	arf := &Arf{}

	message, err := mail.ReadMessage(bytes.NewReader(input))

	if err != nil {
		return nil, err
	}

	mediaType, params, err := mime.ParseMediaType(message.Header.Get("Content-Type"))

	if err != nil {
		return nil, err
	}

	if mediaType != "multipart/report" {
		return nil, ErrNotArfReport
	}

	reader := multipart.NewReader(message.Body, params["boundary"])

	// Part1 is a human-readable description of the report (text/plain or text/html)
	part1, err := reader.NextPart()
	if err != nil {
		return nil, err
	}
	if err := validatePartMimeType(part1, []string{"text/plain", "text/html"}); err != nil {
		return nil, err
	}
	part1Data, err := io.ReadAll(part1)
	if err != nil {
		return nil, err
	}
	arf.ReadableText = string(part1Data)

	// Part2 is the machine-readable report (message/feedback-report)
	part2, err := readNextPartBodyAsMessage(reader, []string{"message/feedback-report"})
	if err != nil {
		return nil, err
	}

	arf.FeedbackType = part2.Header.Get("Feedback-Type")
	arf.UserAgent = part2.Header.Get("User-Agent")
	arf.OriginalMailFrom = part2.Header.Get("Original-Mail-From")
	arf.MessageId = part2.Header.Get("Message-ID")

	// Part3 is the original message (message/rfc822 or message/rfc822-headers)
	part3, err := readNextPartBodyAsMessage(reader, []string{"message/rfc822", "message/rfc822-headers"})
	if err != nil {
		return nil, err
	}
	arf.MessageId = part3.Header.Get("Message-ID")

	return arf, nil
}

func readNextPartBodyAsMessage(reader *multipart.Reader, expectedMediaTypes []string) (*mail.Message, error) {
	part, err := reader.NextPart()
	if err != nil {
		return nil, err
	}

	if err := validatePartMimeType(part, expectedMediaTypes); err != nil {
		return nil, err
	}

	partData, err := io.ReadAll(part)
	if err != nil {
		return nil, err
	}

	return mail.ReadMessage(bytes.NewReader(partData))
}

func validatePartMimeType(part *multipart.Part, expectedMediaTypes []string) error {
	partMediaType, _, err := mime.ParseMediaType(part.Header.Get("Content-Type"))
	if err != nil {
		return err
	}
	for _, mediaType := range expectedMediaTypes {
		if partMediaType == mediaType {
			return nil
		}
	}
	return ErrNotArfReport
}
