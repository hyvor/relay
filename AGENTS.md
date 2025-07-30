### Backend in PHP

- Use PSR-12 coding standards
- Each API has
  - Controllers
  - Input (DTO objects with assertions)
  - Objects (DTO objects with just output data)
    - dates are UNIX timestamps in objects
    - objects are quite similar to Entities, but not all data is required.
  - Controllers only do input validation, calling services, and returning objects.
  - Services handle business logic.
