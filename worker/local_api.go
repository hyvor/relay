package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
)

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

func CallLocalApi(
	ctx context.Context,
	method string,
	endpoint string,
	body io.Reader,
	responseJsonObject interface{},
) error {

	url := localApiUrl(endpoint)

	req, err := http.NewRequestWithContext(ctx, method, url, body)

	if err != nil {
		return err
	}

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
