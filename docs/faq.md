# ID FAQ {#id-faq}

Short answers for setup, configuration, and common runtime questions.

> **Prerequisites:** [ID Package](README.md)

## Getting Started {#getting-started}

### How do I generate an ID? {#generate-id}

Inject `Switon\Id\IdGeneratorInterface` and let the property name choose the generator. See [Quick Start](README.md#quick-start).

### How do I generate several IDs at once? {#generate-batch}

Call `nextN($count)` on the same interface when one request or job needs a batch instead of one value at a time.

### Which generator should I start with? {#which-generator}

Use `uuid7` for most sortable string IDs, `snowflake` for integer IDs with Redis, and `nanoId` for short public tokens. See [Choosing A Generator](README.md#choosing-a-generator).

### How do I use more than one generator in one service? {#multiple-generators}

Inject one property per generator name:

```php
use Switon\Core\Attribute\Autowired;
use Switon\Id\IdGeneratorInterface;

class AssetService
{
    #[Autowired] protected IdGeneratorInterface $uuid7;
    #[Autowired] protected IdGeneratorInterface $nanoId;
}
```

This keeps the type hint the same while the property name selects the concrete generator. See [Named Injection](README.md#named-injection).

### How do I get a shorter UUID string? {#short-uuid}

Use `Switon\Id\ShortUuid` static methods such as `ShortUuid::generate()`, `ShortUuid::encode($uuid)`, and `ShortUuid::decode($short)`. It is not injectable and it needs `ext-gmp`.

### Why is `RequestIdFilter` using UUID v4? {#request-id-filter}

The HTTP request ID filter uses the default `Switon\Id\IdGeneratorInterface` binding, which resolves to `uuid4`.

## Configuration {#configuration}

### Where do I configure generators? {#configure}

Use `switon.yml` with service ids such as `Switon\Id\IdGeneratorInterface#snowflake`, `Switon\Id\IdGeneratorInterface#nanoId`, or `Switon\Id\IdGeneratorInterface#cuid2`. See [Configuration](README.md#configuration).

### How do I inspect the current option surface? {#discover-options}

Query the concrete generator you are tuning, for example `Switon\Id\Snowflake`, `Switon\Id\NanoId`, or `Switon\Id\Cuid2`.

```bash
bash bin/console config:options 'Switon\Id\Snowflake'
```

The interface query `bash bin/console config:options 'Switon\Id\IdGeneratorInterface'` shows only the unnamed default (`uuid4`).

## Troubleshooting {#troubleshooting}

### Why is Snowflake failing at runtime? {#snowflake-fails}

Most failures come from missing or broken Redis backends. Confirm that `redis` resolves to exactly 1 or 2 `Switon\Redis\ClientInterface` instances.

If the backend cannot allocate a sequence, the package raises `Switon\Id\Exception\SequenceBackendUnavailableException`.

### Why are database inserts slower with UUID v4 or `nanoId`? {#random-pks}

Random IDs reduce index locality. Prefer `uuid7` or `snowflake` for hot primary keys.

### Why do `Ksuid` or `ShortUuid` fail even though the code looks correct? {#gmp-runtime}

Both `Ksuid` and `ShortUuid` require `ext-gmp` for base conversion. Install it, then retry.

### Which generator names map to ORM strategy names? {#orm-strategy}

`Switon\Orm\IdGenerator` rewrites two strategy aliases: `uuid` -> `uuid4`, `uuid-v7` -> `uuid7`. Other strategy names (`snowflake`, `ulid`, `nanoId`, `ksuid`, `mongoId`, `cuid2`) use the slot name directly.

### How do I configure Snowflake Redis backends? {#snowflake-redis}

Use the `redis` option as one or two `Switon\Redis\ClientInterface` services. Snowflake is the only built-in generator that needs Redis coordination.
