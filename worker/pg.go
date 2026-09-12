package main

import (
	"context"
	"database/sql"
	"fmt"
	"log/slog"
	"net"
	"net/url"
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

	databaseUrl := os.Getenv("DATABASE_URL")
	u, err := url.Parse(databaseUrl)

	if err != nil {
		panic("Unable to parse DATABASE_URL: " + err.Error())
	}

	host, port, _ := net.SplitHostPort(u.Host)
	password, _ := u.User.Password()

	path := u.Path
	if path == "" {
		path = "/"
	}
	dbName := path[1:]

	return &DBConfig{
		Host:     host,
		Port:     port,
		User:     u.User.Username(),
		Password: password,
		DBName:   dbName,
		SSLMode:  "disable",
	}
}

func (cfg *DBConfig) DSN() string {
	return fmt.Sprintf(
		"host=%s port=%s user=%s password=%s dbname=%s sslmode=%s connect_timeout=5",
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
