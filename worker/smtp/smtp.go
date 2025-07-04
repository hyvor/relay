package smtp

// Copyright 2010 The Go Authors. All rights reserved.
// Use of this source code is governed by a BSD-style
// license that can be found in the LICENSE file.

import (
	"crypto/tls"
	"errors"
	"fmt"
	"io"
	"net"
	"net/textproto"
	"strings"
)

// A Client represents a client connection to an SMTP server.
type Client struct {
	// Text is the textproto.Conn used by the Client. It is exported to allow for
	// clients to add extensions.
	Text *textproto.Conn
	// keep a reference to the connection so it can be used to create a TLS
	// connection later
	conn net.Conn
	// whether the Client is using TLS
	tls        bool
	serverName string
	// map of supported extensions
	ext         map[string]string
	localName   string        // the name to use in HELO/EHLO
	helloResult CommandResult // result of the last hello command
	didHello    bool          // whether we've said HELO/EHLO
}

// Dial returns a new [Client] connected to an SMTP server at addr.
// The addr must include a port, as in "mail.example.com:smtp".
func Dial(addr string) (*Client, error) {
	conn, err := net.Dial("tcp", addr)
	if err != nil {
		return nil, err
	}
	host, _, _ := net.SplitHostPort(addr)
	return NewClient(conn, host)
}

// NewClient returns a new [Client] using an existing connection and host as a
// server name to be used when authenticating.
func NewClient(conn net.Conn, host string) (*Client, error) {
	text := textproto.NewConn(conn)
	_, _, err := text.ReadResponse(220)
	if err != nil {
		text.Close()
		return nil, err
	}
	c := &Client{Text: text, conn: conn, serverName: host, localName: "localhost"}
	_, c.tls = conn.(*tls.Conn)
	return c, nil
}

// Close closes the connection.
func (c *Client) Close() error {
	return c.Text.Close()
}

// hello runs a hello exchange if needed.
func (c *Client) hello() CommandResult {
	if !c.didHello {
		c.didHello = true

		c.helloResult = c.ehlo()
		if c.helloResult.Err != nil || !c.helloResult.CodeValid(250) {
			c.helloResult = c.helo()
		}
	}
	return c.helloResult
}

// Hello sends a HELO or EHLO to the server as the given host name.
// Calling this method is only necessary if the client needs control
// over the host name used. The client will introduce itself as "localhost"
// automatically otherwise. If Hello is called, it must be called before
// any of the other methods.
// Verify with: 250
func (c *Client) Hello(localName string) CommandResult {
	if err := validateLine(localName); err != nil {
		return NewErrorCommandResult(err)
	}
	if c.didHello {
		return NewErrorCommandResult(errors.New("smtp: Hello called after other methods"))
	}
	c.localName = localName
	return c.hello()
}

// cmd is a convenience function that sends a command and returns the response
func (c *Client) cmd(format string, args ...any) CommandResult {
	commandResult := CommandResult{
		Command: fmt.Sprintf(format, args...),
	}

	id, err := c.Text.Cmd(format, args...)
	if err != nil {
		commandResult.Err = err
		return commandResult
	}
	c.Text.StartResponse(id)
	defer c.Text.EndResponse(id)
	code, msg, err := c.Text.ReadResponse(0) // 0 to disable code check

	if err != nil {
		commandResult.Err = err
		return commandResult
	}

	commandResult.Reply = &CommandReply{
		Code:    code,
		Message: msg,
	}

	return commandResult
}

// helo sends the HELO greeting to the server. It should be used only when the
// server does not support ehlo.
func (c *Client) helo() CommandResult {
	c.ext = nil
	return c.cmd("HELO %s", c.localName)
}

// ehlo sends the EHLO (extended hello) greeting to the server. It
// should be the preferred greeting for servers that support it.
func (c *Client) ehlo() CommandResult {
	commandResult := c.cmd("EHLO %s", c.localName)

	if commandResult.Err != nil {
		return commandResult
	}

	if commandResult.CodeValid(250) {
		// set extensions
		ext := make(map[string]string)
		extList := strings.Split(commandResult.Reply.Message, "\n")
		if len(extList) > 1 {
			extList = extList[1:]
			for _, line := range extList {
				k, v, _ := strings.Cut(line, " ")
				ext[k] = v
			}
		}
		c.ext = ext
	}

	return commandResult
}

