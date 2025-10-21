package smtp

import (
	"fmt"
	"strconv"
	"strings"
)

type CommandResult struct {

	// Command that was executed.
	Command string

	// Err is set if the command failed (protocol error, network error, malformed command)
	// If the command was successful, Err is nil.

	// Ex: When sending an email, if Err is set,
	// you would retry sending the email again later or to another MX server.
	Err error

	// Reply is non-nil if the command was successful.
	// It contains the server's reply to the command.
	Reply *CommandReply
}

func NewErrorCommandResult(err error) CommandResult {
	return CommandResult{
		Err: err,
	}
}

func (r CommandResult) CodeValid(expectCode int) bool {
	if r.Reply == nil {
		return false
	}
	return IsSmtpCode(r.Reply.Code, expectCode)
}

// checks if the reply code matches the given expectCode **prefix**.
// 1 means 1xx, 2 means 2xx, etc.
// 10 means 10x, 20 means 20x, etc.
func IsSmtpCode(code int, expectCode int) bool {
	// https://cs.opensource.google/go/go/+/refs/tags/go1.24.4:src/net/textproto/reader.go;l=227
	return 1 <= expectCode && expectCode < 10 && code/100 == expectCode ||
		10 <= expectCode && expectCode < 100 && code/10 == expectCode ||
		100 <= expectCode && expectCode < 1000 && code == expectCode
}

type CommandReply struct {
	Code    int
	EnhancedCode [3]int
	Message string
}

func NewCommandReply(code int, message string) *CommandReply {
	
	reply := &CommandReply{
		Code:    code,
		Message: message,
	}

	
	// EnhancedCode string // RFC 3463
	// https://github.com/emersion/go-smtp/blob/master/client.go#L917

	parts := strings.SplitN(message, " ", 2)
	if len(parts) != 2 {
		return reply
	}

	enchCode, err := parseEnhancedCode(parts[0])
	if err != nil {
		return reply
	}

	// Per RFC 2034, enhanced code should be prepended to each line.
	msg := parts[1]
	msg = strings.ReplaceAll(msg, "\n"+parts[0]+" ", "\n")

	reply.EnhancedCode = enchCode
	reply.Message = msg
	
	return reply

}


func parseEnhancedCode(s string) ([3]int, error) {
	parts := strings.Split(s, ".")
	if len(parts) != 3 {
		return [3]int{}, fmt.Errorf("wrong amount of enhanced code parts")
	}

	code := [3]int{}
	for i, part := range parts {
		num, err := strconv.Atoi(part)
		if err != nil {
			return code, err
		}
		code[i] = num
	}
	return code, nil
}