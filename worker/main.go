package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
)

func main() {

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	emailWorkersState := NewEmailWorkersState()
	srv := NewHttpServer(emailWorkersState)

	go func() {

		httpServer := &http.Server{
			Addr:    "localhost:8085",
			Handler: srv,
		}

		if err := httpServer.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			fmt.Fprintf(os.Stderr, "error listening and serving: %s\n", err)
		}

	}()

	<-ctx.Done()
	fmt.Println("Shutting down server...")

	emailWorkersState.StopWorkers()

	log.Println("Server stopped gracefully")

}
