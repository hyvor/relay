package main

import (
	"context"
	"log/slog"
	"os"

	"go.opentelemetry.io/otel"
	"go.opentelemetry.io/otel/attribute"
	"go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracehttp"
	"go.opentelemetry.io/otel/propagation"
	"go.opentelemetry.io/otel/sdk/resource"
	sdktrace "go.opentelemetry.io/otel/sdk/trace"
	semconv "go.opentelemetry.io/otel/semconv/v1.26.0"
	"go.opentelemetry.io/otel/trace"
)

const tracerName = "github.com/hyvor/relay/worker"

// Tracer returns the package tracer. Safe to call before InitTracing —
// the global provider yields a no-op tracer until configured.
func Tracer() trace.Tracer {
	return otel.Tracer(tracerName)
}

// InitTracing sets up the global OpenTelemetry tracer provider exporting via
// OTLP/HTTP. It is a no-op when OTEL_EXPORTER_OTLP_ENDPOINT is unset, so the
// worker keeps running without an OTel collector configured.
//
// Returns a shutdown function the caller MUST invoke at process exit.
func InitTracing(ctx context.Context, logger *slog.Logger) (func(context.Context) error, error) {

	endpoint := os.Getenv("OTEL_EXPORTER_OTLP_ENDPOINT")
	if endpoint == "" {
		logger.Info("OTEL disabled: OTEL_EXPORTER_OTLP_ENDPOINT not set")
		return func(context.Context) error { return nil }, nil
	}

	exporter, err := otlptracehttp.New(ctx)
	if err != nil {
		return nil, err
	}

	res, err := resource.Merge(
		resource.Default(),
		resource.NewWithAttributes(
			semconv.SchemaURL,
			semconv.ServiceName(envOrDefault("OTEL_SERVICE_NAME", "hyvor-relay-worker")),
			attribute.String("service.component", "worker"),
		),
	)
	if err != nil {
		return nil, err
	}

	tp := sdktrace.NewTracerProvider(
		sdktrace.WithBatcher(exporter),
		sdktrace.WithResource(res),
	)

	otel.SetTracerProvider(tp)
	otel.SetTextMapPropagator(propagation.NewCompositeTextMapPropagator(
		propagation.TraceContext{},
		propagation.Baggage{},
	))

	logger.Info("OTEL tracing initialized", "endpoint", endpoint)
	return tp.Shutdown, nil
}

func envOrDefault(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}
