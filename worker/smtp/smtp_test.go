// Copyright 2010 The Go Authors. All rights reserved.
// Use of this source code is governed by a BSD-style
// license that can be found in the LICENSE file.

package smtp

import (
	"bufio"
	"crypto/tls"
	"crypto/x509"
	"io"
	"net"
	"net/textproto"
	"strings"
	"testing"
	"time"
)

type faker struct {
	io.ReadWriter
}

func (f faker) Close() error                     { return nil }
func (f faker) LocalAddr() net.Addr              { return nil }
func (f faker) RemoteAddr() net.Addr             { return nil }
func (f faker) SetDeadline(time.Time) error      { return nil }
func (f faker) SetReadDeadline(time.Time) error  { return nil }
func (f faker) SetWriteDeadline(time.Time) error { return nil }

func TestBasic(t *testing.T) {
	server := strings.Join(strings.Split(basicServer, "\n"), "\r\n")
	client := strings.Join(strings.Split(basicClient, "\n"), "\r\n")

	var cmdbuf strings.Builder
	bcmdbuf := bufio.NewWriter(&cmdbuf)
	var fake faker
	fake.ReadWriter = bufio.NewReadWriter(bufio.NewReader(strings.NewReader(server)), bcmdbuf)
	c := &Client{Text: textproto.NewConn(fake), localName: "localhost"}

	if helloResult := c.helo(); helloResult.Err != nil {
		t.Fatalf("HELO failed: %s", helloResult.Err)
	}
	if ehloResult := c.ehlo(); ehloResult.CodeValid(250) {
		t.Fatalf("Expected first EHLO to fail")
	}
	if ehloResult := c.ehlo(); ehloResult.Err != nil {
		t.Fatalf("Second EHLO failed: %s", ehloResult.Err)
	}

	c.didHello = true
	if ok, args := c.Extension("aUtH"); !ok || args != "LOGIN PLAIN" {
		t.Fatalf("Expected AUTH supported")
	}
	if ok, _ := c.Extension("DSN"); ok {
		t.Fatalf("Shouldn't support DSN")
	}

	if mailResult := c.Mail("user@gmail.com"); mailResult.CodeValid(250) {
		t.Fatalf("MAIL should require authentication")
	}

	// fake TLS so authentication won't complain
	c.tls = true
	c.serverName = "smtp.google.com"

	if rcptResult := c.Rcpt("golang-nuts@googlegroups.com>\r\nDATA\r\nInjected message body\r\n.\r\nQUIT\r\n"); rcptResult.Err == nil {
		t.Fatalf("RCPT should have failed due to a message injection attempt")
	}
	if mailErr := c.Mail("user@gmail.com>\r\nDATA\r\nAnother injected message body\r\n.\r\nQUIT\r\n"); mailErr.Err == nil {
		t.Fatalf("MAIL should have failed due to a message injection attempt")
	}
	if mailErr := c.Mail("user@gmail.com"); mailErr.Err != nil {
		t.Fatalf("MAIL failed: %s", mailErr.Err)
	}
	if rcptErr := c.Rcpt("golang-nuts@googlegroups.com"); rcptErr.Err != nil {
		t.Fatalf("RCPT failed: %s", rcptErr.Err)
	}
	msg := `From: user@gmail.com
To: golang-nuts@googlegroups.com
Subject: Hooray for Go

Line 1
.Leading dot line .
Goodbye.`
	w, dataResult := c.Data()
	if dataResult.Err != nil {
		t.Fatalf("DATA failed: %s", dataResult.Err)
	}
	if _, err := w.Write([]byte(msg)); err != nil {
		t.Fatalf("Data write failed: %s", err)
	}
	if err := w.Close(); err != nil {
		t.Fatalf("Bad data response: %s", err)
	}

	if quitResult := c.Quit(); quitResult.Err != nil {
		t.Fatalf("QUIT failed: %s", quitResult.Err)
	}

	bcmdbuf.Flush()
	actualcmds := cmdbuf.String()
	if client != actualcmds {
		t.Fatalf("Got:\n%s\nExpected:\n%s", actualcmds, client)
	}
}

