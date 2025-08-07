package main

import (
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"time"

	"github.com/hyvor/relay/worker/bounceparse"
)

func StartHttpServer(
	ctx context.Context,
	serviceState *ServiceState,
) {

	httpServerLogger := serviceState.Logger.With("component", "http_local_server")
	httpServerLogger.Info("Starting local HTTP server on localhost:8085")

	mux := http.NewServeMux()

	mux.HandleFunc("/ping", handlePing)
	mux.HandleFunc("/ready", handleReady(serviceState)) // alias for /ping
	mux.HandleFunc("/state", handleSetState(serviceState, httpServerLogger))
	mux.HandleFunc("/debug/parse-bounce-fbl", handleParseBounceFBL())

	var handler http.Handler = mux

	server := &http.Server{
		Addr:    ":8085",
		Handler: handler,
	}

	go func() {
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			httpServerLogger.Error("HTTP server error: %v", err)
		}
	}()

	go func() {
		<-ctx.Done()

		shutdownCtx, shutdownCtxCancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer shutdownCtxCancel()

		if err := server.Shutdown(shutdownCtx); err != nil {
			httpServerLogger.Error("HTTP shutdown error: %v", err)
		}
	}()

}

func handlePing(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)
	w.Write([]byte("ok"))
}

func handleReady(serviceState *ServiceState) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if serviceState.IsSet {
			w.WriteHeader(http.StatusOK)
			w.Write([]byte("ready"))
		} else {
			w.WriteHeader(http.StatusServiceUnavailable)
			w.Write([]byte("not ready"))
		}
	}
}

func handleSetState(
	serviceState *ServiceState,
	httpServerLogger *slog.Logger,
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		httpServerLogger.Info("Updating Go state from HTTP request")

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

func handleParseBounceFBL() http.HandlerFunc {

	return func(w http.ResponseWriter, r *http.Request) {

		type ParseBounceFBLRequest struct {
			RawEmail []byte `json:"raw"`
			Type     string `json:"type"`
		}

		var request ParseBounceFBLRequest
		decoder := json.NewDecoder(r.Body)
		err := decoder.Decode(&request)

		if err != nil {
			http.Error(w, "Invalid request body", http.StatusBadRequest)
			return
		}

		var parsed interface{}

		if request.Type == "bounce" {

			parsed, err = bounceparse.ParseDsn(request.RawEmail)

			if err != nil {
				http.Error(w, "Failed to parse bounce email: "+err.Error(), http.StatusUnprocessableEntity)
				return
			}

		} else if request.Type == "fbl" {

			parsed, err = bounceparse.ParseArf(request.RawEmail)

			if err != nil {
				http.Error(w, "Failed to parse FBL email: "+err.Error(), http.StatusUnprocessableEntity)
				return
			}

		}

		writeJsonResponse(w, parsed)
	}

}

func writeJsonResponse(w http.ResponseWriter, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	if err := json.NewEncoder(w).Encode(data); err != nil {
		http.Error(w, "Failed to encode response", http.StatusInternalServerError)
	}
}
