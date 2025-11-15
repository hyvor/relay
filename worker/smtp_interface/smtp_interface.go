package smtp_interface

import (
	"bytes"
	"encoding/base64"
	"errors"
	"io"
	"mime"
	"mime/quotedprintable"
	"net/mail"
	"strings"

	"golang.org/x/net/html/charset"
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

	mediaType, _, err := mime.ParseMediaType(message.Header.Get("Content-Type"))
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

	if strings.HasPrefix(mediaType, "multipart/") {
		// walk multipart parts
		// mr := multipart.NewReader(message.Body, params["boundary"])	
	} else {

		body, err := decodeMessageBody(message)

		if err != nil {
			return nil, err
		}

		if mediaType == "text/html" {
			apiRequest.BodyHtml = body
		} else {
			apiRequest.BodyText = body
		}

	}

	return apiRequest, nil

}

func decodeMessageBody(message *mail.Message) (string, error) {

	// get encoding
	encoding := message.Header.Get("Content-Transfer-Encoding")
	if encoding == "" {
		encoding = "7bit"
	}

	// get charset
	contentType := message.Header.Get("Content-Type")
	_, params, err := mime.ParseMediaType(contentType)
	if err != nil {
		params = map[string]string{}
	}
	charsetName := params["charset"]
	if charsetName == "" {
		charsetName = "utf-8"
	}

	// decode body based on encoding

	var reader io.Reader

	switch strings.ToLower(encoding) {
	case "base64":
		reader = base64.NewDecoder(base64.StdEncoding, message.Body)
	case "quoted-printable":
		reader = quotedprintable.NewReader(message.Body)
	default:
		reader = message.Body
	}

	// convert to UTF-8 string
	utf8Reader, err := charset.NewReaderLabel(charsetName, reader)
	if err != nil {
        return "", err
    }

	decoded, err := io.ReadAll(utf8Reader)
    if err != nil {
        return "", err
    }

    return string(decoded), nil


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
