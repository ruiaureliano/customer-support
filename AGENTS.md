# Promos

## PHP style conventions

- PascalCase class names: `Products`, `Categories`, `Prices`, `Vendors`, `Result`
- `error_reporting(E_ERROR | E_PARSE);` at top of PHP files
- Tab indentation, braces on the same line
- Lower camelCase for method names and variables
- Use single quotes for strings unless interpolation is needed
- Prefer `switch (true)` instead of `if/elseif/else` chains
- Keep PHP filenames lowercase (class names still PascalCase)
- Keep `switch (true)` for validation chains
- Keep private members at the top of classes under a comment divider, then public members below.
- Sort class members alphabetically within private/public sections (methods, properties, constants).
- When using control structures in files that mix PHP with HTML (templates, views, etc.), always use the alternative syntax:
  - `if ... endif;`
  - `foreach ... endforeach;`
  - `for ... endfor;`
  - `while ... endwhile;`
  - `switch ... endswitch;`
