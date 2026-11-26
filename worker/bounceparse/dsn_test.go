package bounceparse

import (
	"os"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestDsnJsonMarshal(t *testing.T) {
	status := DsnStatus{5, 1, 1}

	jsonData, err := status.MarshalJSON()
	assert.Nil(t, err)

	assert.Equal(t, `"5.1.1"`, string(jsonData))
}

func TestDsnSimple(t *testing.T) {

	content, err := os.ReadFile("./testdata/dsn_simple.txt")
	assert.Nil(t, err)

	dsn, err := ParseDsn(content)
	assert.Nil(t, err)

	if err != nil {
		t.Fatalf("Error parsing DSN: %v", err)
	}

	assert.Contains(t, dsn.ReadableText, "The following addresses had delivery problems")

	assert.Equal(t, "louisl@larry.slip.umd.edu", dsn.EmailAddress)
	assert.Equal(t, DsnStatus([3]int{4, 0, 0}), dsn.Status)
	assert.Equal(t, "failed", dsn.Action)

}

func TestDsnMultiRecipientsParsesFirst(t *testing.T) {

	content, err := os.ReadFile("./testdata/dsn_multi_recipient.txt")
	assert.Nil(t, err)

	dsn, err := ParseDsn(content)

	if err != nil {
		t.Fatalf("Error parsing DSN: %s", err)
	}

	assert.Contains(t, dsn.ReadableText, "The following addresses had delivery problems")
	assert.Equal(t, "arathib@vnet.ibm.com", dsn.EmailAddress)
	assert.Equal(t, DsnStatus([3]int{5, 0, 0}), dsn.Status)
	assert.Equal(t, "failed", dsn.Action)

}

func TestDsnErrOnInvalidContentType(t *testing.T) {

	content, err := os.ReadFile("./testdata/dsn_invalid_content_type.txt")
	assert.Nil(t, err)

	_, err = ParseDsn(content)
	assert.Equal(t, ErrNotDsnReport, err)

}

func TestDsnErrOnPart2ContentType(t *testing.T) {

	content, err := os.ReadFile("./testdata/dsn_part2_invalid_content_type.txt")
	assert.Nil(t, err)

	_, err = ParseDsn(content)
	assert.Equal(t, ErrInvalidMimeType, err)
}

func TestGetRecipientEmailAd(t *testing.T) {

	email1 := getDsnRecipientEmailAddress("rfc822; supun@hyvor.com ")
	assert.Equal(t, "supun@hyvor.com", email1)

	email2 := getDsnRecipientEmailAddress(" supun@hyvor.com ")
	assert.Equal(t, "supun@hyvor.com", email2)

}

func TestDsnRecipientHeader(t *testing.T) {
	content, err := os.ReadFile("./testdata/dsn_with_original.txt")
	assert.Nil(t, err)

	dsn, err := ParseDsn(content)
	assert.Nil(t, err)
	assert.Equal(t, "5f0c8a0e-1b7e-4d37-9d52-6f3c2a1b9e10", dsn.RecipientUuid)
	assert.Equal(t, "louisl@larry.slip.umd.edu", dsn.EmailAddress)
}

func TestDsnWithoutRecipientHeader(t *testing.T) {
	content, err := os.ReadFile("./testdata/dsn_simple.txt")
	assert.Nil(t, err)

	dsn, err := ParseDsn(content)
	assert.Nil(t, err)
	assert.Equal(t, "", dsn.RecipientUuid)
}

func TestDsnErrOnNoRecipient(t *testing.T) {
	content := []byte("Content-Type: multipart/report; report-type=delivery-status; boundary=\"b\"\r\n\r\n" +
		"--b\r\n\r\nreadable\r\n--b\r\nContent-Type: message/delivery-status\r\n\r\nReporting-MTA: dns; x\r\n\r\n--b--\r\n")

	_, err := ParseDsn(content)
	assert.Equal(t, ErrNoDsnRecipient, err)
}
