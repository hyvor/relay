package main

import "database/sql"

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
	// execute query and return project ID
}

func (f *TestFactory) WebhookDelivery(url string, requestBody string) error {
	// insert a new webhook + webhook delivery into the database
}
