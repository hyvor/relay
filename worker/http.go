package main

import "net/http"

func NewHttpServer(
	emailWorkersState *EmailWorkersState,
) http.Handler {

	mux := http.NewServeMux()
	var handler http.Handler = mux
	addRoutes(
		mux,
		emailWorkersState,
	)
	return handler

}

func addRoutes(
	mux *http.ServeMux,
	emailWorkersState *EmailWorkersState,
) {
	mux.HandleFunc("/health", healthCheckHandler)
	mux.HandleFunc("/email/start", startEmailWorkersHandler(emailWorkersState))
}

func healthCheckHandler(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)
	w.Write([]byte("OK"))
}

func startEmailWorkersHandler(
	emailWorkersState *EmailWorkersState,
) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		workersCount := 5 // Example: you can get this from query params or request body
		emailWorkersState.Start(workersCount)
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("Email workers started"))
	}
}