var basicServer = `250 mx.google.com at your service
502 Unrecognized command.
250-mx.google.com at your service
250-SIZE 35651584
250-AUTH LOGIN PLAIN
250 8BITMIME
530 Authentication required
250 Sender OK
250 Receiver OK
354 Go ahead
250 Data OK
221 OK
`

var basicClient = `HELO localhost
EHLO localhost
EHLO localhost
MAIL FROM:<user@gmail.com> BODY=8BITMIME
MAIL FROM:<user@gmail.com> BODY=8BITMIME
RCPT TO:<golang-nuts@googlegroups.com>
DATA
From: user@gmail.com
To: golang-nuts@googlegroups.com
Subject: Hooray for Go

Line 1
..Leading dot line .
Goodbye.
.
QUIT
`

func TestHELOFailed(t *testing.T) {
	serverLines := `502 EH?
502 EH?
221 OK
`
	clientLines := `EHLO localhost
HELO localhost
QUIT
`

	server := strings.Join(strings.Split(serverLines, "\n"), "\r\n")
	client := strings.Join(strings.Split(clientLines, "\n"), "\r\n")
	var cmdbuf strings.Builder
	bcmdbuf := bufio.NewWriter(&cmdbuf)
	var fake faker
	fake.ReadWriter = bufio.NewReadWriter(bufio.NewReader(strings.NewReader(server)), bcmdbuf)
	c := &Client{Text: textproto.NewConn(fake), localName: "localhost"}

	if err := c.Hello("localhost"); err == nil {
		t.Fatal("expected EHLO to fail")
	}
	if err := c.Quit(); err != nil {
		t.Errorf("QUIT failed: %s", err)
	}
	bcmdbuf.Flush()
	actual := cmdbuf.String()
	if client != actual {
		t.Errorf("Got:\n%s\nWant:\n%s", actual, client)
	}
}

