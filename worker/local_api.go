package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
)

// calls Symfony local API

var ErrUnexpectedStatusCode = errors.New("unexpected status code")

func localApiUrl(endpoint string) string {
	var envValue = os.Getenv("GO_SYMFONY_URL")
	var baseUrl string

	if envValue != "" {
		baseUrl = envValue
	} else {
		baseUrl = "http://localhost:80"
	}

	return baseUrl + "/api/local" + endpoint
}

func handleCallLocalApi(
	ctx context.Context,
	method string,
	endpoint string,
	body interface{},
	responseJsonObject interface{},
) error {

	url := localApiUrl(endpoint)

	var bodyReader io.Reader
	if body != nil {
		jsonBody, err := json.Marshal(body)
		if err != nil {
			return fmt.Errorf("failed to marshal body: %w", err)
		}
		bodyReader = bytes.NewReader(jsonBody)
	} else {
		bodyReader = nil
	}

	req, err := http.NewRequestWithContext(ctx, method, url, bodyReader)

	if err != nil {
		return err
	}

	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{}
	resp, err := client.Do(req)

	if err != nil {
		return err
	}

	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {

		bodyFirst200Bytes, _ := io.ReadAll(io.LimitReader(resp.Body, 200))

		return fmt.Errorf("%w: %s %s %d %s",
			ErrUnexpectedStatusCode,
			method,
			url,
			resp.StatusCode,
			bodyFirst200Bytes,
		)
	}

	if responseJsonObject != nil {
		decoder := json.NewDecoder(resp.Body)
		if err := decoder.Decode(responseJsonObject); err != nil {
			return err
		}
	}

	return nil

}

var CallLocalApi = handleCallLocalApi
