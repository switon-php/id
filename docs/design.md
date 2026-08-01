# Design Rationale {#design-rationale}
`switon/id` keeps ID choice explicit in DI while leaving algorithm-specific helpers on concrete generators.

## See also {#see-also}

- [ID Package](README.md) — generator selection, configuration, and runtime boundaries
- [FAQ](faq.md#id-faq) — common questions and troubleshooting

---

## Core Problem {#core-problem}

ID choice leaks storage and public API assumptions quickly.

- one service wants sortable integers
- another wants UUID-compatible strings
- a third wants compact public tokens
- algorithm metadata should not pretend to be uniform

The package exists to keep that choice visible without forcing each call site to know the algorithm.

## Primary Goal {#primary-goal}

Switon ID standardizes one small generation contract first, then lets DI pick the concrete generator type by name.

- **Benefit:** service code stays uniform.
- **Benefit:** the default remains simple.
- **Cost:** algorithm-specific helpers stay on concrete classes instead of the shared interface.

## Default First {#default-first}

The unnamed binding resolves to `uuid4`.

- use `uuid7` or `ulid` when order matters
- use `snowflake` when you need integer IDs and can support Redis
- use `nanoId` or `cuid2` when compact opaque text is the real requirement

That makes the common path easy while keeping stronger constraints opt-in.

## Concrete Helpers Stay Concrete {#concrete-helpers-concrete}

The shared interface stays small on purpose.

- `Snowflake::parse()` stays Snowflake-specific
- `Ksuid::extractTimestamp()` stays KSUID-specific
- `MongoId::extractTimestamp()` and `MongoId::isValid()` stay MongoId-specific
- `ShortUuid` stays a static UUID helper

The trade-off is less cross-generator uniformity, but the package avoids a fake universal API.

## Snowflake Is The Exception {#snowflake-exception}

`Snowflake` is the only built-in generator with external coordination.

- it needs Redis-backed sequence allocation
- it keeps per-process batch state for efficiency
- it is a good fit for write-heavy integer IDs, not for the simplest default path