func TestExtensions(t *testing.T) {
	fake := func(server string) (c *Client, bcmdbuf *bufio.Writer, cmdbuf *strings.Builder) {
		server = strings.Join(strings.Split(server, "\n"), "\r\n")

		cmdbuf = &strings.Builder{}
		bcmdbuf = bufio.NewWriter(cmdbuf)
		var fake faker
		fake.ReadWriter = bufio.NewReadWriter(bufio.NewReader(strings.NewReader(server)), bcmdbuf)
		c = &Client{Text: textproto.NewConn(fake), localName: "localhost"}

		return c, bcmdbuf, cmdbuf
	}

	t.Run("helo", func(t *testing.T) {
		const (
			basicServer = `250 mx.google.com at your service
250 Sender OK
221 Goodbye
`

			basicClient = `HELO localhost
MAIL FROM:<user@gmail.com>
QUIT
`
		)

		c, bcmdbuf, cmdbuf := fake(basicServer)

		if err := c.helo(); err != nil {
			t.Fatalf("HELO failed: %s", err)
		}
		c.didHello = true
		if err := c.Mail("user@gmail.com"); err != nil {
			t.Fatalf("MAIL FROM failed: %s", err)
		}
		if err := c.Quit(); err != nil {
			t.Fatalf("QUIT failed: %s", err)
		}

		bcmdbuf.Flush()
		actualcmds := cmdbuf.String()
		client := strings.Join(strings.Split(basicClient, "\n"), "\r\n")
		if client != actualcmds {
			t.Fatalf("Got:\n%s\nExpected:\n%s", actualcmds, client)
		}
	})

	t.Run("ehlo", func(t *testing.T) {
		const (
			basicServer = `250-mx.google.com at your service
250 SIZE 35651584
250 Sender OK
221 Goodbye
`

			basicClient = `EHLO localhost
MAIL FROM:<user@gmail.com>
QUIT
`
		)

		c, bcmdbuf, cmdbuf := fake(basicServer)

		if err := c.Hello("localhost"); err != nil {
			t.Fatalf("EHLO failed: %s", err)
		}
		if ok, _ := c.Extension("8BITMIME"); ok {
			t.Fatalf("Shouldn't support 8BITMIME")
		}
		if ok, _ := c.Extension("SMTPUTF8"); ok {
			t.Fatalf("Shouldn't support SMTPUTF8")
		}
		if err := c.Mail("user@gmail.com"); err != nil {
			t.Fatalf("MAIL FROM failed: %s", err)
		}
		if err := c.Quit(); err != nil {
			t.Fatalf("QUIT failed: %s", err)
		}

		bcmdbuf.Flush()
		actualcmds := cmdbuf.String()
		client := strings.Join(strings.Split(basicClient, "\n"), "\r\n")
		if client != actualcmds {
			t.Fatalf("Got:\n%s\nExpected:\n%s", actualcmds, client)
		}
	})

	t.Run("ehlo 8bitmime", func(t *testing.T) {
		const (
			basicServer = `250-mx.google.com at your service
250-SIZE 35651584
250 8BITMIME
250 Sender OK
221 Goodbye
`

			basicClient = `EHLO localhost
MAIL FROM:<user@gmail.com> BODY=8BITMIME
QUIT
`
		)

		c, bcmdbuf, cmdbuf := fake(basicServer)

		if err := c.Hello("localhost"); err != nil {
			t.Fatalf("EHLO failed: %s", err)
		}
		if ok, _ := c.Extension("8BITMIME"); !ok {
			t.Fatalf("Should support 8BITMIME")
		}
		if ok, _ := c.Extension("SMTPUTF8"); ok {
			t.Fatalf("Shouldn't support SMTPUTF8")
		}
		if err := c.Mail("user@gmail.com"); err != nil {
			t.Fatalf("MAIL FROM failed: %s", err)
		}
		if err := c.Quit(); err != nil {
			t.Fatalf("QUIT failed: %s", err)
		}

		bcmdbuf.Flush()
		actualcmds := cmdbuf.String()
		client := strings.Join(strings.Split(basicClient, "\n"), "\r\n")
		if client != actualcmds {
			t.Fatalf("Got:\n%s\nExpected:\n%s", actualcmds, client)
		}
	})

	t.Run("ehlo smtputf8", func(t *testing.T) {
		const (
			basicServer = `250-mx.google.com at your service
250-SIZE 35651584
250 SMTPUTF8
250 Sender OK
221 Goodbye
`

			basicClient = `EHLO localhost
MAIL FROM:<user+📧@gmail.com> SMTPUTF8
QUIT
`
		)

		c, bcmdbuf, cmdbuf := fake(basicServer)

		if err := c.Hello("localhost"); err != nil {
			t.Fatalf("EHLO failed: %s", err)
		}
		if ok, _ := c.Extension("8BITMIME"); ok {
			t.Fatalf("Shouldn't support 8BITMIME")
		}
		if ok, _ := c.Extension("SMTPUTF8"); !ok {
			t.Fatalf("Should support SMTPUTF8")
		}
		if err := c.Mail("user+📧@gmail.com"); err != nil {
			t.Fatalf("MAIL FROM failed: %s", err)
		}
		if err := c.Quit(); err != nil {
			t.Fatalf("QUIT failed: %s", err)
		}

		bcmdbuf.Flush()
		actualcmds := cmdbuf.String()
		client := strings.Join(strings.Split(basicClient, "\n"), "\r\n")
		if client != actualcmds {
			t.Fatalf("Got:\n%s\nExpected:\n%s", actualcmds, client)
		}
	})

	t.Run("ehlo 8bitmime smtputf8", func(t *testing.T) {
		const (
			basicServer = `250-mx.google.com at your service
250-SIZE 35651584
250-8BITMIME
250 SMTPUTF8
250 Sender OK
221 Goodbye
	`

			basicClient = `EHLO localhost
MAIL FROM:<user+📧@gmail.com> BODY=8BITMIME SMTPUTF8
QUIT
`
		)

		c, bcmdbuf, cmdbuf := fake(basicServer)

		if err := c.Hello("localhost"); err != nil {
			t.Fatalf("EHLO failed: %s", err)
		}
		c.didHello = true
		if ok, _ := c.Extension("8BITMIME"); !ok {
			t.Fatalf("Should support 8BITMIME")
		}
		if ok, _ := c.Extension("SMTPUTF8"); !ok {
			t.Fatalf("Should support SMTPUTF8")
		}
		if err := c.Mail("user+📧@gmail.com"); err != nil {
			t.Fatalf("MAIL FROM failed: %s", err)
		}
		if err := c.Quit(); err != nil {
			t.Fatalf("QUIT failed: %s", err)
		}

		bcmdbuf.Flush()
		actualcmds := cmdbuf.String()
		client := strings.Join(strings.Split(basicClient, "\n"), "\r\n")
		if client != actualcmds {
			t.Fatalf("Got:\n%s\nExpected:\n%s", actualcmds, client)
		}
	})
}

