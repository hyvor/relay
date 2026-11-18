package smtp_interface

import (
	"net/mail"
	"slices"
	"strings"
)

func addCustomHeaders(apiReq *ApiRequest, header mail.Header) {

	// IMPORTANT: This must be kept in sync with HeadersValidator.php
	var unallowed = []string{

		// emails
		"from",
		"to",
		"cc",
		"bcc",
		"sender",

		// other (already set by symfony)
		"date",
		"subject",
		"content-type",
		"mime-version",
		"content-transfer-encoding",
		"content-disposition",
		"message-id",

		// security
		"dkim-signature",
		"return-path",
		"x-mailer",
		"x-originating-ip",
		"authentication-results",

	}

	for key, values := range header {
		lowerKey := strings.ToLower(key)

		// ignore unallowed headers
		if slices.Contains(unallowed, lowerKey) {
			continue
		}

		apiReq.Headers[key] = values[0]
	}


}