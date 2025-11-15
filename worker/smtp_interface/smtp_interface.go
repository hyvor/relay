package smtp_interface

import (
	"bytes"
	"errors"
	"mime"
	"net/mail"
)

// converts a SMTP message to a API request compatible with Hyvor Relay's POST /api/console/sends

type ApiRequest struct {
	From        Address           `json:"from"`
	To          []Address         `json:"to"`
	Cc          []Address         `json:"cc,omitempty"`
	Bcc         []Address         `json:"bcc,omitempty"`
	Subject     string            `json:"subject"`
	BodyHtml    string            `json:"body_html,omitempty"`
	BodyText    string            `json:"body_text,omitempty"`
	Headers     map[string]string `json:"headers,omitempty"`
	Attachments []Attachment      `json:"attachments,omitempty"`
}

type Address struct {
	Email string `json:"email"`
	Name  string `json:"name,omitempty"`
}

type Attachment struct {
	Content     string `json:"content"` // base64 encoded
	Name        string `json:"name,omitempty"`
	ContentType string `json:"content_type,omitempty"`
}

var ErrNoFromHeader = errors.New("no From header found")

func MimeToApiRequest(mimeMessage []byte) (*ApiRequest, error) {

	message, err := mail.ReadMessage(bytes.NewReader(mimeMessage))

	if err != nil {
		return nil, err
	}

	fromAddresses := parseAddressList(message.Header.Get("From"))

	if len(fromAddresses) == 0 {
		return nil, ErrNoFromHeader
	}

	mediaType, params, err := mime.ParseMediaType(message.Header.Get("Content-Type"))
	if err != nil {
		mediaType = "text/plain"
	}

	var apiRequest = &ApiRequest{
		From:    parseAddressList(message.Header.Get("From"))[0],
		To:      parseAddressList(message.Header.Get("To")),
		Cc:      parseAddressList(message.Header.Get("Cc")),
		Bcc:     parseAddressList(message.Header.Get("Bcc")),
		Subject: message.Header.Get("Subject"),
		Headers: make(map[string]string),
	}

	// copy custom headers
	// TODO:

	// add body
	// wip:

	//

	return apiRequest, nil

}

func parseAddressList(raw string) []Address {

	if raw == "" {
		return nil
	}

	addrs, _ := mail.ParseAddressList(raw)
	result := make([]Address, 0, len(addrs))
	for _, a := range addrs {
		result = append(result, Address{
			Name:  a.Name,
			Email: a.Address,
		})
	}
	return result

}
