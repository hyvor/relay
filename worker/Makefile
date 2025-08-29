
test:
	go test ./...

coverage:
	@echo "Running tests, generating coverage report...\n"
	go test ./... -coverprofile=coverage.out
	go tool cover -html=coverage.out -o=coverage.html
	@echo "\nCoverage report generated at coverage.html"

coverage-out:
	@echo "Generating coverage report from existing coverage.out...\n"
	go tool cover -html=coverage.out -o=coverage.html