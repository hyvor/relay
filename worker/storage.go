package main

import (
	"bytes"
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"

	"github.com/aws/aws-sdk-go-v2/aws"
	"github.com/aws/aws-sdk-go-v2/credentials"
	"github.com/aws/aws-sdk-go-v2/service/s3"
)

const localMediaRoot = "/app/media"

type SendContentStore interface {
	GetRaw(ctx context.Context, uuid string) (string, error)
}

var NewSendContentStore = newSendContentStore

func newSendContentStore() (SendContentStore, error) {
	filesystem := os.Getenv("FILESYSTEM")
	if filesystem == "" {
		filesystem = "file"
	}

	switch filesystem {
	case "s3":
		return newS3SendContentStore()
	case "file":
		return newLocalSendContentStore()
	default:
		return nil, fmt.Errorf("unsupported FILESYSTEM: %s", filesystem)
	}
}

type s3SendContentStore struct {
	client *s3.Client
	bucket string
}

func newS3SendContentStore() (SendContentStore, error) {
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

	return &s3SendContentStore{
		client: client,
		bucket: bucket,
	}, nil
}

func (s *s3SendContentStore) GetRaw(ctx context.Context, uuid string) (string, error) {
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

type localSendContentStore struct {
	root string
}

func newLocalSendContentStore() (SendContentStore, error) {
	return &localSendContentStore{
		root: localMediaRoot,
	}, nil
}

func (s *localSendContentStore) GetRaw(_ context.Context, uuid string) (string, error) {
	path := filepath.Join(s.root, "sends", uuid+".eml")

	content, err := os.ReadFile(path)
	if err != nil {
		return "", fmt.Errorf("failed to read file %s: %w", path, err)
	}

	return string(content), nil
}
