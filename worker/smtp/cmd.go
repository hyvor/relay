package smtp

type CommandResult struct {
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

// checks if the reply code matches the given prefix
// 1 means 1xx, 2 means 2xx, etc.
// 10 means 10x, 20 means 20x, etc.
func (r CommandResult) CodeValid(expectCode int) bool {
	if r.Reply == nil {
		return false
	}

	code := r.Reply.Code

	// https://cs.opensource.google/go/go/+/refs/tags/go1.24.4:src/net/textproto/reader.go;l=227
	return 1 <= expectCode && expectCode < 10 && code/100 == expectCode ||
		10 <= expectCode && expectCode < 100 && code/10 == expectCode ||
		100 <= expectCode && expectCode < 1000 && code == expectCode
}

type CommandReply struct {
	Code    int
	Message string
}

// EnhancedCode string // RFC 3463
// https://github.com/emersion/go-smtp/blob/master/client.go#L917
