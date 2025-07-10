package main

import (
	"database/sql"
	"fmt"
	"math/rand"
	"time"
)

func getTestDbConfig() *DBConfig {
	return &DBConfig{
		Host:     "hyvor-service-pgsql",
		Port:     "5432",
		User:     "postgres",
		Password: "postgres",
		DBName:   "hyvor_relay_testing",
		SSLMode:  "disable",
	}
}

func createNewTestDbConn() (*sql.DB, error) {
	return createNewDbConn(getTestDbConfig())
}

func truncateTestDb() error {
	conn, err := createNewTestDbConn()
	if err != nil {
		return err
	}
	defer conn.Close()

	_, err = conn.Exec(`
		DELETE FROM webhook_deliveries;
		DELETE FROM sends;
	`)

	if err != nil {
		return err
	}

	return nil
}

type TestFactory struct {
	conn *sql.DB
}

func NewTestFactory() (*TestFactory, error) {
	conn, err := createNewTestDbConn()
	if err != nil {
		return nil, err
	}

	return &TestFactory{conn: conn}, nil
}

func (f *TestFactory) Project() (int, error) {
	now := time.Now()
	randomUserId := rand.Intn(1000000) + 1
	randomName := fmt.Sprintf("Test Project %d", rand.Intn(1000000))

	var projectId int
	err := f.conn.QueryRow(`
		INSERT INTO projects (created_at, updated_at, hyvor_user_id, name)
		VALUES ($1, $2, $3, $4)
		RETURNING id
	`, now, now, randomUserId, randomName).Scan(&projectId)

	if err != nil {
		return 0, err
	}

	return projectId, nil
}

func (f *TestFactory) WebhookDelivery(url string, requestBody string) error {
	now := time.Now()

	// First create a project
	projectId, err := f.Project()
	if err != nil {
		return err
	}

	// Then create a webhook
	var webhookId int
	err = f.conn.QueryRow(`
		INSERT INTO webhooks (created_at, updated_at, project_id, url, description, events)
		VALUES ($1, $2, $3, $4, $5, $6)
		RETURNING id
	`, now, now, projectId, url, "Test webhook", `["test.event"]`).Scan(&webhookId)

	if err != nil {
		return err
	}

	// Finally create the webhook delivery
	_, err = f.conn.Exec(`
		INSERT INTO webhook_deliveries (created_at, updated_at, send_after, webhook_id, url, event, status, request_body, try_count)
		VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
	`, now, now, now, webhookId, url, "test.event", "pending", requestBody, 0)

	return err
}
