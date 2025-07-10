package main

import (
	"context"
	"database/sql"
	"fmt"
	"log/slog"
	"os"
	"time"

	_ "github.com/lib/pq"
)

type DBConfig struct {
	Host     string
	Port     string
	User     string
	Password string
	DBName   string
	SSLMode  string
}

func LoadDBConfig() *DBConfig {
	return &DBConfig{
		Host:     os.Getenv("DB_HOST"),
		Port:     os.Getenv("DB_PORT"),
		User:     os.Getenv("DB_USER"),
		Password: os.Getenv("DB_PASSWORD"),
		DBName:   os.Getenv("DB_NAME"),
		SSLMode:  os.Getenv("DB_SSLMODE"),
	}
}

func (cfg *DBConfig) DSN() string {
	return fmt.Sprintf(
		"host=%s port=%s user=%s password=%s dbname=%s sslmode=%s",
		cfg.Host, cfg.Port, cfg.User, cfg.Password, cfg.DBName, cfg.SSLMode,
	)
}

var NewDbConn = createNewDbConn
var NewRetryingDbConn = createNewRetryingDbConn

func createNewDbConn(dbConfig *DBConfig) (*sql.DB, error) {

	db, err := sql.Open("postgres", dbConfig.DSN())

	if err != nil {
		return nil, fmt.Errorf("failed to connect to database: %w", err)
	}

	err = db.Ping()
	if err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	return db, nil

}

func createNewRetryingDbConn(
	ctx context.Context,
	dbConfig *DBConfig,
	logger *slog.Logger,
) (*sql.DB, error) {

	var db *sql.DB
	var err error

	backoff := 100 * time.Millisecond
	maxBackoff := 10 * time.Second

	for {
		db, err = NewDbConn(dbConfig)

		if err == nil {
			return db, nil
		}

		logger.Error(
			"Failed to connect to database, retrying",
			"error", err,
			"backoff", backoff,
		)

		select {
		case <-ctx.Done():
			return nil, ctx.Err()
		case <-time.After(backoff):
			backoff *= 2
			if backoff > maxBackoff {
				backoff = maxBackoff
			}

		}
	}

}
