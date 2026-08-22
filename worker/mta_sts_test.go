package main

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func withMtaStsStubs(
	t *testing.T,
	txt func(name string) ([]string, error),
	fetch func(ctx context.Context, domain string) (string, error),
) {
	originalLookupTxtFunc := lookupTxtFunc
	originalFetchFunc := fetchMtaStsPolicyFunc
	lookupTxtFunc = txt
	fetchMtaStsPolicyFunc = fetch
	t.Cleanup(func() {
		lookupTxtFunc = originalLookupTxtFunc
		fetchMtaStsPolicyFunc = originalFetchFunc
		cacheClearForTest()
	})
	cacheClearForTest()
}

func TestGetMtaStsEnforced_NoTxtRecord(t *testing.T) {
	withMtaStsStubs(t,
		func(name string) ([]string, error) { return nil, errors.New("not found") },
		func(ctx context.Context, domain string) (string, error) {
			t.Fatal("should not fetch the HTTPS policy when there's no TXT record")
			return "", nil
		},
	)

	enforced, err := getMtaStsEnforced(context.Background(), nil, "example.com")
	assert.NoError(t, err)
	assert.False(t, enforced)
}

func TestGetMtaStsEnforced_ModeEnforce(t *testing.T) {
	withMtaStsStubs(t,
		func(name string) ([]string, error) { return []string{"v=STSv1; id=123"}, nil },
		func(ctx context.Context, domain string) (string, error) {
			return "version: STSv1\nmode: enforce\nmx: mail.example.com\nmax_age: 604800\n", nil
		},
	)

	enforced, err := getMtaStsEnforced(context.Background(), nil, "example.com")
	assert.NoError(t, err)
	assert.True(t, enforced)

	entry, ok := cacheMemory.Get("mta_sts:example.com")
	assert.True(t, ok)
	assert.WithinDuration(t, time.Now().Add(604800*time.Second), entry.ExpiresAt, 10*time.Second)
}

func TestGetMtaStsEnforced_ModeTesting(t *testing.T) {
	withMtaStsStubs(t,
		func(name string) ([]string, error) { return []string{"v=STSv1; id=123"}, nil },
		func(ctx context.Context, domain string) (string, error) {
			return "version: STSv1\nmode: testing\nmax_age: 604800\n", nil
		},
	)

	enforced, err := getMtaStsEnforced(context.Background(), nil, "example.com")
	assert.NoError(t, err)
	assert.False(t, enforced)
}

func TestGetMtaStsEnforced_ModeNone(t *testing.T) {
	withMtaStsStubs(t,
		func(name string) ([]string, error) { return []string{"v=STSv1; id=123"}, nil },
		func(ctx context.Context, domain string) (string, error) {
			return "version: STSv1\nmode: none\nmax_age: 604800\n", nil
		},
	)

	enforced, err := getMtaStsEnforced(context.Background(), nil, "example.com")
	assert.NoError(t, err)
	assert.False(t, enforced)
}

func TestGetMtaStsEnforced_FetchError(t *testing.T) {
	withMtaStsStubs(t,
		func(name string) ([]string, error) { return []string{"v=STSv1; id=123"}, nil },
		func(ctx context.Context, domain string) (string, error) {
			return "", errors.New("connection refused")
		},
	)

	enforced, err := getMtaStsEnforced(context.Background(), nil, "example.com")
	assert.NoError(t, err)
	assert.False(t, enforced)
}

func TestGetMtaStsEnforced_MalformedBody(t *testing.T) {
	withMtaStsStubs(t,
		func(name string) ([]string, error) { return []string{"v=STSv1; id=123"}, nil },
		func(ctx context.Context, domain string) (string, error) {
			return "this is not a valid policy file at all", nil
		},
	)

	enforced, err := getMtaStsEnforced(context.Background(), nil, "example.com")
	assert.NoError(t, err)
	assert.False(t, enforced)
}

func TestGetMtaStsEnforced_MissingMaxAge_FallsBackToDefaultTtl(t *testing.T) {
	withMtaStsStubs(t,
		func(name string) ([]string, error) { return []string{"v=STSv1; id=123"}, nil },
		func(ctx context.Context, domain string) (string, error) {
			return "version: STSv1\nmode: enforce\n", nil
		},
	)

	enforced, err := getMtaStsEnforced(context.Background(), nil, "example.com")
	assert.NoError(t, err)
	assert.True(t, enforced)

	entry, ok := cacheMemory.Get("mta_sts:example.com")
	assert.True(t, ok)
	assert.WithinDuration(t, time.Now().Add(mtaStsDefaultTtl), entry.ExpiresAt, 10*time.Second)
}

func TestGetMtaStsEnforced_CacheHit_SkipsLookup(t *testing.T) {
	withMtaStsStubs(t,
		func(name string) ([]string, error) {
			t.Fatal("should not perform a TXT lookup on a cache hit")
			return nil, nil
		},
		func(ctx context.Context, domain string) (string, error) {
			t.Fatal("should not fetch the HTTPS policy on a cache hit")
			return "", nil
		},
	)

	cacheSet(context.Background(), nil, "mta_sts:example.com", MtaStsCacheValue{Enforced: true}, time.Hour)

	enforced, err := getMtaStsEnforced(context.Background(), nil, "example.com")
	assert.NoError(t, err)
	assert.True(t, enforced)
}

func TestParseMtaStsPolicy(t *testing.T) {
	mode, maxAge := parseMtaStsPolicy("version: STSv1\nmode: enforce\nmx: mail.example.com\nmax_age: 86400\n")
	assert.Equal(t, "enforce", mode)
	assert.Equal(t, 86400, maxAge)
}
