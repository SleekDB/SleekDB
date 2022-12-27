# SleekDB V3

SleekDB v3 is in implementation stage.

## Goal:

- Introducing a new Engine called "Poly" to support database storage based on a single file.
- Move the old storage engine to an optional engine called "Mono".
- Keep the API as simple as possible.
- Support the old data storage format with multiple files and folders using the Mono engine.

## Roadmap:

- [x] Remove "SleekDB.php" legacy API.
- [x] Remove "timeout" config.
- [x] Remove "nestedWhere" depricated method which was introduced in (v2.3).
- [x] Fix phpunit tests and add PHP 8 support.
- [x] Refactor existing storage processing system to Mono Engine.
- [x] Isolate and simplify the Store class.
- [ ] Implement the Poly engine.
  - [ ] Remove the "\_id" field requirement with the new Poly engine.
  - [ ] Introduce `defragment()` method to Poly engine. To remove unused allocated space from a file.
  - [ ] Introduce `resizeDocument()` method to Poly engine. (CLI?)
  - [ ] In poly engine the caching system will not be embeded with nested documents, instead it should keep the list of used documents id in an array inside each cache file. With those specific documents the query will be executed and the result should generate same result as well as the `md5` should match as well. If the `md5` does not match, the cache should be removed and the query should be executed again on the whole database documents as usual.
- [ ] Keep the API compatible with both engine.
- [ ] Modify the `IoHelper` class to support the new Poly engine.
- [ ] A re-write of the caching system might be needed to support the new Poly engine caching.
- [ ] Prepare the documentation for v3.

## Discussion points:

- Should we keep the old Mono engine as an optional engine?
- Should we implement indexing for Poly engine? There is a huge potential for implementing a new indexing system for Poly engine. Although with schema less document it can be a challange to implement a good indexing system. Let's discuss!
