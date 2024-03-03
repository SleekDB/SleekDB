# SleekDB - PHP NoSQL Database

![SleekDB Logo](https://sleekdb.github.io/assets/SleekDB-Logo.jpg)

**Full Documentation:** [https://sleekdb.github.io/](https://sleekdb.github.io/)

SleekDB is a NoSQL database crafted in PHP, relying on plain JSON files for data storage. Designed for simplicity, it suits scenarios requiring a lightweight database for managing several gigabytes of data. It's ideal for small to medium load operations, providing a straightforward alternative for database management without the need for complex setups.

## Key Features

- **Fast & Lightweight:** Utilizes plain JSON files; no binary data conversion. Comes with a default query cache layer.
- **Schema-Free:** Inserts any data type, supporting queries on nested properties.
- **No Dependencies:** Only requires PHP 7+ to operate.
- **Advanced Querying:** Supports rich conditions, filters, and text search on nested properties.
- **Flexible & Easy to Use:** Provides an elegant API, perfect for quick implementations and processes data on-demand within the PHP runtime.
- **Portable:** Ideal for both shared hosting and VPS environments.
- **Easy Data Management:** Simplifies backup, import, and export tasks using file-based storage.
- **Actively Maintained:** Developed by [@rakibtg](https://twitter.com/rakibtg) and Timucin [GoodSoft](https://www.goodsoft.de) as an active contributor, ensuring quality and ongoing enhancements.
- **Well Documented:** Comprehensive [documentation](https://sleekdb.github.io/) with detailed examples.

## Tests

To run tests you need to install the dev dependencies using composer:

```bash
composer install
```

Then you can run the tests using the following command:

```bash
composer run test
```

## Support SleekDB

Loving SleekDB? Consider sponsoring the project to help it grow and improve! Your support will contribute to the development of new features, maintenance, and better documentation. Visit our [GitHub sponsors page](https://github.com/sponsors/sleekdb) to show your support. Every contribution makes a difference!

**Visit our [website](https://sleekdb.github.io/) for the documentation and getting started guide.**
