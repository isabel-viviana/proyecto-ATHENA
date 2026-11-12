<?php

$sampleWithMarkdown = "```json\n{\n  \"questions\": [\n    {\n      \"order\": 1\n    }\n  ]\n}\n```";

$bad = json_decode($sampleWithMarkdown, true);
echo "Direct json_decode: " . var_export($bad, true) . " (Error: " . json_last_error_msg() . ")\n";

function cleanJson($text) {
    $text = trim($text);
    if (str_starts_with($text, '```')) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);
    }
    return json_decode($text, true);
}

$good = cleanJson($sampleWithMarkdown);
echo "Cleaned json_decode: " . var_export($good, true) . "\n";

