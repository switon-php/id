# Switon ID Package

Distributed ID generation for Switon Framework.

## Installation

```bash
composer require switon/id
```

**Requirements:** PHP 8.3+

## Quick Start

```php
use Switon\Id\IdGeneratorInterface;
use Switon\Core\Attribute\Autowired;

class OrderService
{
    #[Autowired] protected IdGeneratorInterface $uuid7;

    public function createOrder(array $data): string
    {
        return $this->uuid7->next();
    }
}
```

Docs: https://docs.switon.dev/latest/id

## License

MIT.
