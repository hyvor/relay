package main

import "strings"

const recipientHeaderName = "X-Relay-Recipient"

// Adds the X-Relay-Recipient header at the end of the headers
// The raw message is DKIM-signed once when it is built.
// This header is not part of the signed headers (h=), so adding it later does not invalidate the signatures and the message does not
// need to be rebuilt or signed again for each recipient.
func injectRecipientHeader(raw string, value string) string {
	header := "\r\n" + recipientHeaderName + ": " + value

	headersEnd := strings.Index(raw, "\r\n\r\n")
	if headersEnd == -1 {
		return raw + header
	}

	return raw[:headersEnd] + header + raw[headersEnd:]
}
