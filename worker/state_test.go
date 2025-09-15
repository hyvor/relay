package main

import (
	"bytes"
	"context"
	"errors"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestGoState(t *testing.T) {

	goState := GoState{
		Ips: []GoStateIp{
			{Ip: "192.168.1.1", QueueName: "queue1"},
			{Ip: "192.168.1.2", QueueName: "queue2"},
		},
	}

	assert.Equal(t, []string{"192.168.1.1", "192.168.1.2"}, goState.IpAddresses())
	assert.Equal(t, "192.168.1.1:queue1,192.168.1.2:queue2", goState.IpQueueMapString())
}

func TestServiceStateInitialize(t *testing.T) {

	ctx := context.Background()

	logBuf := &bytes.Buffer{}
	serviceState := NewServiceState(ctx, slogBuffer(logBuf))

	assert.NotNil(t, serviceState)

	times := 0

	CallLocalApi = func(ctx context.Context, method string, path string, body any, result interface{}) error {
		if times > 0 {
			return nil
		} else {
			times++
			return errors.New("failed to call local api")
		}
	}

	serviceState.Initialize()
	time.Sleep(3 * time.Second)
	assert.True(t, serviceState.IsSet)

}
