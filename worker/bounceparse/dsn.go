package bounceparse

import (
	"bufio"
	"bytes"
	"fmt"
	"io"
	"mime"
	"mime/multipart"
	"net/mail"
	"net/textproto"
	"strconv"
	"strings"
)

// DSN (Delivery Status Notification) is RFC3464
// https://datatracker.ietf.org/doc/html/rfc3464

type Dsn struct {
	ReadableText string         // Readable text representation of the DSN (part1)
	Recipients   []DsnRecipient // List of recipients with their statuses
}

type DsnRecipient struct {
	EmailAddress string // The email address of the recipient
	Status       [3]int // The status code for this recipient, e.g., {5,1,1} for permanent failure
	Action       string // Action taken, e.g., "failed", "delayed", "delivered" (https://datatracker.ietf.org/doc/html/rfc3464#section-2.3.3)
}

// ParseBounce
func ParseDsn(input []byte) (*Dsn, error) {

	dsn := &Dsn{}

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

	multipartReader := multipart.NewReader(message.Body, params["boundary"])

	// Part1 is a human-readable description of the report (text/plain or text/html)
	part1, err := multipartReader.NextPart()
	if err != nil {
		return nil, err
	}
	part1Data, err := io.ReadAll(part1)
	if err != nil {
		return nil, err
	}
	dsn.ReadableText = string(part1Data)

	// Part2 is the machine-readable report (message/feedback-report)
	part2, err := readNextPartBodyAsMessage(multipartReader, []string{"message/delivery-status"})
	if err != nil {
		return nil, err
	}

	recipientsBody, _ := io.ReadAll(part2.Body)
	recipientsFields := bytes.Split(recipientsBody, []byte("\n\n"))

	for i, recipientFields := range recipientsFields {

		dsnRecipient := DsnRecipient{}

		recipientFields = append(recipientFields, '\n')
		recipientFields = append(recipientFields, '\n')
		reader := bufio.NewReader(bytes.NewReader(recipientFields))

		tp := textproto.NewReader(reader)

		headers, err := tp.ReadMIMEHeader()
		if err != nil {
			return nil, fmt.Errorf("[%d] error reading MIME header: %w", i, err)
		}

		dsnRecipient.EmailAddress = getDsnRecipientEmailAddress(headers.Get("Original-Recipient"))
		dsnRecipient.Status = parseDsnStatus(headers.Get("Status"))
		dsnRecipient.Action = headers.Get("Action")

		dsn.Recipients = append(dsn.Recipients, dsnRecipient)

	}

	return dsn, nil

}

// https://datatracker.ietf.org/doc/html/rfc3464#section-2.3.1
// Original-Recipient: rfc822;me@example.com
// Removes the "rfc822;" prefix if it exists
func getDsnRecipientEmailAddress(header string) string {

	if strings.HasPrefix(header, "rfc822;") {
		return strings.TrimSpace(strings.TrimPrefix(header, "rfc822;"))
	}

	return strings.TrimSpace(header)

}

// https://datatracker.ietf.org/doc/html/rfc3464#section-2.3.4
// Status: 5.1.1
// Status: 4.0.0 (delayed)
// Parsed to: [3]int{5, 1, 1} or [3]int{4, 0, 0}
func parseDsnStatus(header string) [3]int {

	var status [3]int

	parts := strings.Split(header, ".")

	for i, part := range parts {
		if i >= 3 {
			break // Only take the first three parts
		}
		if num, err := strconv.Atoi(part); err == nil {
			status[i] = num
		}
	}

	return status

}
