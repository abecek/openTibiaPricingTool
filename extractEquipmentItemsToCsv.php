<?php

declare(strict_types=1);

// File paths
$inputFile = 'items.xml';
$outputFile = 'itemsWorkFile.csv';

// Attribute keys of interest
$targetAttributeKeys = ['slotType', 'weaponType'];

// Check if input file exists
if (!file_exists($inputFile)) {
	die("File '$inputFile' does not exist.\n");
}

// Initialize XMLReader for large XML parsing
$reader = new XMLReader();
if (!$reader->open($inputFile)) {
	die("Failed to open XML file: $inputFile\n");
}

// Arrays to store matching items and all attribute keys
$items = [];
$allAttributeKeys = [];

while ($reader->read()) {
	if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'item') {
		$node = new SimpleXMLElement($reader->readOuterXML());

		$itemId = (string)$node['id'];
		$itemName = (string)$node['name'];
		$itemData = [
			'id' => $itemId,
			'name' => $itemName
		];

		$hasTargetAttribute = false;

		// Iterate over <attribute> nodes
		foreach ($node->attribute as $attr) {
			$key = (string)$attr['key'];
			$value = (string)$attr['value'];

			if (in_array($key, $targetAttributeKeys, true)) {
				$hasTargetAttribute = true;
			}

			$itemData[$key] = $value;
			$allAttributeKeys[$key] = true;
		}

		// Store item if it contains any target attribute
		if ($hasTargetAttribute) {
			$items[] = $itemData;
		}

		unset($node); // free memory
	}
}

$reader->close();

// Open CSV file for writing
$csv = fopen($outputFile, 'w');
if (!$csv) {
	die("Failed to open CSV output file: $outputFile\n");
}

// Prepare and write CSV header
$allKeys = array_keys($allAttributeKeys);
sort($allKeys);
$header = array_merge(['id', 'name'], $allKeys);
fputcsv($csv, $header);

// Write item data
foreach ($items as $item) {
	$row = [];
	foreach ($header as $key) {
		$row[] = $item[$key] ?? '';
	}
	fputcsv($csv, $row);
}

fclose($csv);

echo "Exported " . count($items) . " items with 'slotType' or 'weaponType' attribute to '$outputFile'.\n";
