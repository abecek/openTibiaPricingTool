# Opis działania narzędzia `opentibia_pricing_tool`

To narzędzie to **CLI w PHP** (Symfony Console) stworzone do **analizy, przetwarzania i generowania danych o przedmiotach, potworach, lootach i cenach** dla prywatnego serwera Tibia (TFS 1.5 downgrade 8.6).
Celem jest **zautomatyzowanie ekonomii gry** – od wyciągnięcia danych z plików XML TFS, przez wzbogacenie ich o dane z TibiaWiki, analizę spawnu potworów, integrację loota, po generowanie gotowych plików Lua dla NPC-handlarzy.

## Główne moduły i ich rola

1. **ExtractEquipmentFromXmlCommand (`extract:items-xml`)**
   - **Wejście:** plik `items.xml` z TFS
   - **Wyjście:** CSV z ustalonym porządkiem kolumn + dodatkowe pola (`Url`, `Level`, `Tibia Buy Price`, `Tibia Sell Price`, `Is Missing Tibia source`, `Buy`, `Sell`, `LootFrom`)
   - Filtruje tylko itemy z określonym `slotType` lub `weaponType`
   - Opcjonalnie uzupełnia kolumnę `Image` (`images/{name}.gif`)

2. **UpdateDataCommand (`update:data`)**
   - Aktualizuje dane w CSV/XLSX na podstawie nowych informacji (np. z webscrapingu)

3. **TibiaWikiDataScrapper**
   - Pobiera dane o przedmiotach (obrazy, ceny NPC, linki wiki) z TibiaWiki
   - Dane zapisuje w CSV lub XLSX

4. **AnalyzeSpawnsCommand (`analyze:spawns`)**
   - Wczytuje pliki spawnu potworów (XML) i przypisuje je do najbliższego miasta
   - Dane służą do określania lokalnej dostępności lootu

5. **LoadMonsterLootCommand (`load:monster-loot`)**
   - Wczytuje looty potworów z plików XML (`monsters.xml` + poszczególne potwory)
   - Parsuje `<item>` i `<inside>` z obsługą `id` i `name`

6. **SuggestPricesCommand (`suggest:prices`)**
   - **Wejście:** CSV/XLSX z itemami + dane o spawnach + lootach
   - **Wyjście:** Uzupełnione kolumny `Buy` i `Sell` (w formacie JSON per miasto)
   - Uwzględnia lokalną dostępność lootu i bazowe ceny Tibii
   - Zapobiega sytuacji, gdzie `buy` < `sell` w innym mieście
   - Obsługuje brak ceny jako `null`, nie `0`

7. **GenerateMerchantItemsCommand (`merchant:generate-items`)**
   - Generuje pliki Lua (`items/*.lua`) per kategoria (np. `weapons.lua`, `equipment.lua`)
   - Dane w formacie:
     ```lua
     local ITEMS = {
         [2181] = { id = 2181, name = "terra rod", slotType = nil, group = "wands", subType = 0,
             buy  = { Agren = 10000, Estimar = 8950 },
             sell = { Agren = 2000, Estimar = 2350 }
         }
     }
     return ITEMS
     ```
   - Waliduje kolumny i poprawność JSON w `Buy` i `Sell`
   - Przy `--debug` podaje statystyki (ile swordów, axe’ów itd.)

## Kluczowe klasy narzędziowe
- **EquipmentCsvReader / EquipmentXlsxReader** – wczytują CSV/XLSX z itemami
- **PriceSuggestionEngine** – główny silnik logiki cen
- **LuaTableDumper** – konwertuje PHP array do Lua table z odpowiednimi wcięciami
- **MerchantDataValidator** – waliduje dane wejściowe przed generacją Lua
- **SpawnAnalyzer** – zestaw klas do analizy spawnu potworów
- **MonsterLoot** – zestaw klas DTO i loaderów lootu

## Ogólny przepływ pracy
1. **Ekstrakcja itemów** z `items.xml` → CSV (`extract:items-xml`)
2. **Uzupełnienie danych** z TibiaWiki (`scrapper`)
3. **Analiza spawnu** (`analyze:spawns`)
4. **Ładowanie lootu** (`load:monster-loot`)
5. **Sugestia cen** (`suggest:prices`)
6. **Generowanie Lua** dla NPC-handlarzy (`merchant:generate-items`)
