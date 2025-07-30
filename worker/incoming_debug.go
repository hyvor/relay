package main

type DebugIncomingType string

const (
	DebugIncomingTypeBounce DebugIncomingType = "bounce"
	DebugIncomingTypeFbl    DebugIncomingType = "fbl"
)

type DebugIncomingStatus string

const (
	DebugIncomingStatusFailed  DebugIncomingStatus = "failed"
	DebugIncomingStatusSuccess DebugIncomingStatus = "success"
)

func createDebugRecord(
	debugType DebugIncomingType,
	status DebugIncomingStatus,
	rawEmail []byte,
	mailFrom string,
	rcptTo string,
	parsedData interface{},
	errorMessage string,
) {

	//

}
