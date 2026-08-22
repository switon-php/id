# ID Package {#id-package}

`switon/id` gives Switon apps one generator contract with named bindings for UUIDs, ULID, Snowflake, and compact public IDs.

**Key features:**

- **One contract:** inject `Switon\Id\IdGeneratorInterface` and pick the generator by property name.
- **Built-in generators:** `uuid4`, `uuid7`, `ulid`, `snowflake`, `nanoId`, `ksuid`, `mongoId`, `cuid2`.
- **Stable default:** unnamed `IdGeneratorInterface` resolves to `uuid4`.
- **Short UUID helper:** `ShortUuid` is a static helper for 22-character Base58 UUID text.
- **Explicit Snowflake runtime:** `Snowflake` is the only built-in generator that needs Redis-backed sequence allocation.

---

## Installation {#installation}

**Requirements:** PHP 8.3+, `switon/sync`. Install `switon/redis` to use `Snowflake`.

Install `ext-gmp` if you use `Ksuid` or `ShortUuid`.

```bash
composer require switon/id
```

---

## Quick Start {#quick-start}

Inject `IdGeneratorInterface` and let the property name choose the generator. For most string IDs, start with `uuid7`; for a plain default, keep the interface unnamed.

```php
namespace App\Service;

use Switon\Core\Attribute\Autowired;
use Switon\Id\IdGeneratorInterface;

class OrderService
{
    #[Autowired] protected IdGeneratorInterface $uuid7;
    #[Autowired] protected IdGeneratorInterface $idGenerator;

    public function create(): string
    {
        return $this->uuid7->next();
    }
}
```

`$uuid7` resolves to `Switon\Id\IdGeneratorInterface#uuid7`.
`$idGenerator` resolves to the unnamed default, which is `uuid4`.

If you need multiple generator types in one service, add one property per generator name.

---

## Core Concepts {#core-concepts}

### Named Injection {#named-injection}

`Switon\Id\ServiceProvider` registers named bindings under `Switon\Id\IdGeneratorInterface#name`.

```php
class UserService
{
    #[Autowired] protected IdGeneratorInterface $snowflake;
    #[Autowired] protected IdGeneratorInterface $uuid7;
    #[Autowired] protected IdGeneratorInterface $nanoId;
}
```

Built-in named slots: `snowflake`, `uuid4`, `uuid7`, `ulid`, `nanoId`, `ksuid`, `mongoId`, `cuid2`. The unnamed `IdGeneratorInterface` resolves through `#default` to `#uuid4`.

### Choosing A Generator {#choosing-a-generator}

Start with the ID form you need:

- `uuid7` for most sortable string IDs
- `snowflake` for sortable integer IDs when Redis is available
- `nanoId` for short public tokens
- `ulid` when you want readable ordered strings
- `uuid4` when you want a standard random UUID
- `ksuid` when you need 27-character time-ordered Base62 strings (needs `ext-gmp`)
- `mongoId` when you need ObjectId compatibility
- `cuid2` when you want compact random-style text
- `ShortUuid` when you already have UUID text and want a shorter reversible form

### Generator API {#generator-api}

`IdGeneratorInterface` only exposes `next()` and `nextN()`.

Generator-specific helpers stay on the concrete class or static helper.

### ShortUuid {#shortuuid}

`ShortUuid` is static, not injectable. Use it when you need shorter UUID text, not a new generator service.

---

## Configuration {#configuration}

Use `switon.yml` to tune named generator bindings. Most generators work with defaults; start there and only add config when a generator exposes useful knobs.

Common tuned options: `Snowflake` exposes `shard`, `prefix`, `prefetch`, `jitter`, and `redis`; `NanoId` and `Cuid2` expose `alphabet` and `length`.

```yaml
# switon.yml
Switon\Id\IdGeneratorInterface#snowflake:
  shard: 1
  prefetch: 200

Switon\Id\IdGeneratorInterface#nanoId:
  length: 12

Switon\Id\IdGeneratorInterface#cuid2:
  length: 28
```

---

## Option Discovery {#option-discovery}

Use the concrete class when you inspect options for a named generator:

```bash
bash bin/console config:options 'Switon\Id\Snowflake'
bash bin/console config:options 'Switon\Id\NanoId'
bash bin/console config:options 'Switon\Id\Cuid2'
```

`bash bin/console config:options 'Switon\Id\IdGeneratorInterface'` shows only the unnamed default binding.

---

## Keep in Mind {#keep-in-mind}

- Use `uuid7` for most database IDs unless you specifically need integers.
- Plan Redis before choosing `Snowflake`.
- `Ksuid` and `ShortUuid` need `ext-gmp`.
- Random IDs are fine for opaque tokens, but they are a poor fit for hot primary keys.
- `config:options` queries the concrete generator class for tuned generators, not the interface.
- ORM strategy names map differently: `uuid` -> `uuid4`, `uuid-v7` -> `uuid7`; other names match the slot.
- `Switon\Http\Filter\RequestIdFilter` uses the unnamed `IdGeneratorInterface` (so `uuid4`) for `X-Request-Id`.

---

## Next {#next}

- **To understand trade-offs and boundaries:** [Design Rationale](design.md#design-rationale)
- **To answer common setup and runtime questions:** [FAQ](faq.md#id-faq)
- **To use Snowflake with Redis-backed allocation:** [Redis Package](https://github.com/switon-php/redis/blob/main/docs/README.md)
- **To resolve named properties with DI:** [Autowired](https://github.com/switon-php/di/blob/main/docs/autowired.md#autowired)
- **To apply IDs in repositories and entities:** [ORM Package](https://github.com/switon-php/orm/blob/main/docs/README.md)