func TestNewClient(t *testing.T) {
	server := strings.Join(strings.Split(newClientServer, "\n"), "\r\n")
	client := strings.Join(strings.Split(newClientClient, "\n"), "\r\n")

	var cmdbuf strings.Builder
	bcmdbuf := bufio.NewWriter(&cmdbuf)
	out := func() string {
		bcmdbuf.Flush()
		return cmdbuf.String()
	}
	var fake faker
	fake.ReadWriter = bufio.NewReadWriter(bufio.NewReader(strings.NewReader(server)), bcmdbuf)
	c, err := NewClient(fake, "fake.host")
	if err != nil {
		t.Fatalf("NewClient: %v\n(after %v)", err, out())
	}
	defer c.Close()
	if ok, args := c.Extension("aUtH"); !ok || args != "LOGIN PLAIN" {
		t.Fatalf("Expected AUTH supported")
	}
	if ok, _ := c.Extension("DSN"); ok {
		t.Fatalf("Shouldn't support DSN")
	}
	if err := c.Quit(); err != nil {
		t.Fatalf("QUIT failed: %s", err)
	}

	actualcmds := out()
	if client != actualcmds {
		t.Fatalf("Got:\n%s\nExpected:\n%s", actualcmds, client)
	}
}

var newClientServer = `220 hello world
250-mx.google.com at your service
250-SIZE 35651584
250-AUTH LOGIN PLAIN
250 8BITMIME
221 OK
`

var newClientClient = `EHLO localhost
QUIT
`

func TestNewClient2(t *testing.T) {
	server := strings.Join(strings.Split(newClient2Server, "\n"), "\r\n")
	client := strings.Join(strings.Split(newClient2Client, "\n"), "\r\n")

	var cmdbuf strings.Builder
	bcmdbuf := bufio.NewWriter(&cmdbuf)
	var fake faker
	fake.ReadWriter = bufio.NewReadWriter(bufio.NewReader(strings.NewReader(server)), bcmdbuf)
	c, err := NewClient(fake, "fake.host")
	if err != nil {
		t.Fatalf("NewClient: %v", err)
	}
	defer c.Close()
	if ok, _ := c.Extension("DSN"); ok {
		t.Fatalf("Shouldn't support DSN")
	}
	if err := c.Quit(); err != nil {
		t.Fatalf("QUIT failed: %s", err)
	}

	bcmdbuf.Flush()
	actualcmds := cmdbuf.String()
	if client != actualcmds {
		t.Fatalf("Got:\n%s\nExpected:\n%s", actualcmds, client)
	}
}

var newClient2Server = `220 hello world
502 EH?
250-mx.google.com at your service
250-SIZE 35651584
250-AUTH LOGIN PLAIN
250 8BITMIME
221 OK
`

var newClient2Client = `EHLO localhost
HELO localhost
QUIT
`

