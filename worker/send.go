package main

import (
	"errors"
	"log"

	smtp "github.com/emersion/go-smtp"
)

var ErrSendEmailFailed = errors.New("failed to send email")

func sendEmail(message EmailSendMessage) error {

	log.Printf("Starting to send email. Getting MX records for: %s", message.To)

	mxHosts, err := getMxHostsFromEmail(message.To)

	if err != nil {
		log.Printf("Error getting MX records: %s", err)
		return err
	}

	log.Printf("MX records found: %v", mxHosts)

	for _, host := range mxHosts {
		log.Printf("Trying to send email to host: %s", host)

		err = sendEmailToHost(message, host)

		if err == nil {
			log.Printf("Email sent successfully to %s", message.To)
			return nil
		} else {
			log.Printf("Failed to send email to %s: %s", host, err)
		}
	}

	log.Printf("All attempts to send email failed for %s", message.To)
	return ErrSendEmailFailed

}

func sendEmailToHost(message EmailSendMessage, host string) error {

	log.Printf("SMTP host: %s", host)

	c, err := smtp.Dial(host + ":25")
	if err != nil {
		log.Printf("Error connecting to SMTP server: %s", err)
		return err
	}
	defer c.Close()

	if err := c.Hello("relay.hyvor.com"); err != nil {
		log.Printf("Error during HELO: %s", err)
		return err
	}

	if err := c.Mail(message.From, nil); err != nil {
		log.Fatal(err)
	}

	if err := c.Rcpt(message.To, nil); err != nil {
		log.Printf("Error during RCPT: %s", err)
		return err
	}

	w, err := c.Data()
	if err != nil {
		log.Printf("Error during DATA: %s", err)
		return err
	}

	_, err = w.Write([]byte(message.RawEmail))
	if err != nil {
		log.Printf("Error writing email data: %s", err)
		return err
	}

	if err := w.Close(); err != nil {
		log.Printf("Error closing email data: %s", err)
		return err
	}

	// TODO: do not throw error, call c.Close() directly
	if err := c.Quit(); err != nil {
		log.Printf("Error during QUIT: %s", err)
		return err
	}

	return nil

}
