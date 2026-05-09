# ID Package Tests

Test suite for the Switon ID package, organized by test type and speed.

## Test Structure

```
tests/
└── Unit/          # Fast unit tests (no external dependencies)
```

## Running Tests

Run from the package root:

```bash
# Run all tests
vendor/bin/phpunit --configuration tests/phpunit.xml.dist

# Run specific test suite
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testsuite=unit

# Run specific test method
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --filter testMethodName

# Run with PHPUnit options
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --coverage-html tests/coverage
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --stop-on-failure
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --verbose
```

**Note**: Install dev dependencies first so `vendor/bin/phpunit` is available.

## Test Quality Standards

### Code Quality

- **Strict Types**: All test code uses `declare(strict_types=1)`
- **No Warnings**: Clean execution with zero warnings
- **Descriptive Names**: Test methods clearly describe what they test
- **Proper Assertions**: Use specific assertions with messages

### Test Structure

- **Arrange-Act-Assert**: Clear test phases with explicit comments
- **One Behavior Per Test**: Focus on single behavior
- **Descriptive Messages**: Helpful failure messages
- **Proper Cleanup**: Tests clean up after themselves

## Coverage

Current suite status:

- 86 tests
- 1 skipped when Redis-backed Snowflake runtime is unavailable
- assertions vary slightly with PHPUnit and environment
- Covers all 8 injectable generators plus `ShortUuid` and `IdCommand`

## Troubleshooting

For debugging failing tests:

```bash
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --filter testMethodName
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --verbose
```

## Contributing

When adding new tests:

- Use `Unit/` for isolated logic tests
- Follow naming convention: `Id[Component]Test.php`
- Use appropriate namespace: `Tests\Unit`
- Test boundary conditions and error cases

---

*See main README.md for package overview and documentation.*