func TestNewClientWithTLS(t *testing.T) {
	cert, err := tls.X509KeyPair(localhostCert, localhostKey)
	if err != nil {
		t.Fatalf("loadcert: %v", err)
	}

	config := tls.Config{Certificates: []tls.Certificate{cert}}

	ln, err := tls.Listen("tcp", "127.0.0.1:0", &config)
	if err != nil {
		ln, err = tls.Listen("tcp", "[::1]:0", &config)
		if err != nil {
			t.Fatalf("server: listen: %v", err)
		}
	}

	go func() {
		conn, err := ln.Accept()
		if err != nil {
			t.Errorf("server: accept: %v", err)
			return
		}
		defer conn.Close()

		_, err = conn.Write([]byte("220 SIGNS\r\n"))
		if err != nil {
			t.Errorf("server: write: %v", err)
			return
		}
	}()

	config.InsecureSkipVerify = true
	conn, err := tls.Dial("tcp", ln.Addr().String(), &config)
	if err != nil {
		t.Fatalf("client: dial: %v", err)
	}
	defer conn.Close()

	client, err := NewClient(conn, ln.Addr().String())
	if err != nil {
		t.Fatalf("smtp: newclient: %v", err)
	}
	if !client.tls {
		t.Errorf("client.tls Got: %t Expected: %t", client.tls, true)
	}
}

func TestHello(t *testing.T) {

	if len(helloServer) != len(helloClient) {
		t.Fatalf("Hello server and client size mismatch")
	}

	for i := 0; i < len(helloServer); i++ {
		server := strings.Join(strings.Split(baseHelloServer+helloServer[i], "\n"), "\r\n")
		client := strings.Join(strings.Split(baseHelloClient+helloClient[i], "\n"), "\r\n")
		var cmdbuf strings.Builder
		bcmdbuf := bufio.NewWriter(&cmdbuf)
		var fake faker
		fake.ReadWriter = bufio.NewReadWriter(bufio.NewReader(strings.NewReader(server)), bcmdbuf)
		c, err := NewClient(fake, "fake.host")
		if err != nil {
			t.Fatalf("NewClient: %v", err)
		}
		defer c.Close()
		c.localName = "customhost"
		err = nil

		switch i {
		case 0:
			err = c.Hello("hostinjection>\n\rDATA\r\nInjected message body\r\n.\r\nQUIT\r\n")
			if err == nil {
				t.Errorf("Expected Hello to be rejected due to a message injection attempt")
			}
			err = c.Hello("customhost")
		case 1:
			err = c.StartTLS(nil)
			if err.Error() == "502 Not implemented" {
				err = nil
			}
		case 2:
			err = c.Verify("test@example.com")
		case 3:
			continue
		case 4:
			err = c.Mail("test@example.com")
		case 5:
			ok, _ := c.Extension("feature")
			if ok {
				t.Errorf("Expected FEATURE not to be supported")
			}
		case 6:
			err = c.Reset()
		case 7:
			err = c.Quit()
		case 8:
			err = c.Verify("test@example.com")
			if err != nil {
				err = c.Hello("customhost")
				if err != nil {
					t.Errorf("Want error, got none")
				}
			}
		case 9:
			err = c.Noop()
		default:
			t.Fatalf("Unhandled command")
		}

		if err != nil {
			t.Errorf("Command %d failed: %v", i, err)
		}

		bcmdbuf.Flush()
		actualcmds := cmdbuf.String()
		if client != actualcmds {
			t.Errorf("Got:\n%s\nExpected:\n%s", actualcmds, client)
		}
	}
}

var baseHelloServer = `220 hello world
502 EH?
250-mx.google.com at your service
250 FEATURE
`

var helloServer = []string{
	"",
	"502 Not implemented\n",
	"250 User is valid\n",
	"235 Accepted\n",
	"250 Sender ok\n",
	"",
	"250 Reset ok\n",
	"221 Goodbye\n",
	"250 Sender ok\n",
	"250 ok\n",
}

var baseHelloClient = `EHLO customhost
HELO customhost
`

var helloClient = []string{
	"",
	"STARTTLS\n",
	"VRFY test@example.com\n",
	"AUTH PLAIN AHVzZXIAcGFzcw==\n",
	"MAIL FROM:<test@example.com>\n",
	"",
	"RSET\n",
	"QUIT\n",
	"VRFY test@example.com\n",
	"NOOP\n",
}

