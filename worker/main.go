package main

import (
	"context"
	"os"
	"os/signal"
	"syscall"
)

func main() {

	StartBouncesServer()
	os.Exit(0)

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	// serviceState holds the state of the services (ex: email workers, etc.)
	serviceState := NewServiceState(ctx)

	// starting the local HTTP server so that symfony can call it to update the state if config changes
	StartHttpServer(ctx, serviceState)

	// tries to call /state symfony endpoint and get the state of the Go backend
	// and initialize the serviceState
	serviceState.Initialize()

	// listenRabbitMq()

	<-ctx.Done()

	serviceState.Logger.Info("Received shutdown signal, stopping services...")
}

type EmailSendMessage struct {
	SendId   int    `json:"sendId"`
	From     string `json:"from"`
	To       string `json:"to"`
	RawEmail string `json:"rawEmail"`
}

func listenRabbitMq() {

	/* log.Println("Connecting to RabbitMQ...")

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

			sendEmail(&message, os.Stdout)
		}
	}()

	<-forever */

}
