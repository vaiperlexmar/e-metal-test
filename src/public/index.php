<?php

declare(strict_types=1);

$data = json_decode(file_get_contents('data.json'), true);
$referenceData = file_get_contents('https://e-metall.ru/docs/fields/');

$dom = new DOMDocument();
@$dom->loadHTML($referenceData);
$xpath = new DOMXPath($dom);

$newArray = [];

function getCurrentCustomFieldValue($colCode, $item)
{
    switch ($colCode) {
        case "item_steel_mark":
            return $item["alloy"] ?? null;
        case "item_standart":
            return $item['standard'] ?? null;

        case "item_wall":
            return parseSize($item['size'], 'wall') ?? parseFromTitle($item['title'], 'wall');

        case "item_width":
            return parseSize($item['size'], 'width') ?? parseFromTitle($item['title'], 'width');

        case "item_length":
            return parseSize($item['size'], 'length') ?? parseFromTitle($item['title'], 'length');

        case "item_diameter":
            return parseSize($item['size'], 'diameter') ?? parseFromTitle($item['title'], 'diameter');

        case "item_size":
            $width = parseSize($item['size'], 'width') ?? parseFromTitle($item['title'], 'width');
            $length = parseSize($item['size'], 'length') ?? parseFromTitle($item['title'], 'length');
            return "{$width}x{$length}";


        default:
            return null;
    }
}

function parseSize(string $size, string $type): ?string
{
    if ($size === '-' || empty($size)) {
        return null;
    }

    $parts = preg_split('/[xх]/u', $size);
    if (!$parts) {
        return null;
    }

    switch ($type) {
        case 'wall':
            return $parts[0] ?? null;
        case 'width':
            return $parts[1] ?? null;
        case 'length':
            return $parts[2] ?? null;
    }

    return null;
}

function parseFromTitle(string $title, string $type): ?string
{
    if ($type === 'wall') {
        if (preg_match('/(\d+(?:\.\d+)?)[xх](\d+(?:\.\d+)?)[xх](\d+)/u', $title, $matches)) {
            return $matches[2];
        }
    }
    if ($type === 'width') {
        if (preg_match('/\b\d+x(\d+)(?:x\d+)?\b/u', $title, $matches)) {
            return $matches[1];
        }
    }
    if ($type === 'length') {
        if (preg_match('/(\d+(?:\.\d+)?)[xх](\d+(?:\.\d+)?)[xх](\d+)/u', $title, $matches)) {
            return $matches[3];
        }
    }

    if ($type === 'diameter') {
        if (preg_match('/(\d+(?:\.\d+)?)[xх](\d+(?:\.\d+)?)[xх](\d+)/u', $title, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

function parseHtmlTableToArray($node, $item): array
{
    $result = [];

    if (!is_null($node)) {
        $nodeTable = $node->nextSibling;
        for ($i = 1; $i < $nodeTable->childNodes->length; $i++) {
            if ($i <= 4) {
                $key = $nodeTable->childNodes[$i]->childNodes[1]->textContent;
                $value = $nodeTable->childNodes[$i]->childNodes[2]->textContent;
                $result[$key] = $value;
            } else {
                $colName = $nodeTable->childNodes[$i]->childNodes[0]->textContent;
                $colCode = $nodeTable->childNodes[$i]->childNodes[1]->textContent;
                $colValue = getCurrentCustomFieldValue($colCode, $item);
                $result["columns"][] = ["col_name" => $colName, "col_code" => $colCode, "value" => $colValue];
            }
        }

        $result["isMetal"] = true;

        return $result;
    } else {
        $result["isMetal"] = false;
    }

    return $result;
}

function parseToArray($data, $xpath, $newArray): array
{
    foreach ($data as $item) {
        $categoryDiv = $xpath->query(
            '//div[contains(@class, "category-name") and contains(text(), "'.$item["category"].'")]'
        );
        $extraFields = parseHtmlTableToArray($categoryDiv[0], $item);
        if ($extraFields["isMetal"]) {
            unset($extraFields["isMetal"]);
            $concatenatedParams = implode("|", $item);
            $newArray[$item["item_id"]] = ["name" => $item["category"], ...$extraFields, "concatenated_params" => $concatenatedParams];
        }
    }

    return $newArray;
}


echo "<pre>";
print_r(parseToArray($data, $xpath, $newArray));
echo "</pre>";
