package bounceparse

import (
	"log"
	"os"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestParseArf(t *testing.T) {

	content, err := os.ReadFile("./testdata/arf1.txt")
	assert.Nil(t, err)

	arf, err := ParseArf(content)
	assert.Nil(t, err)

	log.Printf("arf: %+v\n", arf)

}
