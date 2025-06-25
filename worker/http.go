package main

import (
	"context"
	"encoding/json"
	"log"
	"net/http"
	"time"
)

func StartHttpServer(
	ctx context.Context,
	serviceState *ServiceState,
) {
	mux := http.NewServeMux()

	mux.HandleFunc("/health", handleHealth)
	mux.HandleFunc("/state", handleSetState(serviceState))

	var handler http.Handler = mux

	server := &http.Server{
		Addr:    "localhost:8085",
		Handler: handler,
	}

	serviceState.Logger.Info("Starting local HTTP server on :8085")

	go func() {
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("HTTP server error: %v", err)
		}
	}()

	go func() {
		<-ctx.Done()

		shutdownCtx, shutdownCtxCancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer shutdownCtxCancel()

		if err := server.Shutdown(shutdownCtx); err != nil {
			log.Fatalf("HTTP shutdown error: %v", err)
		}
	}()

}

func handleHealth(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)
	w.Write([]byte("OK"))
}

func handleSetState(
	serviceState *ServiceState,
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		log.Println("Setting Go state...")

		var goState GoState
		decoder := json.NewDecoder(r.Body)
		err := decoder.Decode(&goState)

		if err != nil {
			http.Error(w, "Invalid request body", http.StatusBadRequest)
			return
		}

		serviceState.Set(goState)

		writeJsonResponse(w, map[string]string{"message": "Go state updated"})
	}
}

func writeJsonResponse(w http.ResponseWriter, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	if err := json.NewEncoder(w).Encode(data); err != nil {
		http.Error(w, "Failed to encode response", http.StatusInternalServerError)
	}
}