// StartTLS sends the STARTTLS command and encrypts all further communication.
// Only servers that advertise the STARTTLS extension support this function.
// returns STARTTLS command result and the result of the EHLO command after
// Verify with: 220 and 250
func (c *Client) StartTLS(config *tls.Config) (CommandResult, CommandResult) {
	tlsResult := c.cmd("STARTTLS")

	if tlsResult.Err != nil {
		return tlsResult, CommandResult{}
	}

	c.conn = tls.Client(c.conn, config)
	c.Text = textproto.NewConn(c.conn)
	c.tls = true

	return tlsResult, c.ehlo()
}

// Mail issues a MAIL command to the server using the provided email address.
// If the server supports the 8BITMIME extension, Mail adds the BODY=8BITMIME
// parameter. If the server supports the SMTPUTF8 extension, Mail adds the
// SMTPUTF8 parameter.
// This initiates a mail transaction and is followed by one or more [Client.Rcpt] calls.
// Verify with: 250
func (c *Client) Mail(from string) CommandResult {
	if err := validateLine(from); err != nil {
		return NewErrorCommandResult(err)
	}
	cmdStr := "MAIL FROM:<%s>"
	if c.ext != nil {
		if _, ok := c.ext["8BITMIME"]; ok {
			cmdStr += " BODY=8BITMIME"
		}
		if _, ok := c.ext["SMTPUTF8"]; ok {
			cmdStr += " SMTPUTF8"
		}
	}
	return c.cmd(cmdStr, from)
}

// Rcpt issues a RCPT command to the server using the provided email address.
// A call to Rcpt must be preceded by a call to [Client.Mail] and may be followed by
// a [Client.Data] call or another Rcpt call.
// Verify with: 25
func (c *Client) Rcpt(to string) CommandResult {
	if err := validateLine(to); err != nil {
		return NewErrorCommandResult(err)
	}
	return c.cmd("RCPT TO:<%s>", to)
}

type dataCloser struct {
	c *Client
	io.WriteCloser
}

// Verify with: 250
func (d *dataCloser) Close() CommandResult {
	commandResult := CommandResult{}

	d.WriteCloser.Close()
	code, msg, err := d.c.Text.ReadResponse(0)

	if err != nil {
		commandResult.Err = err
		return commandResult
	}

	commandResult.Reply = &CommandReply{
		Code:    code,
		Message: msg,
	}

	return commandResult
}

// Data issues a DATA command to the server and returns a writer that
// can be used to write the mail headers and body. The caller should
// close the writer before calling any more methods on c. A call to
// Data must be preceded by one or more calls to [Client.Rcpt].
// Verify with: 354
func (c *Client) Data() (*dataCloser, CommandResult) {
	dataResult := c.cmd("DATA")

	if dataResult.Err != nil {
		return nil, dataResult
	}

	return &dataCloser{c, c.Text.DotWriter()}, dataResult
}

// Extension reports whether an extension is support by the server.
// The extension name is case-insensitive. If the extension is supported,
// Extension also returns a string that contains any parameters the
// server specifies for the extension.
func (c *Client) Extension(ext string) (bool, string) {
	if c.ext == nil {
		return false, ""
	}
	ext = strings.ToUpper(ext)
	param, ok := c.ext[ext]
	return ok, param
}

// Reset sends the RSET command to the server, aborting the current mail
// transaction.
// Verify with: 250
func (c *Client) Reset() CommandResult {
	return c.cmd("RSET")
}

// Noop sends the NOOP command to the server. It does nothing but check
// that the connection to the server is okay.
// Verify with: 250
func (c *Client) Noop() CommandResult {
	return c.cmd("NOOP")
}

// Quit sends the QUIT command and closes the connection to the server.
// Verify with: 221
func (c *Client) Quit() CommandResult {
	quitResult := c.cmd("QUIT")
	if quitResult.Err != nil {
		return quitResult
	}

	err := c.Text.Close()

	if err != nil {
		quitResult.Err = err
	}

	return quitResult
}

// validateLine checks to see if a line has CR or LF as per RFC 5321.
func validateLine(line string) error {
	if strings.ContainsAny(line, "\n\r") {
		return errors.New("smtp: A line must not contain CR or LF")
	}
	return nil
}
