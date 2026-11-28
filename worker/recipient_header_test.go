package main

import "testing"

func TestInjectRecipientHeader(t *testing.T) {
	raw := "DKIM-Signature: v=1\r\nFrom: a@b.com\r\nX-Mailer: Hyvor Relay v1\r\nSubject: Hi\r\n\r\nX-Mailer: body\r\n"
	got := injectRecipientHeader(raw, "uuid-12")
	want := "DKIM-Signature: v=1\r\nFrom: a@b.com\r\nX-Mailer: Hyvor Relay v1\r\nSubject: Hi\r\nX-Relay-Recipient: uuid-12\r\n\r\nX-Mailer: body\r\n"
	if got != want {
		t.Fatalf("got %q, want %q", got, want)
	}
}

func TestInjectRecipientHeaderWithoutBody(t *testing.T) {
	got := injectRecipientHeader("Subject: Hi", "uuid-1")
	want := "Subject: Hi\r\nX-Relay-Recipient: uuid-1"
	if got != want {
		t.Fatalf("got %q, want %q", got, want)
	}
}