func TestTLSConnState(t *testing.T) {
	ln := newLocalListener(t)
	defer ln.Close()
	clientDone := make(chan bool)
	serverDone := make(chan bool)
	go func() {
		defer close(serverDone)
		c, err := ln.Accept()
		if err != nil {
			t.Errorf("Server accept: %v", err)
			return
		}
		defer c.Close()
		if err := serverHandle(c, t); err != nil {
			t.Errorf("server error: %v", err)
		}
	}()
	go func() {
		defer close(clientDone)
		c, err := Dial(ln.Addr().String())
		if err != nil {
			t.Errorf("Client dial: %v", err)
			return
		}
		defer c.Quit()
		cfg := &tls.Config{ServerName: "example.com"}
		testHookStartTLS(cfg) // set the RootCAs
		if err := c.StartTLS(cfg); err != nil {
			t.Errorf("StartTLS: %v", err)
			return
		}
		cs, ok := c.TLSConnectionState()
		if !ok {
			t.Errorf("TLSConnectionState returned ok == false; want true")
			return
		}
		if cs.Version == 0 || !cs.HandshakeComplete {
			t.Errorf("ConnectionState = %#v; expect non-zero Version and HandshakeComplete", cs)
		}
	}()
	<-clientDone
	<-serverDone
}

func newLocalListener(t *testing.T) net.Listener {
	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		ln, err = net.Listen("tcp6", "[::1]:0")
	}
	if err != nil {
		t.Fatal(err)
	}
	return ln
}

type smtpSender struct {
	w io.Writer
}

func (s smtpSender) send(f string) {
	s.w.Write([]byte(f + "\r\n"))
}

// smtp server, finely tailored to deal with our own client only!
func serverHandle(c net.Conn, t *testing.T) error {
	send := smtpSender{c}.send
	send("220 127.0.0.1 ESMTP service ready")
	s := bufio.NewScanner(c)
	for s.Scan() {
		switch s.Text() {
		case "EHLO localhost":
			send("250-127.0.0.1 ESMTP offers a warm hug of welcome")
			send("250-STARTTLS")
			send("250 Ok")
		case "STARTTLS":
			send("220 Go ahead")
			keypair, err := tls.X509KeyPair(localhostCert, localhostKey)
			if err != nil {
				return err
			}
			config := &tls.Config{Certificates: []tls.Certificate{keypair}}
			c = tls.Server(c, config)
			defer c.Close()
			return serverHandleTLS(c, t)
		default:
			t.Fatalf("unrecognized command: %q", s.Text())
		}
	}
	return s.Err()
}

func serverHandleTLS(c net.Conn, t *testing.T) error {
	send := smtpSender{c}.send
	s := bufio.NewScanner(c)
	for s.Scan() {
		switch s.Text() {
		case "EHLO localhost":
			send("250 Ok")
		case "MAIL FROM:<joe1@example.com>":
			send("250 Ok")
		case "RCPT TO:<joe2@example.com>":
			send("250 Ok")
		case "DATA":
			send("354 send the mail data, end with .")
			send("250 Ok")
		case "Subject: test":
		case "":
		case "howdy!":
		case ".":
		case "QUIT":
			send("221 127.0.0.1 Service closing transmission channel")
			return nil
		default:
			t.Fatalf("unrecognized command during TLS: %q", s.Text())
		}
	}
	return s.Err()
}

func init() {
	testRootCAs := x509.NewCertPool()
	testRootCAs.AppendCertsFromPEM(localhostCert)
	testHookStartTLS = func(config *tls.Config) {
		config.RootCAs = testRootCAs
	}
}

