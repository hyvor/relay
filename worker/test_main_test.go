package main

import (
	"context"
	"os"
	"testing"
)

func TestMain(m *testing.M) {
	original := lookupTLSAFunc
	lookupTLSAFunc = func(context.Context, *SharedCache, string) (TLSAResult, error) {
		return TLSAResult{State: TLSAStateSecureAbsent}, nil
	}
	code := m.Run()
	lookupTLSAFunc = original
	os.Exit(code)
}
