package smtp

import "testing"

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
