package main

import (
	"log"

	amqp "github.com/rabbitmq/amqp091-go"
)

func failOnError(err error, msg string) {
	if err != nil {
		log.Panicf("%s: %s", msg, err)
	}
}

func main() {

	conn, err := amqp.Dial("amqp://rabbit:rabbit@hyvor-relay-rabbitmq:5672/")
	failOnError(err, "Failed to connect to RabbitMQ")
	defer conn.Close()

	ch, err := conn.Channel()
	failOnError(err, "Failed to open a channel")

	// make sure the exchange exists
	err = ch.ExchangeDeclare(
		"email",
		"direct",
		true,
		false,
		false,
		false,
		nil,
	)
	failOnError(err, "Failed to declare an exchange")

	// make sure the queue exists
	// here we are only checking the email_transactional queue, but will need more later from DB
	queueName := "email_transactional"
	q, err := ch.QueueDeclare(queueName, true, false, false, false, nil)
	failOnError(err, "Failed to declare a queue")

	// make sure the exchange is bound to the queue with the given routing key
	routingKey := "email.transactional"
	ch.QueueBind(q.Name, routingKey, "email", false, nil)

	// consume the queue
	msgs, err := ch.Consume(q.Name, "", true, false, false, false, nil)
	failOnError(err, "Failed to register a consumer")

	log.Printf("Waiting for messages in queue: %s", q.Name)

	for d := range msgs {
		log.Printf("Transactional Email: %s", d.Body)
		// handle JSON and send email
	}

}
