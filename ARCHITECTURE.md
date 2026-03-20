# Architecture: statscatalog

## Purpose

A PrestaShop statistics module that provides a catalogue health overview: counts of enabled/disabled products, products without images, descriptions or prices, and category counts.

## Directory Structure

```
statscatalog.php   - Module class; all business logic
upgrade/           - Migration scripts
tests/             - PHPUnit test stubs and PHPStan bootstrap
translations/      - Locale string overrides
```

## Key Design Decisions

- **Catalogue introspection**: Runs multiple lightweight SQL queries to surface data-quality issues in the product catalogue.
- **No ModuleGrid/Graph**: Renders a plain HTML summary table rather than a sortable grid or chart.

## Extension Points

- Add additional quality checks (e.g., products without SEO meta) by extending the rendered HTML in `hookDisplayAdminStatsModules()`.

## Dependency Flow

```
statscatalog (Module)
  └─> hookDisplayAdminStatsModules() — renders the catalogue health summary
        └─> Db::getInstance()        — executes catalogue count queries
```
