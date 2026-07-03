package main

import (
	"bytes"
	"context"
	"fmt"
	"io"
	"os"

	"github.com/aws/aws-sdk-go-v2/aws"
	"github.com/aws/aws-sdk-go-v2/credentials"
	"github.com/aws/aws-sdk-go-v2/service/s3"
)

// SendContentStore fetches the raw MIME content of sends from object storage.
type SendContentStore struct {
	client *s3.Client
	bucket string
}

var NewSendContentStore = newSendContentStore

func newSendContentStore() (*SendContentStore, error) {
	endpoint := os.Getenv("S3_ENDPOINT")
	region := os.Getenv("S3_REGION")
	accessKey := os.Getenv("S3_KEY")
	secretKey := os.Getenv("S3_SECRET")
	bucket := os.Getenv("S3_BUCKET")

	if bucket == "" {
		return nil, fmt.Errorf("S3_BUCKET is not set")
	}

	client := s3.New(s3.Options{
		Region:       region,
		BaseEndpoint: aws.String(endpoint),
		Credentials:  credentials.NewStaticCredentialsProvider(accessKey, secretKey, ""),
		UsePathStyle: true,
	})

	return &SendContentStore{
		client: client,
		bucket: bucket,
	}, nil
}

// Content is stored by the Symfony backend as 'sends/{uuid}.eml'.
func (s *SendContentStore) GetRaw(ctx context.Context, uuid string) (string, error) {
	key := "sends/" + uuid + ".eml"

	out, err := s.client.GetObject(ctx, &s3.GetObjectInput{
		Bucket: aws.String(s.bucket),
		Key:    aws.String(key),
	})
	if err != nil {
		return "", fmt.Errorf("failed to get object %s: %w", key, err)
	}
	defer out.Body.Close()

	var buf bytes.Buffer
	if _, err := io.Copy(&buf, out.Body); err != nil {
		return "", fmt.Errorf("failed to read object %s: %w", key, err)
	}

	return buf.String(), nil
}
