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

	"github.com/jackc/pgx/v5/pgxpool"
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

	return &DBConfig{
		Host:     host,
		Port:     port,
		User:     u.User.Username(),
		Password: password,
		DBName:   u.Path[1:],
		SSLMode:  "disable",
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

func createNewPgPool(
	ctx context.Context,
	config *DBConfig,
	minConns int32,
	maxConns int32,
) (*pgxpool.Pool, error) {

	pgConfig, err := pgxpool.ParseConfig(config.DSN())

	if err != nil {
		return nil, fmt.Errorf("failed to parse database config: %w", err)
	}

	pgConfig.MaxConns = maxConns
	pgConfig.MinConns = minConns

	pgpool, err := pgxpool.NewWithConfig(ctx, pgConfig)
	if err != nil {
		return nil, fmt.Errorf("failed to create pgpool: %w", err)
	}

	if err := pgpool.Ping(ctx); err != nil {
		pgpool.Close()
		return nil, fmt.Errorf("failed to ping pgpool: %w", err)
	}

	return pgpool, nil
}

func createNewRetryingPgPool(
	ctx context.Context,
	config *DBConfig,
	minConns int32,
	maxConns int32,
) (*pgxpool.Pool, error) {

	var pgpool *pgxpool.Pool
	var err error

	backoff := 100 * time.Millisecond
	maxBackoff := 10 * time.Second

	for {
		pgpool, err = createNewPgPool(ctx, config, minConns, maxConns)

		if err == nil {
			return pgpool, nil
		}

		slog.Error(
			"Failed to connect to pgpool, retrying",
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
