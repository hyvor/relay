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
		DELETE FROM servers;
		DELETE FROM ip_addresses;
		DELETE FROM projects;
		DELETE FROM domains;
		DELETE FROM queues;
		DELETE FROM sends;
		DELETE FROM send_recipients;
		DELETE FROM webhooks;
		DELETE FROM webhook_deliveries;
		DELETE FROM send_attempts;
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

func (f *TestFactory) Server() (int, error) {

	now := time.Now()
	hostname := fmt.Sprintf("Test Server %d", rand.Intn(1000000))

	var serverId int
	err := f.conn.QueryRow(`
		INSERT INTO servers (created_at, updated_at, hostname)
		VALUES ($1, $2, $3)
		RETURNING id
	`, now, now, hostname).Scan(&serverId)

	if err != nil {
		return 0, err
	}

	return serverId, nil

}

func (f *TestFactory) IpAddress() (int, error) {

	serverId, err := f.Server()

	if err != nil {
		return 0, err
	}

	var ipId int
	err = f.conn.QueryRow(`
		INSERT INTO ip_addresses (server_id, ip_address, created_at, updated_at)
		VALUES ($1, $2, $3, $4)
		RETURNING id
	`, serverId, "192.168.1.1", time.Now(), time.Now()).Scan(&ipId)

	if err != nil {
		return 0, err
	}

	return ipId, nil

}

func (f *TestFactory) Project() (int, error) {
	now := time.Now()
	randomUserId := rand.Intn(1000000) + 1
	randomName := fmt.Sprintf("Test Project %d", rand.Intn(1000000))

	var projectId int
	err := f.conn.QueryRow(`
		INSERT INTO projects (created_at, updated_at, user_id, name, send_type)
		VALUES ($1, $2, $3, $4, 'transactional')
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
	Queued      bool
	SendAfter   time.Time
	FromAddress string
	ToAddress   string
	Subject     string
	BodyHtml    string
	BodyText    string
}

type FactorySendRecipient struct {
	Id       int
	Type     string // "to", "cc", "bcc"
	Status   string // "queued", "accepted", "retrying", "bounced", "complained", "failed"
	Address  string
	Name     string
	TryCount int
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
			queue_name, from_address, subject, body_html, body_text,
			headers, message_id, raw,
			size_bytes, queued
		) VALUES (
			$1, $2, $3, $4, $5, $6,
			$7, $8, $9, $10, $11,
			$12, $13, $14,
			0, $15
		) RETURNING id, uuid
	`, now, now, send.SendAfter, projectId, send.DomainId, queueId,
		"test-queue", send.FromAddress, send.Subject,
		send.BodyHtml, send.BodyText, nil, "test-message-id", "raw-email-content",
		send.Queued,
	).Scan(&send.Id, &send.Uuid)

	if err != nil {
		return nil, err
	}

	return send, nil

}

func (f *TestFactory) GetSendById(id int) (*FactorySend, error) {

	var send FactorySend
	row := f.conn.QueryRow(`
		SELECT 
			id, uuid, project_id, domain_id, queue_id, queued, send_after,
			from_address, subject, body_html, body_text
		FROM sends WHERE id = $1
	`, id)

	err := row.Scan(
		&send.Id,
		&send.Uuid,
		&send.ProjectId,
		&send.DomainId,
		&send.QueueId,
		&send.Queued,
		&send.SendAfter,
		&send.FromAddress,
		&send.Subject,
		&send.BodyHtml,
		&send.BodyText,
	)

	if err != nil {
		return nil, err
	}

	return &send, nil

}

type FactorySendAttempt struct {
	Id                int
	SendId            int
	IpAddressId       int
	Status            string
	TryCount          int
	Domain            string
	ResolvedMx        string
	RespondedMx       sql.NullString
	SmtpConversations string

	Recipients []*FactorySendAttemptRecipientResult
}

type FactorySendAttemptRecipientResult struct {
	Id int
	RecipientId int
	Status string
	SmtpCode int
	SmtpEnhancedCode string
	SmtpMessage string
}

