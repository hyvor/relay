package main

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestGenerateTLSCertificates(t *testing.T) {	

	err := generateTLSCertificates()
	assert.NoError(t, err)

}