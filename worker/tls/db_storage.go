package tls

import (
	"context"
	"fmt"

	"github.com/caddyserver/certmagic"
)

// implements certmagic.Storage
type DbStorage struct{}

func (d *DbStorage) Store(ctx context.Context, key string, value []byte) error {
	fmt.Println("DBStorage: Storing key:", key)
	return nil
}

// key: certificates/acme-v02.api.letsencrypt.org-directory/domain.com/domain.com.key
func (d *DbStorage) Load(ctx context.Context, key string) ([]byte, error) {
	fmt.Println("DBStorage: Loading key:", key)
	return []byte("mocked-value"), nil
}

func (d *DbStorage) Delete(ctx context.Context, key string) error {
	fmt.Println("DBStorage: Deleting key:", key)
	return nil
}

func (d *DbStorage) Exists(ctx context.Context, key string) bool {
	fmt.Println("DBStorage: Checking existence of key:", key)
	return false
}

func (d *DbStorage) List(ctx context.Context, path string, recursive bool) ([]string, error) {
	fmt.Println("DBStorage: Listing keys at path:", path, "recursive:", recursive)
	return []string{}, nil
}

func (d *DbStorage) Stat(ctx context.Context, key string) (certmagic.KeyInfo, error) {
	fmt.Println("DBStorage: Stating key:", key)
	return certmagic.KeyInfo{}, nil
}

func (d *DbStorage) Lock(ctx context.Context, key string) error {
	fmt.Println("DBStorage: Locking key:", key)
	return nil
}

func (d *DbStorage) Unlock(ctx context.Context, key string) error {
	fmt.Println("DBStorage: Unlocking key:", key)
	return nil
}