func (f *TestFactory) GetSendAttemptById(id int) (*FactorySendAttempt, error) {

	var attempt FactorySendAttempt
	row := f.conn.QueryRow(`
		SELECT 
			id, send_id, ip_address_id, status, try_count, domain,
			resolved_mx_hosts, responded_mx_host, smtp_conversations
		FROM send_attempts WHERE id = $1
	`, id)

	err := row.Scan(
		&attempt.Id,
		&attempt.SendId,
		&attempt.IpAddressId,
		&attempt.Status,
		&attempt.TryCount,
		&attempt.Domain,
		&attempt.ResolvedMx,
		&attempt.RespondedMx,
		&attempt.SmtpConversations,
	)

	if err != nil {
		return nil, err
	}

	var recipientResults []*FactorySendAttemptRecipientResult
	rows, err := f.conn.Query(`
		SELECT 
			id, send_recipient_id, recipient_status, smtp_code, smtp_enhanced_code, smtp_message
		FROM send_attempt_recipients WHERE send_attempt_id = $1
	`, id)

	if err != nil {
		return nil, err
	}
	defer rows.Close()

	for rows.Next() {
		var result FactorySendAttemptRecipientResult
		if err := rows.Scan(
			&result.Id,
			&result.RecipientId,
			&result.Status,
			&result.SmtpCode,
			&result.SmtpEnhancedCode,
			&result.SmtpMessage,
		); err != nil {
			return nil, err
		}
		recipientResults = append(recipientResults, &result)
	}

	attempt.Recipients = recipientResults

	return &attempt, nil

}

func (f *TestFactory) SendRecipient(send *FactorySend, recipients *FactorySendRecipient) (int, error) {

	var recipientId int
	err := f.conn.QueryRow(`
		INSERT INTO send_recipients (
			send_id, type, status, address, name, try_count
		) VALUES (
			$1, $2, $3, $4, $5, $6
		) RETURNING id
	`, send.Id, recipients.Type, recipients.Status,
		recipients.Address, recipients.Name, recipients.TryCount).Scan(&recipientId)

	if err != nil {
		return 0, err
	}

	return recipientId, nil

}

func (f *TestFactory) GetSendRecipientById(id int) (*FactorySendRecipient, error) {

	var recipient FactorySendRecipient
	row := f.conn.QueryRow(`
		SELECT 
			id, type, status, address, name, try_count
		FROM send_recipients WHERE id = $1
	`, id)

	err := row.Scan(
		&recipient.Id,
		&recipient.Type,
		&recipient.Status,
		&recipient.Address,
		&recipient.Name,
		&recipient.TryCount,
	)

	if err != nil {
		return nil, err
	}

	return &recipient, nil

}

func (f *TestFactory) WebhookDelivery(
	url string,
	requestBody string,
	tryCount int,
) (int, error) {
	return f.WebhookDeliveryWithSignature(url, requestBody, tryCount, nil)
}

func (f *TestFactory) WebhookDeliveryWithSignature(
	url string,
	requestBody string,
	tryCount int,
	signature *string,
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
		INSERT INTO webhooks (created_at, updated_at, project_id, url, description, events, secret_encrypted)
		VALUES ($1, $2, $3, $4, $5, $6, $7)
		RETURNING id
	`, now, now, projectId, url, "Test webhook", `["test.event"]`, "test-secret-encrypted").Scan(&webhookId)

	if err != nil {
		return 0, err
	}

	// Finally create the webhook delivery
	var webhookDeliveryId int
	err = f.conn.QueryRow(`
		INSERT INTO webhook_deliveries (created_at, updated_at, send_after, webhook_id, url, event, status, request_body, try_count, signature)
		VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
		RETURNING id
	`, now, now, now, webhookId, url, "test.event", "pending", requestBody, tryCount, signature).Scan(&webhookDeliveryId)

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
	Signature    sql.NullString
}

func getWebhookDeliveryEntityById(db *sql.DB, id int) (*WebhookDeliveryEntity, error) {
	var delivery WebhookDeliveryEntity
	row := db.QueryRow(`
		SELECT 
			id, created_at, updated_at, 
			send_after, webhook_id, url, 
			event, status, request_body, response, 
			response_code, try_count, signature
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
		&delivery.Signature,
	); err != nil {
		return nil, err
	}
	return &delivery, nil
}
