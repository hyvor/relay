package main

import (
	"context"
	"fmt"
	"io"
	"net/http"
)

type SendContentStore interface {
	GetRaw(ctx context.Context, uuid string) (string, error)
}

var NewSendContentStore = newSendContentStore

func newSendContentStore() (SendContentStore, error) {
	return &localApiSendContentStore{}, nil
}

type localApiSendContentStore struct{}

func (s *localApiSendContentStore) GetRaw(ctx context.Context, uuid string) (string, error) {
	url := getSymfonyUrl("/api/local/sends/" + uuid + "/raw")

	req, err := http.NewRequestWithContext(ctx, "GET", url, nil)
	if err != nil {
		return "", fmt.Errorf("failed to create request for send content %s: %w", uuid, err)
	}

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		return "", fmt.Errorf("failed to fetch send content for %s: %w", uuid, err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		bodyFirst200Bytes, _ := io.ReadAll(io.LimitReader(resp.Body, 200))
		return "", fmt.Errorf("failed to fetch send content for %s (status %d): %s", uuid, resp.StatusCode, bodyFirst200Bytes)
	}

	content, err := io.ReadAll(resp.Body)
	if err != nil {
		return "", fmt.Errorf("failed to read send content stream for %s: %w", uuid, err)
	}

	return string(content), nil
}
