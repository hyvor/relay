package main

import (
	"context"
	"fmt"
)

type SendContentStore interface {
	GetRaw(ctx context.Context, uuid string) (string, error)
}

var NewSendContentStore = newSendContentStore

func newSendContentStore() (SendContentStore, error) {
	return &localApiSendContentStore{}, nil
}

type localApiSendContentStore struct{}

type sendRawContentResponse struct {
	Raw string `json:"raw"`
}

func (s *localApiSendContentStore) GetRaw(ctx context.Context, uuid string) (string, error) {
	var resp sendRawContentResponse
	err := CallLocalApi(
		ctx,
		"GET",
		"/sends/"+uuid+"/raw",
		nil,
		&resp,
	)
	if err != nil {
		return "", fmt.Errorf("failed to fetch send content via local API for %s: %w", uuid, err)
	}

	return resp.Raw, nil
}
