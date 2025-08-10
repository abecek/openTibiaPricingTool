# Open Tibia Pricing Tool

**Automated item data fetcher + spawn analyzer + monster loot loader for Open Tibia Server development.**  
Fetches NPC buy/sell prices, required level, item images, integrates spawn monster density near cities, and analyzes loot data per monster.

---

## 🚀 Features

### Item Data Scraper (`update:data`)
- Symfony Console CLI command: `update:data`
- Scrapes data from TibiaWiki for each item:
  - **Buy/Sell Prices** from NPC trade tables
  - **Required Level** from item sidebar
  - **32x32 image icon** saved to `images/`
- Options:
  - `--input` – path to input `.csv` file
  - `--output` – output path (without extension)
  - `--format` – `csv` or `xlsx`
  - `--debug` – enables debug logs (file + console)
- Output generation:
  - Supports **CSV** and **XLSX with embedded images**
  - Auto-resized columns, adjusted row height

---

### Spawn Analyzer (`analyze:spawns`)
- Symfony Console CLI command: `analyze:spawns`
- Parses spawn XML and assigns monster counts to closest city
- Ignores Z-axis (2D proximity)
- Per-city radius supported (`City::getRadius()`)
- Options:
  - `--spawnfile` – path to spawn `.xml` file
  - `--output=csv` – enables CSV export to `data/output/spawn_analysis_output.csv`
  - `--debug` – enables debug logs (file + console)
- Output contains:
  - `City`, `Radius`, `Monster`, `Count`
- Uses accurate `centerx/centery` + offset `(x/y)` to calculate monster positions

---

### Monster Loot Integration (`monster:load-loot`)
- Symfony Console CLI command: `monster:load-loot`
- Integrates monster loot from TFS XML files
- Requires:
  - `--monster-dir` – path to TFS `/monster/` directory
- Options:
  - `--spawn-csv` – path to spawn analysis CSV (output of `analyze:spawns`, default `data/output/spawn_analysis_output.csv`)
- Output:
  - Prints loot data for each monster in each city
  - Supports nested loot via `<inside>`
  - Resolves **item `id` ↔ `name`** using `items.xml` (loaded once)
- Loot items are printed as:
  ```
  Demon:
    - bag (ID: 1987, chance: 100000, countMax: n/a)
      - life ring (ID: 3050, chance: 500, countMax: n/a)
      - crystal coin (ID: 3043, chance: 200, countMax: 1)
  ```

### 💰 Price Suggestion Engine

A tool for automatically suggesting localized NPC buy/sell prices for items based on monster loot availability around cities.

#### Command (`merchant:suggest-prices`)

- Supports **CSV** and **XLSX** as both input and output formats
- Uses data from:
  - Equipment file (`--equipment-csv` or `--equipment-xlsx`)
  - Monster loot file (`--loot-csv`)
  - Spawn data file (`--spawn-csv`)
- Generates **Buy** and **Sell** columns as JSON per city in the following format:
  ```json
  {"Agren": 1500, "Estimar": 1550, "Ohara": 1600, "Sacrus": 1500, "Sagvana": 1500}
  ```
- If an item is not available in monster loot around a given city, base **Tibia Buy Price** and **Tibia Sell Price** are used (or `null` if missing)
- Prevents cross-city exploitation:
  - Ensures no city’s `buy` price is lower than any other city’s `sell` price for the same item
- Options:
  - `--equipment-csv` / `--equipment-xlsx` – equipment data source
  - `--loot-csv` – monster loot data source
  - `--spawn-csv` – spawn proximity data
  - `--format` – `csv` or `xlsx` for output
  - `--debug` – shows detailed processing info

#### Data Sources

1. **Equipment data**  
   Contains item metadata and base Tibia prices.
2. **Loot data**  
   Maps monsters to their loot tables with drop chances.
3. **Spawn data**  
   Assigns monsters to their nearest cities (ignoring floor level).

#### Example Output Row

