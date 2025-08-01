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
		DELETE FROM projects;
		DELETE FROM webhooks;
		DELETE FROM webhook_deliveries;
		DELETE FROM sends;
		DELETE FROM suppressions;
		DELETE FROM debug_incoming_emails;
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

func (f *TestFactory) Domain(projectId int, domain string) (int, error) {
	now := time.Now()

	var domainId int
	err := f.conn.QueryRow(`
		INSERT INTO domains (created_at, updated_at, project_id, domain, dkim_selector, dkim_public_key, dkim_private_key_encrypted)
		VALUES ($1, $2, $3, $4, 'selector', 'public_key', 'encrypted_private_key')
		RETURNING id
	`, now, now, projectId, domain).Scan(&domainId)

	if err != nil {
		return 0, err
	}

	return domainId, nil
}

func (f *TestFactory) Queue() (int, error) {
	now := time.Now()

	name := fmt.Sprintf("test-queue-%d", rand.Intn(1000000))

	var queueId int
	err := f.conn.QueryRow(`
		INSERT INTO queues (created_at, updated_at, name)
		VALUES ($1, $2, $3)
		RETURNING id
	`, now, now, name).Scan(&queueId)

	if err != nil {
		return 0, err
	}

	return queueId, nil
}

type FactorySend struct {
	Id          int
	Uuid        string
	ProjectId   int
	DomainId    int
	QueueId     int
	FromAddress string
	ToAddress   string
	Subject     string
	BodyHtml    string
	BodyText    string
}

func (m *TestFactory) Send(send *FactorySend) (*FactorySend, error) {

	projectId, err := m.Project()
	if err != nil {
		return nil, err
	}

	queueId, err := m.Queue()
	if err != nil {
		return nil, err
	}

	domainId, err := m.Domain(projectId, "example.com")
	if err != nil {
		return nil, err
	}

	send.ProjectId = projectId
	send.DomainId = domainId
	send.QueueId = queueId

	now := time.Now()
	err = m.conn.QueryRow(`
		INSERT INTO sends (
			created_at, updated_at, send_after, project_id, domain_id, queue_id,
			queue_name, from_address, to_address, subject, body_html, body_text,
			headers, message_id, raw, status, try_count
		) VALUES (
			$1, $2, $3, $4, $5, $6,
			$7, $8, $9, $10, $11, $12,
			$13, $14, $15, $16, $17
		) RETURNING id, uuid
	`, now, now, now, projectId, send.DomainId, queueId,
		"test-queue", send.FromAddress, send.ToAddress, send.Subject,
		send.BodyHtml, send.BodyText, nil, "test-message-id", "raw-email-content",
		"queued", 0).Scan(&send.Id, &send.Uuid)

	if err != nil {
		return nil, err
	}

	return send, nil

}

func (f *TestFactory) WebhookDelivery(
	url string,
	requestBody string,
	tryCount int,
) (int, error) {
	now := time.Now()

	// First create a project
	projectId, err := f.Project()
	if err != nil {
		return 0, err
	}

	// Then create a webhook
	var webhookId int
	err = f.conn.QueryRow(`
		INSERT INTO webhooks (created_at, updated_at, project_id, url, description, events)
		VALUES ($1, $2, $3, $4, $5, $6)
		RETURNING id
	`, now, now, projectId, url, "Test webhook", `["test.event"]`).Scan(&webhookId)

	if err != nil {
		return 0, err
	}

	// Finally create the webhook delivery
	var webhookDeliveryId int
	err = f.conn.QueryRow(`
		INSERT INTO webhook_deliveries (created_at, updated_at, send_after, webhook_id, url, event, status, request_body, try_count)
		VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
		RETURNING id
	`, now, now, now, webhookId, url, "test.event", "pending", requestBody, tryCount).Scan(&webhookDeliveryId)

	if err != nil {
		return 0, err
	}

	return webhookDeliveryId, nil
}

type WebhookDeliveryEntity struct {
	ID           int
	CreatedAt    time.Time
	UpdatedAt    time.Time
	SendAfter    time.Time
	WebhookID    int64
	URL          string
	Event        string
	Status       string
	RequestBody  string
	Response     sql.NullString
	ResponseCode sql.NullInt64
	TryCount     int
}

func getWebhookDeliveryEntityById(db *sql.DB, id int) (*WebhookDeliveryEntity, error) {
	var delivery WebhookDeliveryEntity
	row := db.QueryRow(`
		SELECT 
			id, created_at, updated_at, 
			send_after, webhook_id, url, 
			event, status, request_body, response, 
			response_code, try_count
		FROM webhook_deliveries WHERE id = $1
	`, id)
	if err := row.Scan(
		&delivery.ID,
		&delivery.CreatedAt,
		&delivery.UpdatedAt,
		&delivery.SendAfter,
		&delivery.WebhookID,
		&delivery.URL,
		&delivery.Event,
		&delivery.Status,
		&delivery.RequestBody,
		&delivery.Response,
		&delivery.ResponseCode,
		&delivery.TryCount,
	); err != nil {
		return nil, err
	}
	return &delivery, nil
}
