# Switon ID Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/id/ci.yml?branch=main&label=CI)](https://github.com/switon-php/id/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's ID generation package for interface-first services that need selectable strategies, sortable identifiers, and
compact public IDs.

## Highlights

- **Pluggable ID generation:** `IdGeneratorInterface` can be swapped as needed.
- **Named ID strategies:** common strategies are ready to use through the service provider.
- **Distributed Snowflake support:** sequence allocation can be coordinated through Redis.
- **Time-ordered identifiers:** `Uuid7`, `Ulid`, and `Ksuid` produce sortable string IDs.
- **Compact formats:** `NanoId`, `Cuid2`, `ShortUuid`, and `MongoId` cover shorter public IDs.

## Installation

```bash
composer require switon/id
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Id\IdGeneratorInterface;

class OrderService
{
    #[Autowired] protected IdGeneratorInterface $idGenerator;

    public function createOrder(): string
    {
        return (string) $this->idGenerator->next();
    }
}
```

Docs: https://docs.switon.dev/latest/id

## License

MIT.
