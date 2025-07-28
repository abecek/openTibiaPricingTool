# otItemsScrapper

**Automated item data fetcher for TibiaWiki (Fandom)**  
Fetches NPC buy/sell prices, required level, item images, and exports the result as CSV or XLSX.

---

## 🚀 Features

- Symfony Console CLI command: `tibia:update-data`
- Options:
  - `--input` – path to input `.csv` file
  - `--output` – output file path
  - `--format` – either `csv` (default) or `xlsx`
  - `--debug` – enables debug logging (to `logs/debug.log` and console output)
- Parses input CSV with validation and BOM handling
- For each item, scrapes data from TibiaWiki:
  - **Buy/Sell Prices** from NPC trade table
  - **Required Level** from infobox sidebar
  - **32x32 image icon**, saved locally in `images/`
- Output generation:
  - Supports CSV and **XLSX with embedded images**
  - Automatically resizes columns to fit content
  - Applies minimum row height for proper image rendering

---

## 📦 Installation

Run in your project root:

```bash
composer install
```

---

## 🧪 Usage Example

```bash
php console tibia:update-prices \
  --input=items.csv \
  --output=updated_items.xlsx \
  --format=xlsx \
  --debug
```

- `--input`: path to input `.csv` file
- `--output`: output file path (CSV or XLSX)
- `--format`: `csv` or `xlsx` (default: csv)
- `--debug`: enables detailed logging to file and console

---

## 🧾 Input CSV Format

Input file must include a header row like:

```
id;name;Image;slotType;weaponType;Url;Level;Tibia Buy Price;Tibia Sell Price;...
```

- Separator: `;` (semicolon)
- Required columns: `id`, `name`
- Other columns are updated or appended
- Invalid or incomplete rows are skipped with warnings

---

## 📁 Project Structure

```
├─ src/
│   ├─ src/
│       └─ Command/UpdateDataCommand.php
│   ├─ TibiaItemPriceUpdater.php
│   ├─ TibiaWikiDataScrapper.php
│   ├─ UrlBuilder.php
│   ├─ OutputWriter.php
├─ console             # CLI entrypoint
├─ images/             # Downloaded item icons (GIF)
├─ logs/
│   ├─ error.log
│   └─ debug.log
└─ workCopyEquipment.csv  # Example input file
```

---

## 💡 How It Works

For each item:
1. A slug URL is generated for TibiaWiki (e.g. `Two_Handed_Sword`)
2. Data is scraped:
   - Sell/Buy prices from the `npc-trade` section
   - Required level from the sidebar
   - Image from infobox
3. All data is merged into the original row
4. Result is saved to file

---

## 📤 Output (.xlsx)

- Columns auto-resize based on the longest cell value
- Embedded icons (32×32) shown directly in cells
- Row height adjusted for image visibility

---

## 🧾 Logging

Logs are stored in:

```
logs/error.log   – Warnings, errors
logs/debug.log   – Detailed logs if --debug is enabled
```

When `--debug` is enabled, messages are also printed to the console with colors (INFO, WARNING, ERROR).

---

## 🧱 Under the Hood

- Written in **PHP 8.3**
- Follows **PSR-4 autoloading** with `App\` namespace
- Input validation + fallback behavior
- `OutputWriter` handles CSV/XLSX formatting and image rendering

---

## 🔧 Possible Enhancements

- Result caching
- Retry/backoff strategy on HTTP errors
- Fallback to alternative data sources (e.g. Tibiopedia)
- GUI wrapper

---
