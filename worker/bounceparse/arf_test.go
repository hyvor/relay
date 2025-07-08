package bounceparse

import (
	"os"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestParseArf(t *testing.T) {

	content, err := os.ReadFile("./testdata/arf1.txt")
	assert.Nil(t, err)

	arf, err := ParseArf(content)
	assert.Nil(t, err)

	assert.Equal(t, "abuse", arf.FeedbackType)
	assert.Equal(t, "SomeGenerator/1.0", arf.UserAgent)
	assert.Equal(t, "<somespammer@example.net>", arf.OriginalMailFrom)
	assert.Equal(t, "8787KJKJ3K4J3K4J3K4J3.mail@example.net", arf.MessageId)
	assert.Contains(t, arf.ReadableText, "This is an email abuse report for an email message received from IP")

}
