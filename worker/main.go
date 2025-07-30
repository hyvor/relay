package main

import (
	"context"
	"os/signal"
	"syscall"

	"github.com/joho/godotenv"
)

func main() {

	godotenv.Load()

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	// serviceState holds the state of the services (ex: email workers, etc.)
	serviceState := NewServiceState(ctx)

	// starting the local HTTP server so that symfony can call it to update the state if config changes
	StartHttpServer(ctx, serviceState)

	// tries to call /state symfony endpoint and get the state of the Go backend
	// and initialize the serviceState
	serviceState.Initialize()

	<-ctx.Done()

	serviceState.Logger.Info("Received shutdown signal, stopping services...")
}
