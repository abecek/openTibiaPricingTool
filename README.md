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
│   ├─ Command/
│   │    ├─ AbstractCommand.php
│   │    ├─ AnalyzeSpawnsCommand.php
│   │    ├─ LoadMonsterLootCommand.php
│   │    └─ UpdateDataCommand.php
│   ├─ Scrapper/
│   │    ├─ TibiaItemDataUpdater.php
│   │    ├─ TibiaWikiDataScrapper.php
│   │    ├─ UrlBuilder.php
│   │    └─ OutputWriter.php
│   ├─ SpawnAnalyzer/
│   │    ├─ CityRegistry.php
│   │    ├─ MonsterProximityAnalyzer.php
│   │    ├─ SpawnCsvReader.php
│   │    ├─ SpawnParser.php
│   │    ├─ Writer/
│   │    │     └─ SpawnAnalysisCsvWriter.php
│   │    └─ DTO/
│   │          ├─ City.php
│   │          ├─ MonsterCount.php
│   │          └─ SpawnEntry.php
│   ├─ Item/
│   │    └─ ItemLookupService.php
│   ├─ MonsterLoot/
│   │    ├─ DTO/
│   │    │     ├─ LootItem.php
│   │    │     └─ MonsterLoot.php
│   │    ├─ MonsterDataProvider.php
│   │    ├─ MonsterLootLoader.php
│   │    └─ SpawnLootIntegrator.php
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

- Export monster analysis to XLSX
- Export integrated loot to structured file
- Price suggestion engine per city
- Web interface or dashboard

---