| id   | name           | Tibia Buy Price | Tibia Sell Price | Buy JSON                                                                                      | Sell JSON                                                                                     |
|------|----------------|-----------------|------------------|-----------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------|
| 2376 | sword          | 85               | 25               | `{"Agren":85,"Estimar":85,"Ohara":85,"Sacrus":85,"Sagvana":85}`                               | `{"Agren":25,"Estimar":25,"Ohara":25,"Sacrus":25,"Sagvana":25}`                               |
| 2195 | boots of haste | 30000            | 9000              | `{"Agren":29500,"Estimar":30000,"Ohara":30000,"Sacrus":30000,"Sagvana":30000}`               | `{"Agren":9000,"Estimar":9000,"Ohara":9000,"Sacrus":9000,"Sagvana":9000}`                     |

---

**Note:**  
The Price Suggestion Engine should be run **after** loot data and spawn data are up-to-date to ensure price suggestions reflect the current in-game economy.

### 🛒 Merchant System (NPC Traders)

A new module for automatically generating merchant data in Lua format, ready to be loaded in TFS.

#### Generation Command (`merchant:generate-items`)

- Supports **CSV** and **XLSX** input formats
- Generates Lua files per category (e.g., `weapons.lua`, `wands.lua`, `equipment.lua`) based on the following columns:
  - `id`, `name`, `slotType`, `weaponType`, `group`
  - `Buy` (JSON), `Sell` (JSON)
- **Buy** and **Sell** are converted into Lua tables with proper indentation
- Supported options:
  - `--equipment-file` – source CSV/XLSX file
  - `--format` – `csv` or `xlsx`
  - `--dst-dir` – target directory (`items/`)
  - `--include-tibia-lists` – includes `tibiaBuy` / `tibiaSell` lists (optional)
  - `--debug` – displays detailed statistics
- Data validation:
  - Checks for required columns (`id`, `name`, `Buy`, `Sell`)
  - Validates JSON in `Buy` and `Sell` columns
  - Can abort the process if validation fails
- With `--debug` enabled, prints statistics:
  - Total number of generated items
  - Count per category (`swords`, `axes`, `bows`, etc.)

#### Format of Generated `items/*.lua` Files

Each Lua file contains a local `ITEMS` table:

```lua
  local ITEMS = {
      [2181] = { id = 2181, name = "terra rod", slotType = nil, group = "wands", subType = 0,
          buy  = { Agren = 10000, Estimar = 8950, Ohara = 9750, Sacrus = 10000, Sagvana = 10000 },
          sell = { Agren = 2000,  Estimar = 2350, Ohara = 2100, Sacrus = 2000,  Sagvana = 2000 }
      },
      -- more items...
  }
  return ITEMS
```

---

## 📦 Installation

```bash
composer install
```

---

## 🧪 Usage Examples

### Item Data Scraper

```bash
php console update:data \
  --input=data/input/workCopyEquipment.csv \
  --output=data/output/workCopyEquipment_extended \
  --format=xlsx \
  --debug
```

### Spawn Analyzer

```bash
php console analyze:spawns \
  --spawnfile=data/input/spawns/test3-860-spawn.xml \
  --output=csv \
  --debug
```

### Monster Loot Loader

```bash
php console monster:load-loot \
  --monster-dir=/path/to/TFS/monster \
  --spawn-csv=data/output/spawn_analysis_output.csv \
  --debug
```

E.g.
```bash
php console monster:load-loot \
  --monster-dir="C:\otsDev\TFS-1.5-Downgrades-8.60-upgrade\data\monster"  
```
E.g. 2
```bash
php console monster:load-loot \
  --monster-dir="C:\otsDev\TFS-1.5-Downgrades-8.60-upgrade\data\monster" \
  --loot-output="data/output/monster_loot_output.csv"
```

### Custom Price Suggester

```bash
php console suggest:prices \
  --format=xlsx \
  --debug 
```

### Npc Merchant System Data Populator

### CSV
```bash
php console merchant:generate-items \
  --equipment-file=data/output/workCopyEquipment_extended.csv \
  --format=csv \
  --dst-dir=data/lib/core/customs/merchant/items \
  --include-tibia-lists
```

