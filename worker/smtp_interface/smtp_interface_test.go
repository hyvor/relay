package smtp_interface

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestMimeToApiRequest(t *testing.T) {

	// get all .mime.txt files in the testdata/mapping directory
	files, err := filepath.Glob("testdata/mapping/*.eml")
	assert.NoError(t, err)

	for _, file := range files {

		t.Run(filepath.Base(file), func(t *testing.T) {

			// read the file content
			content, err := os.ReadFile(file)
			assert.NoError(t, err)

			errorFile := file[:len(file)-len(".eml")] + ".error"

			// if .error file exists, expect an error
			if _, err := os.Stat(errorFile); err == nil {
				expectedError, err := os.ReadFile(errorFile)
				assert.NoError(t, err)

				_, err = MimeToApiRequest(content)
				assert.Error(t, err)

				assert.Equal(t, string(expectedError), err.Error())
			} else {
				requestFile := file[:len(file)-len(".eml")] + ".json"
				expectedJson, err := os.ReadFile(requestFile)
				assert.NoError(t, err)

				apiRequest, err := MimeToApiRequest(content)
				assert.NoError(t, err)

				apiRequestJson, err := json.MarshalIndent(apiRequest, "", "  ")
				assert.NoError(t, err)

				assert.JSONEq(t, string(expectedJson), string(apiRequestJson))
			}
		})
	}
}