// localhostCert is a PEM-encoded TLS cert generated from src/crypto/tls:
//
//	go run generate_cert.go --rsa-bits 1024 --host 127.0.0.1,::1,example.com \
//		--ca --start-date "Jan 1 00:00:00 1970" --duration=1000000h
var localhostCert = []byte(`
-----BEGIN CERTIFICATE-----
MIICFDCCAX2gAwIBAgIRAK0xjnaPuNDSreeXb+z+0u4wDQYJKoZIhvcNAQELBQAw
EjEQMA4GA1UEChMHQWNtZSBDbzAgFw03MDAxMDEwMDAwMDBaGA8yMDg0MDEyOTE2
MDAwMFowEjEQMA4GA1UEChMHQWNtZSBDbzCBnzANBgkqhkiG9w0BAQEFAAOBjQAw
gYkCgYEA0nFbQQuOWsjbGtejcpWz153OlziZM4bVjJ9jYruNw5n2Ry6uYQAffhqa
JOInCmmcVe2siJglsyH9aRh6vKiobBbIUXXUU1ABd56ebAzlt0LobLlx7pZEMy30
LqIi9E6zmL3YvdGzpYlkFRnRrqwEtWYbGBf3znO250S56CCWH2UCAwEAAaNoMGYw
DgYDVR0PAQH/BAQDAgKkMBMGA1UdJQQMMAoGCCsGAQUFBwMBMA8GA1UdEwEB/wQF
MAMBAf8wLgYDVR0RBCcwJYILZXhhbXBsZS5jb22HBH8AAAGHEAAAAAAAAAAAAAAA
AAAAAAEwDQYJKoZIhvcNAQELBQADgYEAbZtDS2dVuBYvb+MnolWnCNqvw1w5Gtgi
NmvQQPOMgM3m+oQSCPRTNGSg25e1Qbo7bgQDv8ZTnq8FgOJ/rbkyERw2JckkHpD4
n4qcK27WkEDBtQFlPihIM8hLIuzWoi/9wygiElTy/tVL3y7fGCvY2/k1KBthtZGF
tN8URjVmyEo=
-----END CERTIFICATE-----`)

// localhostKey is the private key for localhostCert.
var localhostKey = []byte(testingKey(`
-----BEGIN RSA TESTING KEY-----
MIICXgIBAAKBgQDScVtBC45ayNsa16NylbPXnc6XOJkzhtWMn2Niu43DmfZHLq5h
AB9+Gpok4icKaZxV7ayImCWzIf1pGHq8qKhsFshRddRTUAF3np5sDOW3QuhsuXHu
lkQzLfQuoiL0TrOYvdi90bOliWQVGdGurAS1ZhsYF/fOc7bnRLnoIJYfZQIDAQAB
AoGBAMst7OgpKyFV6c3JwyI/jWqxDySL3caU+RuTTBaodKAUx2ZEmNJIlx9eudLA
kucHvoxsM/eRxlxkhdFxdBcwU6J+zqooTnhu/FE3jhrT1lPrbhfGhyKnUrB0KKMM
VY3IQZyiehpxaeXAwoAou6TbWoTpl9t8ImAqAMY8hlULCUqlAkEA+9+Ry5FSYK/m
542LujIcCaIGoG1/Te6Sxr3hsPagKC2rH20rDLqXwEedSFOpSS0vpzlPAzy/6Rbb
PHTJUhNdwwJBANXkA+TkMdbJI5do9/mn//U0LfrCR9NkcoYohxfKz8JuhgRQxzF2
6jpo3q7CdTuuRixLWVfeJzcrAyNrVcBq87cCQFkTCtOMNC7fZnCTPUv+9q1tcJyB
vNjJu3yvoEZeIeuzouX9TJE21/33FaeDdsXbRhQEj23cqR38qFHsF1qAYNMCQQDP
QXLEiJoClkR2orAmqjPLVhR3t2oB3INcnEjLNSq8LHyQEfXyaFfu4U9l5+fRPL2i
jiC0k/9L5dHUsF0XZothAkEA23ddgRs+Id/HxtojqqUT27B8MT/IGNrYsp4DvS/c
qgkeluku4GjxRlDMBuXk94xOBEinUs+p/hwP1Alll80Tpg==
-----END RSA TESTING KEY-----`))

func testingKey(s string) string { return strings.ReplaceAll(s, "TESTING KEY", "PRIVATE KEY") }
