# Tibia Price Tool CLI

This command-line tool fetches NPC buy/sell prices for Tibia items using data from TibiaWiki (https://tibia.fandom.com).  
It updates a CSV file containing item names with their respective prices, using Symfony Console and Guzzle.

---

## ✅ Features

- CLI interface with options (`--input`, `--output`)
- Fetches `Buy From` and `Sell To` NPC price ranges
- Logs warnings/errors to `logs/error.log`
- Progress bar for processing large CSVs

---

## 📦 Requirements

- PHP 8.3+
- Composer

---

## 🔧 Installation

```bash
composer install
```

---

## 🚀 Usage

```bash
php console update-prices
```

By default: `workCopyEquipment.csv` file is used as input and `workCopyEquipment_with_prices.csv` as output.


You can also specify input/output, if needed:

```bash
php console update-prices --input=items.csv --output=updated_items.csv
```

---

## 📁 Input CSV Format

CSV file must contain the following headers:

```csv
id;name;Tibia Buy Price;Tibia Sell Price;Is Missing Tibia source
```

Only the `name` column is used to match item names on TibiaWiki.

---

## 📂 Output

- Result CSV file (default: `workCopyEquipment_with_prices.csv`)
- Log file: `logs/error.log` (for failed fetches)

---

## 📞 Support

If an item has no matching page or price, a warning is logged and the row is skipped gracefully.
