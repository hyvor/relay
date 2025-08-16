package main

import "strings"

// It assumes the email is well-formed and contains an '@' character.
// otherwise, it returns an empty string.
func getDomainFromEmail(email string) string {
	parts := strings.Split(email, "@")
	if len(parts) != 2 {
		return ""
	}
	return parts[1]
}
