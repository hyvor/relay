package main

import (
	"context"
	"encoding/json"
	"log"
	"os"
	"os/signal"
	"syscall"

	amqp "github.com/rabbitmq/amqp091-go"
)

// GoState object from backend
type GoState struct {
	Hostname string      `json:"hostname"`
	Ips      []GoStateIp `json:"ips"`
}

type GoStateIp struct {
	Ip       string `json:"ip"`
	Ptr      string `json:"ptr"`
	Queue    string `json:"queue"`
	Incoming bool   `json:"incoming"`
}

func main() {

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	emailPool := NewEmailWorkersPool(ctx)
	StartHttpServer(ctx, emailPool)

	// listenRabbitMq()

	<-ctx.Done()

	return

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

			sendEmail(&message, os.Stdout)
		}
	}()

	<-forever

}
