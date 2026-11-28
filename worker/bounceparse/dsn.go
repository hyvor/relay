package bounceparse

import (
	"bufio"
	"bytes"
	"errors"
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
	ReadableText  string    // Readable text representation of the DSN (part1)
	RecipientUuid string    // The X-Relay-Recipient header of the original message, empty if not available
	EmailAddress  string    // The email address of the recipient (can be redacted by some providers)
	Status        DsnStatus // The status code for the recipient, e.g., {5,1,1} for permanent failure
	Action        string    // Action taken, e.g., "failed", "delayed", "delivered" (https://datatracker.ietf.org/doc/html/rfc3464#section-2.3.3)
	BounceReason  string    `json:"BounceReason,omitempty"` // computed bounce reason ("recipient"/"infrastructure"/"unknown"), empty if not a bounce
}

type DsnStatus [3]int

func (s DsnStatus) MarshalJSON() ([]byte, error) {
	return []byte(fmt.Sprintf(`"%d.%d.%d"`, s[0], s[1], s[2])), nil
}

var ErrNotDsnReport = errors.New("not a DSN report")
var ErrNoDsnRecipient = errors.New("DSN has no recipient")

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
		return nil, ErrNotDsnReport
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

	// Part2 is the machine-readable report (message/delivery-status)
	part2, err := readNextPartBodyAsMessage(multipartReader, []string{"message/delivery-status"})
	if err != nil {
		return nil, err
	}
	recipientsBody, _ := io.ReadAll(part2.Body)
	recipientFields := bytes.TrimSpace(bytes.Split(bytes.TrimSpace(recipientsBody), []byte("\n\n"))[0])

	if len(recipientFields) == 0 {
		return nil, ErrNoDsnRecipient
	}

	recipientFields = append(recipientFields, '\n', '\n')
	tp := textproto.NewReader(bufio.NewReader(bytes.NewReader(recipientFields)))

	headers, err := tp.ReadMIMEHeader()
	if err != nil {
		return nil, fmt.Errorf("error reading MIME header: %w", err)
	}

	dsn.EmailAddress = getDsnRecipientEmailAddress(headers.Get("Final-Recipient"))
	dsn.Status = parseDsnStatus(headers.Get("Status"))
	dsn.Action = headers.Get("Action")
	dsn.BounceReason = string(
		NewSmtpResponseParser(0, [3]int(dsn.Status), "").BounceReason(),
	)

	part3, err := readNextPartBodyAsMessage(multipartReader, []string{"message/rfc822", "message/rfc822-headers", "text/rfc822-headers"})
	if err == nil {
		dsn.RecipientUuid = strings.TrimSpace(part3.Header.Get(RecipientHeader))
	}

	return dsn, nil

}

// https://datatracker.ietf.org/doc/html/rfc3464#section-2.3.1
// Final-Recipient: rfc822;me@example.com
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
func parseDsnStatus(header string) DsnStatus {

	var status DsnStatus

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
