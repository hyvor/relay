package main

type DebugIncomingType string

const (
	DebugIncomingTypeBounce DebugIncomingType = "bounce"
	DebugIncomingTypeFBL    DebugIncomingType = "fbl"
)

type DebugIncomingStatus string

const (
	DebugIncomingStatusFailed  DebugIncomingStatus = "failed"
	DebugIncomingStatusSuccess DebugIncomingStatus = "success"
)

func createDebugRecord(
	debugType DebugIncomingType,
	status DebugIncomingStatus,
	rawEmail string,
	mailFrom string,
	rcptTo string,
	parsedData interface{},
	errorMessage string,
) {

	//

}
