package smtp

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestNewCommandReply(t *testing.T) {

	reply := NewCommandReply(250, "2.1.5 OK")

	assert.Equal(t, 250, reply.Code)
	assert.Equal(t, [3]int{2, 1, 5}, reply.EnhancedCode)
	assert.Equal(t, "OK", reply.Message)

	reply = NewCommandReply(550, "5.1.1 User unknown\n5.1.1 User unknown")

	assert.Equal(t, 550, reply.Code)
	assert.Equal(t, [3]int{5, 1, 1}, reply.EnhancedCode)
	assert.Equal(t, "User unknown\nUser unknown", reply.Message)

	reply = NewCommandReply(250, "OK without enhanced code")
	
	assert.Equal(t, 250, reply.Code)
	assert.Equal(t, [3]int{}, reply.EnhancedCode)
	assert.Equal(t, "OK without enhanced code", reply.Message)

}

func TestCodeValid(t *testing.T) {

	tests := []struct {
		name       string
		reply      CommandReply
		expectCode int
		want       bool
	}{
		{"Valid 2xx", CommandReply{Code: 250}, 2, true},
		{"Valid 20x", CommandReply{Code: 200}, 20, true},
		{"Valid 250", CommandReply{Code: 250}, 250, true},
		{"Invalid 3xx", CommandReply{Code: 300}, 2, false},
		{"Invalid 200", CommandReply{Code: 200}, 250, false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := CommandResult{Reply: &tt.reply}
			if got := result.CodeValid(tt.expectCode); got != tt.want {
				t.Errorf("CodeValid() = %v, want %v", got, tt.want)
			}
		})
	}

}