### XLSX
```bash
php console merchant:generate-items \
  --equipment-file=data/output/workCopyEquipment_extended.xlsx \
  --format=xlsx \
  --dst-dir=data/lib/core/customs/merchant/items
```

E.g.
```bash
php console merchant:generate-items \
  --equipment-file=data/output/workCopyEquipment_extended.csv \
  --format=csv \
  --dst-dir="C:\otsDev\TFS-1.5-Downgrades-8.60-upgrade\data\lib\core\customs\merchant\items" \
  --debug
```

```bash
php console merchant:generate-items \
  --equipment-file=data/output/workCopyEquipment_extended.xlsx \
  --format=xlsx \
  --dst-dir="C:\otsDev\TFS-1.5-Downgrades-8.60-upgrade\data\lib\core\customs\merchant\items" \
  --debug
```


---

## 🧾 Input CSV Format (for update:data)

Input file must include a header row like:

```
id;name;Image;slotType;weaponType;Url;Level;Tibia Buy Price;Tibia Sell Price;Is Missing Tibia source;...
```

- Separator: `;` (semicolon)
- Required columns: `id`, `name`
- Other columns are updated or appended

---

## 📁 Project Structure

```
├─ src/
│   ├── Command/
│   │   ├── AbstractCommand.php
│   │   ├── AnalyzeSpawnsCommand.php
│   │   ├── GenerateMerchantItemsCommand.php
│   │   ├── LoadMonsterLootCommand.php
│   │   ├── SuggestPricesCommand.php
│   │   └── UpdateDataCommand.php
│   │
│   ├── Item/
│   │   └── ItemLookupService.php
│   │
│   ├── Merchant/
│   │   ├── LuaTableDumper.php
│   │   └── MerchantDataValidator.php
│   │
│   ├── MonsterLoot/
│   │   ├── DTO/
│   │   │   ├── LootItem.php
│   │   │   └── MonsterLoot.php
│   │   ├── Writer/
│   │   │   └── MonsterLootCsvWriter.php
│   │   ├── MonsterDataProvider.php
│   │   ├── MonsterLootCsvReader.php
│   │   ├── MonsterLootLoader.php
│   │   └── SpawnLootIntegrator.php
│   │
│   ├── Pricing/
│   │   ├── EquipmentCsvReader.php
│   │   ├── EquipmentXlsxReader.php
│   │   ├── EquipmentXlsxUpdater.php
│   │   └── PriceSuggestionEngine.php
│   │
│   ├── Scrapper/
│   │   ├── OutputWriter.php
│   │   ├── TibiaItemDataUpdater.php
│   │   ├── TibiaWikiDataScrapper.php
│   │   └── UrlBuilder.php
│   │
│   └── SpawnAnalyzer/
│   ├── DTO/
│   │   ├── City.php
│   │   ├── MonsterCount.php
│   │   └── SpawnEntry.php
│   ├── Writer/
│   │   └── SpawnAnalysisCsvWriter.php
│   ├── CityRegistry.php
│   ├── MonsterProximityAnalyzer.php
│   ├── SpawnCsvReader.php
│   └── SpawnParser.php
│
├─ data/
│   ├─ input/
│   │    ├─ items.xml
│   │    ├─ workCopyEquipment.csv
│   │    └─ spawns/test3-860-spawn.xml
│   └─ output/
├─ logs/
│   ├─ debug.log
│   └─ error.log
├─ images/
└─ console
```

---

## 🧾 Logging

Logs are stored in:

```
logs/error.log   – Warnings, errors  
logs/debug.log   – Detailed logs if --debug is enabled  
```

When `--debug` is enabled:
- Logs are written to file
- Also printed to console with color formatting (INFO, WARNING, ERROR)

---

## 🧱 Under the Hood

- PHP 8.3
- Symfony Console
- PSR-4 autoloading (`App\` namespace)
- Robust DOM/XPath parsing with fallback handling
- Modular design (Scrapper, Analyzer, Loot components)

---

## 🔧 Possible Enhancements

- Web interface or dashboard

---
