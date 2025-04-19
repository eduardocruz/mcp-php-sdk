<?php
// Sample code with errors for testing
function add($a, $b) {
    return $a + $c; // Undefined variable $c
}

$result = add("5", 10); // Type mismatch, string + int
echo $result;