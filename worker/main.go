package main

import (
	"encoding/json"
	"log"

	smtp "github.com/emersion/go-smtp"
	amqp "github.com/rabbitmq/amqp091-go"
)

func main() {

	listenRabbitMq()
	return

	// ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	// defer stop()

	// emailWorkersState := NewEmailWorkersState()
	// srv := NewHttpServer(emailWorkersState)

	// go func() {

	// 	httpServer := &http.Server{
	// 		Addr:    "localhost:8085",
	// 		Handler: srv,
	// 	}

	// 	if err := httpServer.ListenAndServe(); err != nil && err != http.ErrServerClosed {
	// 		fmt.Fprintf(os.Stderr, "error listening and serving: %s\n", err)
	// 	}

	// }()

	// <-ctx.Done()
	// fmt.Println("Shutting down server...")

	// emailWorkersState.StopWorkers()

	// log.Println("Server stopped gracefully")

}

type EmailSendMessage struct {
	SendId   int    `json:"sendId"`
	From     string `json:"from"`
	To       string `json:"to"`
	RawEmail string `json:"rawEmail"`
}

func listenRabbitMq() {

	log.Println("Connecting to RabbitMQ...")

	conn, err := amqp.Dial("amqp://rabbit:rabbit@hyvor-relay-rabbitmq:5672/")
	if err != nil {
		log.Fatalf("Failed to connect to RabbitMQ: %s", err)
	}
	defer conn.Close()

	ch, err := conn.Channel()
	if err != nil {
		log.Fatalf("Failed to open a channel: %s", err)
	}
	defer ch.Close()

	msgs, err := ch.Consume(
		"email", // queue
		"",      // consumer
		true,    // auto-ack
		false,   // exclusive
		false,   // no-local
		false,   // no-wait
		nil,     // args
	)
	if err != nil {
		log.Fatalf("Failed to register a consumer: %v", err)
	}

	log.Println("Waiting for messages. To exit press CTRL+C")

	forever := make(chan bool)

	go func() {

		for d := range msgs {

			var message EmailSendMessage
			log.Printf("Received a message: %s", d.Body)
			err := json.Unmarshal(d.Body, &message)

			if err != nil {
				log.Printf("Error unmarshalling message: %s", err)
				continue
			}

			sendEmail(message)
		}
	}()

	<-forever

}

func sendEmail(message EmailSendMessage) {

	log.Printf("Sending email")

	c, err := smtp.Dial("hyvor-service-mailpit:1025")
	if err != nil {
		log.Printf("Error connecting to SMTP server: %s", err)
		return
	}
	defer c.Close()

	err = c.Hello("relay.hyvor.com")
	if err != nil {
		log.Printf("Error during HELO: %s", err)
		return
	}

	if err := c.Mail(message.From, nil); err != nil {
		log.Fatal(err)
	}

	if err := c.Rcpt(message.To, nil); err != nil {
		log.Printf("Error during RCPT: %s", err)
		return
	}

	w, err := c.Data()
	if err != nil {
		log.Printf("Error during DATA: %s", err)
		return
	}

	_, err = w.Write([]byte(message.RawEmail))
	if err != nil {
		log.Printf("Error writing email data: %s", err)
		return
	}

	if err := w.Close(); err != nil {
		log.Printf("Error closing email data: %s", err)
		return
	}

	log.Printf("Email sent successfully to %s", message.To)
	if err := c.Quit(); err != nil {
		log.Printf("Error during QUIT: %s", err)
		return
	}

}
