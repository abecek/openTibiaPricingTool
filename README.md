# Open Tibia Pricing Tool

**Automated item data fetcher + spawn analyzer for Open Tibia Server development.**  
Fetches NPC buy/sell prices, required level, item images, and exports spawn monster density near cities for NPC pricing logic.

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
│   │    ├─ UpdateDataCommand.php
│   │    └─ AnalyzeSpawnsCommand.php
│   ├─ Scrapper/
│   │    ├─ TibiaItemDataUpdater.php
│   │    ├─ TibiaWikiDataScrapper.php
│   │    ├─ UrlBuilder.php
│   │    └─ OutputWriter.php
│   └─ SpawnAnalyzer/
│        ├─ CityRegistry.php
│        ├─ MonsterProximityAnalyzer.php
│        ├─ SpawnParser.php
│        ├─ Writer/
│        │     └─ SpawnAnalysisCsvWriter.php
│        └─ DTO/
│              ├─ City.php
│              ├─ MonsterCount.php
│              └─ SpawnEntry.php
├─ data/
│   ├─ input/
│   │    ├─ <your_items_xml ???>
│   │    ├─ <your_items_csv_file>           
│   │    └─ spawns/<your_spawn_file>
│   └─ output/
│        └─ <spawn_analysis_output.csv>
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
- Modular design (Scrapper, Analyzer, Writer components)

---

## 🔧 Possible Enhancements

- Export monster analysis to XLSX
- Integration with monsters loots
- Price suggestion engine per city
- Web interface or dashboard

---