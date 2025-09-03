
test:
	go test ./... -coverprofile=coverage.out

coverage:
	@echo "Running tests, generating coverage report...\n"
	go test ./... -coverprofile=coverage.out -v
	go tool cover -html=coverage.out -o=coverage.html
	@echo "\nCoverage report generated at coverage.html"

coverage-out:
	@echo "Generating coverage report from existing coverage.out...\n"
	go tool cover -html=coverage.out -o=coverage.html