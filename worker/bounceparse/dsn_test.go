package bounceparse

import (
	"os"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestDsnSimple(t *testing.T) {

	content, err := os.ReadFile("./testdata/dsn_simple.txt")
	assert.Nil(t, err)

	dsn, err := ParseDsn(content)
	assert.Nil(t, err)

	if err != nil {
		t.Fatalf("Error parsing DSN: %v", err)
	}

	assert.Contains(t, dsn.ReadableText, "The following addresses had delivery problems")

	assert.Equal(t, 1, len(dsn.Recipients))

	firstRecipient := dsn.Recipients[0]
	assert.Equal(t, "louisl@larry.slip.umd.edu", firstRecipient.EmailAddress)
	assert.Equal(t, DsnStatus([3]int{4, 0, 0}), firstRecipient.Status)
	assert.Equal(t, "failed", firstRecipient.Action)

}

func TestDsnMultiRecipients(t *testing.T) {

	content, err := os.ReadFile("./testdata/dsn_multi_recipient.txt")
	assert.Nil(t, err)

	dsn, err := ParseDsn(content)

	if err != nil {
		t.Fatalf("Error parsing DSN: %s", err)
	}

	assert.Contains(t, dsn.ReadableText, "The following addresses had delivery problems")

	assert.Equal(t, 3, len(dsn.Recipients))

	firstRecipient := dsn.Recipients[0]
	assert.Equal(t, "arathib@vnet.ibm.com", firstRecipient.EmailAddress)
	assert.Equal(t, DsnStatus([3]int{5, 0, 0}), firstRecipient.Status)
	assert.Equal(t, "failed", firstRecipient.Action)

	secondRecipient := dsn.Recipients[1]
	assert.Equal(t, "johnh@hpnjld.njd.hp.com", secondRecipient.EmailAddress)
	assert.Equal(t, DsnStatus([3]int{4, 0, 0}), secondRecipient.Status)
	assert.Equal(t, "delayed", secondRecipient.Action)

	thirdRecipient := dsn.Recipients[2]
	assert.Equal(t, "wsnell@sdcc13.ucsd.edu", thirdRecipient.EmailAddress)
	assert.Equal(t, DsnStatus([3]int{5, 1, 1}), thirdRecipient.Status)
	assert.Equal(t, "failed", thirdRecipient.Action)

}
