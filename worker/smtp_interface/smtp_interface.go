package smtp_interface

import (
	"bytes"
	"encoding/base64"
	"errors"
	"io"
	"mime"
	"mime/multipart"
	"mime/quotedprintable"
	"net/mail"
	"net/textproto"
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

	multipartReader, err := messageToMultipartReader(message)
	if err != nil {
		return nil, err
	}

	var apiRequest = &ApiRequest{
		From:    parseAddressList(message.Header.Get("From"))[0],
		To:      parseAddressList(message.Header.Get("To")),
		Cc:      parseAddressList(message.Header.Get("Cc")),
		Bcc:     parseAddressList(message.Header.Get("Bcc")),
		Subject: message.Header.Get("Subject"),
		Headers: make(map[string]string),
	}

	// add the top-level custom headers
	addCustomHeaders(apiRequest, message.Header)

	err = walkMultipart(multipartReader, apiRequest, true)
	if err != nil {
		return nil, err
	}

	return apiRequest, nil

}

// artifically convert mail.Message to multipart.Reader
// this makes code nicer by reusing walkMultipart function
func messageToMultipartReader(message *mail.Message) (*multipart.Reader, error) {

	buf := &bytes.Buffer{}
    mw := multipart.NewWriter(buf)

    // Create a single part
    part, err := mw.CreatePart(textproto.MIMEHeader(message.Header))
    if err != nil {
        return nil, err
    }
	_, err = io.Copy(part, message.Body)
	if err != nil {
		return nil, err
	}

    mw.Close() // finalize multipart content

    // Create a reader with the same boundary
    mr := multipart.NewReader(buf, mw.Boundary())
    return mr, nil
	
}

func walkMultipart(mr *multipart.Reader, apiRequest *ApiRequest, top bool) error {

	var topBody []byte

	for {

		part, err := mr.NextRawPart()
		if err == io.EOF {
			break
		}
		if err != nil {
			return err
		}

		contentType := part.Header.Get("Content-Type")
		contentDisposition := part.Header.Get("Content-Disposition")

		mediaType, params, _ := mime.ParseMediaType(contentType)

		// nested multipart
		if strings.HasPrefix(mediaType, "multipart/") {
			nestedMR := multipart.NewReader(part, params["boundary"])
			if err := walkMultipart(nestedMR, apiRequest, false); err != nil {
				return err
			}
			continue
		}

		// attachments
		if strings.HasPrefix(contentDisposition, "attachment") || strings.HasPrefix(contentDisposition, "inline") {
			filename := part.FileName()

			var attachmentBase64 string

			if part.Header.Get("Content-Transfer-Encoding") == "base64" {
				// if already base64 encoded, read as is
				attachmentBase64Bytes, err := io.ReadAll(part)
				if err != nil {
					continue
				}
				attachmentBase64 = string(attachmentBase64Bytes)
			} else {
				// otherwise, read and encode to base64
				body, err := decodeMessageBody(part)
				if err != nil {
					continue
				}
				attachmentBase64 = base64.StdEncoding.EncodeToString(body)
			}

			apiRequest.Attachments = append(apiRequest.Attachments, Attachment{
				Name:        filename,
				Content:     attachmentBase64,
				ContentType: mediaType,
			})
			continue
		}

		// body parts
		body, err := decodeMessageBody(part)

		if err != nil {
			return err
		}

		switch mediaType {
		case "text/plain":
			if apiRequest.BodyText == "" {
				apiRequest.BodyText = string(body)
			}
		case "text/html":
			if apiRequest.BodyHtml == "" {
				apiRequest.BodyHtml = string(body)
			}
		default:
			// if this is the top-level part,
			// and if the content type is not known,
			// save it later so that we can use it as body text if no other body is found
			if top {
				topBody = body
			}
		}
	}

	// if this is the top-level part,
	// and no body text or html found,
	// use the saved topBody as body text
	if top && apiRequest.BodyText == "" && apiRequest.BodyHtml == "" {
		apiRequest.BodyText = string(topBody)
	}

	return nil

}

func decodeMessageBody(part *multipart.Part) ([]byte, error) {

	// get encoding
	encoding := part.Header.Get("Content-Transfer-Encoding")
	if encoding == "" {
		encoding = "7bit"
	}

	// get charset
	contentType := part.Header.Get("Content-Type")
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
		reader = base64.NewDecoder(base64.StdEncoding, part)
	case "quoted-printable":
		reader = quotedprintable.NewReader(part)
	default:
		reader = part
	}

	// convert to UTF-8 string
	utf8Reader, err := charset.NewReaderLabel(charsetName, reader)
	if err != nil {
        return nil, err
    }

	decoded, err := io.ReadAll(utf8Reader)
    if err != nil {
        return nil, err
    }

    return decoded, nil


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
