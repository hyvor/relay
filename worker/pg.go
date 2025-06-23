package main

import (
	"database/sql"
	"fmt"
)

func NewDbConn() (*sql.DB, error) {

	connStr := "host=hyvor-service-pgsql port=5432 user=postgres password=postgres dbname=hyvor_relay sslmode=disable"
	db, err := sql.Open("postgres", connStr)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to database: %w", err)
	}
	defer db.Close()

	err = db.Ping()
	if err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}
	fmt.Println("Connected to PostgreSQL!")

	return db, nil

}
