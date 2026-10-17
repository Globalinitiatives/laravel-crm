---
name: pest-testing
description: Use when writing or updating UpDesk CRM tests (unit or feature), debugging test failures, using datasets, mocks or custom expectations, writing architecture tests, or when the user mentions test, spec, TDD, expects, assertion, or coverage. Uses the Pest PHP framework (v3) with Laravel 12.
license: MIT
metadata:
  author: webkul
---

# Pest Testing in UpDesk CRM

## When to Apply

Activate this skill when:
- Creating or updating tests (unit or feature)
- Debugging test failures
- Using datasets, mocks, or custom expectations
- Writing architecture or convention tests
- The user mentions test, spec, TDD, expects, assertion, coverage, or verifying behavior

## UpDesk Testing Structure

### Test Locations

UpDesk tests are stored in the root `tests/` directory:

```
/tests
├── Feature/
├── Unit/
├── Pest.php
├── TestCase.php
└── CreatesApplication.php
```

### Available Test Suites

Defined in `phpunit.xml`:

| Test Suite | Location | Command |
|------------|----------|---------|
| Unit | `tests/Unit` | `php artisan test --testsuite="Unit"` |
| Feature | `tests/Feature` | `php artisan test --testsuite="Feature"` |

## Pest.php Configuration

`tests/Pest.php` binds the base test case for feature tests:

```php
uses(\Tests\TestCase::class)->in('Feature');
```

It also defines helper functions you can reuse, such as:
- `getDefaultAdmin()`
- `actingAsSanctumAuthenticatedAdmin()`
- `getFirstName($fullName)`

## Running Tests

### Run All Tests

```bash
php artisan test --compact
```

### Run a Specific Test Suite

```bash
php artisan test --testsuite="Feature"
php artisan test --testsuite="Unit"
```

### Run a Specific Test File

```bash
php artisan test --compact tests/Feature/ExampleTest.php
```

### Run with Filter

```bash
php artisan test --compact --filter=testName
```

## Creating New Tests

### Create Feature Test

```bash
php artisan make:test --pest ExampleFeatureTest
```

### Create Unit Test

```bash
php artisan make:test --pest --unit ExampleUnitTest
```

## Basic Test Structure

```php
it('passes basic assertion', function () {
    expect(true)->toBeTrue();
});

it('returns successful response', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});
```

## Assertions

Prefer specific response helpers:

| Use | Instead of |
|-----|------------|
| `assertSuccessful()` | `assertStatus(200)` |
| `assertNotFound()` | `assertStatus(404)` |
| `assertForbidden()` | `assertStatus(403)` |

## Datasets

```php
it('has valid emails', function (string $email) {
    expect($email)->toContain('@');
})->with([
    'james' => 'james@updesk.com',
    'john'  => 'john@updesk.com',
]);
```

## Common Pitfalls

- Forgetting to use `assertSuccessful()` and other specific helpers
- Skipping `--pest` when creating tests
- Ignoring helper utilities already available in `tests/Pest.php`

## Testing Best Practices

- Cover happy paths, failure paths, and edge cases
- Keep tests isolated and focused
- Use factories where available
- Follow existing test patterns in the repository
